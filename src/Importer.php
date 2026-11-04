<?php

namespace Javaabu\Imports;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Columns\ValueConverter;
use Javaabu\Imports\Enums\Duplicates;
use Javaabu\Imports\Enums\Formats;
use Javaabu\Imports\Enums\RowOutcomes;
use Javaabu\Imports\Mapping\Mapping;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Processing\FailureReport;
use Javaabu\Imports\Processing\ImportResult;
use Javaabu\Imports\Processing\ImportRunner;
use Javaabu\Imports\Processing\Preview;
use Javaabu\Imports\Readers\SpreadsheetReader;
use Javaabu\Imports\Support\TemporaryFile;
use Javaabu\Imports\Writers\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reads a spreadsheet and imports its rows: maps headings to columns, converts and validates
 * each row, and hands valid rows to persist()
 *
 * Extend ModelImporter to import Eloquent models. Extend this class directly for anything
 * else, implementing columns() and persist().
 */
abstract class Importer
{
    /** @var array<int|string, string|null>|Mapping|null */
    protected array|Mapping|null $mapping = null;

    protected ?Duplicates $duplicates = null;

    protected bool $dry_run = false;

    /** @var array<string, Column>|null */
    protected ?array $resolved_columns = null;

    protected ?ValueConverter $converter = null;

    /** @var array<string, mixed>|null */
    protected ?array $compiled = null;

    /**
     * The fields rows fill: Column definitions or attribute names
     *
     * @return array<int, Column|string>
     */
    abstract public function columns(): array;

    /**
     * Save one valid row
     *
     * @param  array<string, mixed>  $data  attribute => value, validated
     */
    abstract public function persist(array $data, int $row): RowOutcomes;

    /**
     * Make the importer. Arguments go to the constructor; keep them serializable (ids,
     * strings), because queued imports serialize the importer.
     */
    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * What the importer imports, shown in the imports page: `ProductsImporter` → `Products`
     */
    public function label(): string
    {
        return __(Str::headline(Str::beforeLast(class_basename(static::class), 'Importer')));
    }

    /**
     * Whether the user may run this importer. Return false to hide it from the imports page.
     */
    public function authorize(?Model $user): bool
    {
        return true;
    }

    /**
     * Extra validation rules, by attribute, added to the columns' rules. Rules can refer to
     * other attributes of the row (`required_without:email`).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Change a row after its values are read and before it's validated
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareRow(array $data, int $row): array
    {
        return $data;
    }

    /**
     * Called with the valid rows of a chunk before they're saved, e.g. to load related
     * records for all of them at once
     *
     * @param  array<int, array<string, mixed>>  $rows  row number => data
     */
    public function beforePersist(array $rows): void {}

    /**
     * What persist() would do with a valid row, for previews and dry runs
     *
     * @param  array<string, mixed>  $data
     */
    public function previewOutcome(array $data, int $row): RowOutcomes
    {
        return RowOutcomes::CREATED;
    }

    /**
     * The row that holds the headings (from 1)
     */
    public function headingRow(): int
    {
        return (int) config('imports.heading_row', 1);
    }

    /**
     * The sheet to read: an index (from 0) or a name
     */
    public function sheet(): int|string
    {
        return 0;
    }

    /**
     * How many rows are validated and saved at a time, in one transaction
     */
    public function chunkSize(): int
    {
        return max((int) config('imports.chunk_size', 500), 1);
    }

    /**
     * The database connection rows are saved in, for the chunk transactions
     */
    public function connectionName(): ?string
    {
        return null;
    }

    /**
     * Choose which column of the file fills which field. Unmapped files are mapped
     * automatically from their headings.
     *
     * @param  array<int|string, string|null>|Mapping|null  $mapping  heading index or text => attribute
     */
    public function map(array|Mapping|null $mapping): static
    {
        $this->mapping = $mapping;

        return $this;
    }

    public function duplicates(Duplicates|string $duplicates): static
    {
        $this->duplicates = Duplicates::resolve($duplicates);

        return $this;
    }

    public function getDuplicates(): Duplicates
    {
        return $this->duplicates ?? Duplicates::resolve(null);
    }

    /**
     * Validate every row and count what would happen, without saving anything
     */
    public function dryRun(bool $dry_run = true): static
    {
        $this->dry_run = $dry_run;

        return $this;
    }

    public function isDryRun(): bool
    {
        return $this->dry_run;
    }

    /**
     * The columns, keyed by attribute
     *
     * @return array<string, Column>
     */
    public function resolvedColumns(): array
    {
        if ($this->resolved_columns !== null) {
            return $this->resolved_columns;
        }

        $columns = [];

        foreach ($this->columns() as $column) {
            if (is_string($column)) {
                $column = Column::make($column);
            }

            if (! $column instanceof Column) {
                throw new InvalidArgumentException('columns() must return Column definitions or attribute names.');
            }

            $columns[$column->attribute()] = $column;
        }

        return $this->resolved_columns = $columns;
    }

    public function column(string $attribute): ?Column
    {
        return $this->resolvedColumns()[$attribute] ?? null;
    }

    /**
     * The mapping for a file's headings: the one given to map(), or a suggested one
     *
     * @param  array<int, string>  $headings
     */
    public function resolveMapping(array $headings): Mapping
    {
        if ($this->mapping instanceof Mapping) {
            return $this->mapping;
        }

        if ($this->mapping === null) {
            return Mapping::suggest($headings, $this->resolvedColumns());
        }

        return Mapping::fromArray($this->mapping, $headings, $this->resolvedColumns());
    }

    /**
     * Import a file now, in this request. Use queue() for big files.
     *
     * @param  string|null  $report_path  write failed rows to this local file
     */
    public function import(string|UploadedFile $file, Formats|string|null $format = null, ?string $report_path = null): ImportResult
    {
        $reader = $this->reader($file, $format);
        $mapping = $this->resolveMapping($reader->headings())->ensureComplete($this->resolvedColumns());

        $report = $report_path ? new FailureReport($report_path, $this->reportFormat($reader->format()), $reader->headings()) : null;

        return (new ImportRunner($this, $reader, $mapping, $report))->run();
    }

    /**
     * Headings, sample rows, the suggested (or given) mapping and the validation errors of
     * the first rows, without saving anything
     */
    public function preview(string|UploadedFile $file, Formats|string|null $format = null, ?int $rows = null): Preview
    {
        $reader = $this->reader($file, $format);

        return Preview::make($this, $reader, $this->resolveMapping($reader->headings()), $rows);
    }

    /**
     * Store the file and import it on the queue. Returns the Import record that tracks it.
     */
    public function queue(string|UploadedFile $file, ?Model $user = null, ?string $type = null): Import
    {
        return Import::start($this, $file, $user, $type);
    }

    /**
     * Store the file as a draft to map and preview in the imports page before it runs
     */
    public function upload(UploadedFile|string $file, ?Model $user = null, ?string $type = null): Import
    {
        return Import::draft($this, $file, $user, $type);
    }

    public function reader(string|UploadedFile $file, Formats|string|null $format = null): SpreadsheetReader
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file;

        return new SpreadsheetReader($path, Formats::resolve($format, $name), $this->headingRow(), $this->sheet());
    }

    /**
     * Failure reports are written in the uploaded file's format unless `imports.report_format` is set
     */
    public function reportFormat(Formats $source): Formats
    {
        $format = config('imports.report_format');

        return $format ? Formats::resolve($format) : $source;
    }

    /**
     * The template's headings: the labels, with ` *` after required columns
     *
     * @return array<int, string>
     */
    public function templateHeadings(): array
    {
        return array_values(array_map(
            fn (Column $column) => $column->getLabel() . ($column->isRequired() && ! $column->hasDefault() ? ' *' : ''),
            $this->resolvedColumns(),
        ));
    }

    /**
     * Write a template: the headings and, when columns have examples, a sample row
     *
     * @return string the path written to
     */
    public function template(Formats|string $format = Formats::XLSX, ?string $path = null): string
    {
        $format = Formats::resolve($format);
        $path ??= TemporaryFile::path($format, 'template');

        $example = array_values(array_map(fn (Column $column) => $column->getExample(), $this->resolvedColumns()));
        $has_example = array_filter($example, fn (mixed $value) => $value !== null) !== [];

        SpreadsheetWriter::write($path, $format, $this->templateHeadings(), $has_example ? [$example] : []);

        return $path;
    }

    public function templateFilename(Formats|string $format = Formats::XLSX): string
    {
        $name = Str::slug($this->label()) ?: 'import';

        return "{$name}-template." . Formats::resolve($format)->extension();
    }

    public function downloadTemplate(Formats|string $format = Formats::XLSX, ?string $filename = null): BinaryFileResponse
    {
        $format = Formats::resolve($format);

        return response()
            ->download($this->template($format), $filename ?? $this->templateFilename($format), ['Content-Type' => $format->mimeType()])
            ->deleteFileAfterSend();
    }

    /**
     * Warm the lookup caches for a chunk of rows, one query per lookup
     *
     * @param  array<int, array<int, mixed>>  $rows  row number => cells
     */
    public function prepareChunk(array $rows, Mapping $mapping): void
    {
        foreach ($this->resolvedColumns() as $attribute => $column) {
            $lookup = $column->getLookup();
            $index = $mapping->indexOf($attribute);

            if (! $lookup || $index === null) {
                continue;
            }

            $lookup->warm(array_map(fn (array $cells) => $cells[$index] ?? null, $rows));
        }
    }

    /**
     * Read, convert, look up and validate one row
     *
     * Required and type checks run here directly; the validator only runs for columns with
     * rules of their own (and the importer's rules()), which keeps big imports fast.
     *
     * @param  array<int, mixed>  $cells
     * @return array{0: array<string, mixed>, 1: array<string, array<int, string>>} the data and the errors
     */
    public function processRow(array $cells, int $row, Mapping $mapping): array
    {
        $converter = $this->converter ??= new ValueConverter;
        $compiled = $this->compiled();
        $data = [];
        $errors = [];
        $skip = [];

        foreach ($this->resolvedColumns() as $attribute => $column) {
            $index = $mapping->indexOf($attribute);
            $raw = $index === null ? null : $converter->normalize($cells[$index] ?? null, $column->shouldTrim());

            if ($raw === null) {
                if ($index === null && ! $column->hasDefault()) {
                    continue;
                }

                $data[$attribute] = $column->hasDefault() ? $column->getDefault($data) : null;

                continue;
            }

            $value = $converter->convert($column, $raw);

            if ($lookup = $column->getLookup()) {
                $key = $lookup->find($value);

                if ($key === false && ! $lookup->createsMissing()) {
                    $errors[$attribute][] = __('No :attribute matches ":value".', [
                        'attribute' => $compiled['labels'][$attribute],
                        'value' => $this->display($raw),
                    ]);

                    $data[$attribute] = null;
                    $skip[$attribute] = true;

                    continue;
                }

                if ($key === false && $this->dry_run) {
                    $skip[$attribute] = true;
                }

                $value = $key === false ? ($this->dry_run ? $raw : $lookup->create($raw)) : $key;
            }

            if ($transform = $column->getTransform()) {
                $value = $transform($value, $data);
            }

            $data[$attribute] = $value;
        }

        $data = $this->prepareRow($data, $row);

        $rules = [];

        foreach ($this->resolvedColumns() as $attribute => $column) {
            if (isset($skip[$attribute])) {
                continue;
            }

            $value = $data[$attribute] ?? null;

            if (($value === null || $value === '') && $column->isRequired()) {
                $errors[$attribute] = [...($errors[$attribute] ?? []), ...$this->messagesFor($attribute, $value, ['required'], $data)];

                continue;
            }

            if (! $converter->hasType($column, $value)) {
                $errors[$attribute] = [...($errors[$attribute] ?? []), ...$this->messagesFor($attribute, $value, ['nullable', ...$column->typeRules()], $data)];

                continue;
            }

            if (isset($compiled['rules'][$attribute])) {
                $rules[$attribute] = $compiled['rules'][$attribute];
            }
        }

        foreach ($compiled['extra'] as $attribute => $extra) {
            if (! isset($this->resolvedColumns()[$attribute])) {
                $rules[$attribute] = $extra;
            }
        }

        if ($rules !== []) {
            $validator = Validator::make($data, $rules, $this->messages(), $compiled['labels']);

            foreach ($validator->errors()->messages() as $attribute => $messages) {
                $errors[$attribute] = [...($errors[$attribute] ?? []), ...$messages];
            }
        }

        return [$data, $errors];
    }

    /**
     * The validator's messages for one attribute and rules, used when a direct check fails
     *
     * @param  array<int, mixed>  $rules
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    protected function messagesFor(string $attribute, mixed $value, array $rules, array $data): array
    {
        $validator = Validator::make([...$data, $attribute => $value], [$attribute => $rules], $this->messages(), $this->compiled()['labels']);

        return $validator->errors()->get($attribute);
    }

    /**
     * The labels, and the rules left for the validator, worked out once per run and locale
     *
     * @return array{locale: string, labels: array<string, string>, rules: array<string, array<int, mixed>>, extra: array<string, array<int, mixed>>}
     */
    protected function compiled(): array
    {
        if ($this->compiled !== null && $this->compiled['locale'] === app()->getLocale()) {
            return $this->compiled;
        }

        $extra = [];

        foreach ($this->rules() as $attribute => $rules) {
            $extra[$attribute] = is_string($rules) ? explode('|', $rules) : (is_array($rules) ? array_values($rules) : [$rules]);
        }

        $labels = [];
        $rules = [];

        foreach ($this->resolvedColumns() as $attribute => $column) {
            $labels[$attribute] = $column->getLabel();

            $own = [...$column->ownRules(), ...($extra[$attribute] ?? [])];

            if ($own === []) {
                continue;
            }

            $type = array_filter($column->typeRules(), fn (mixed $rule) => is_string($rule));

            $rules[$attribute] = [$column->isRequired() ? 'required' : 'nullable', ...$type, ...$own];
        }

        return $this->compiled = [
            'locale' => app()->getLocale(),
            'labels' => $labels,
            'rules' => $rules,
            'extra' => $extra,
        ];
    }

    /**
     * Run persist() for a valid row inside its own savepoint, so a failing row is rolled back alone
     *
     * @param  array<string, mixed>  $data
     */
    public function persistRow(array $data, int $row): RowOutcomes
    {
        return DB::connection($this->connectionName())->transaction(fn () => $this->persist($data, $row));
    }

    /**
     * A cell as text, for messages and previews
     */
    public function display(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? __('Yes') : __('No'),
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    /**
     * Forget the lookup caches and the columns, e.g. before the importer is serialized
     */
    public function reset(): void
    {
        $this->resolved_columns = null;
        $this->converter = null;
        $this->compiled = null;
    }

    /**
     * Properties worked out during a run, left out when the importer is serialized
     *
     * @return array<int, string>
     */
    protected function transientProperties(): array
    {
        return ['resolved_columns', 'converter', 'compiled'];
    }

    /**
     * @return array<int, string>
     */
    public function __sleep(): array
    {
        return array_values(array_diff(array_keys(get_object_vars($this)), $this->transientProperties()));
    }
}
