<?php

namespace Javaabu\Imports\Processing;

use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Exceptions\RowFailedException;
use Javaabu\Imports\Importer;
use Javaabu\Imports\Mapping\Mapping;
use Javaabu\Imports\Readers\SpreadsheetReader;
use JsonSerializable;

/**
 * What an import would do, before anything is written: the file's headings with sample
 * values, the fields and which heading fills each, required fields that aren't mapped,
 * and the first rows validated with their errors and outcome (new, update or skip)
 */
class Preview implements JsonSerializable
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string>>  $samples  heading index => sample values
     * @param  array<int, array{row: int, values: array<string, string>, errors: array<string, array<int, string>>, outcome: string|null, outcome_label: string|null}>  $rows
     * @param  array<int, string>  $missing  required attributes that aren't mapped
     */
    public function __construct(
        protected Importer $importer,
        public readonly array $headings,
        public readonly array $samples,
        public readonly Mapping $mapping,
        public readonly array $rows,
        public readonly array $missing,
        public readonly string $format,
    ) {}

    /**
     * @param  int|null  $limit  how many rows to validate (`imports.preview.rows`)
     */
    public static function make(Importer $importer, SpreadsheetReader $reader, Mapping $mapping, ?int $limit = null): static
    {
        $limit ??= max((int) config('imports.preview.rows', 100), 1);
        $sample_count = max((int) config('imports.preview.samples', 3), 0);
        $headings = $reader->headings();
        $columns = $importer->resolvedColumns();

        $chunk = iterator_to_array($reader->rows($limit), true);

        $samples = [];

        foreach (array_keys($headings) as $index) {
            $samples[$index] = [];

            foreach (array_slice($chunk, 0, $sample_count, true) as $cells) {
                $samples[$index][] = $importer->display($cells[$index] ?? null);
            }
        }

        $missing = $mapping->missingRequired($columns);
        $rows = [];

        if ($missing === [] && ! $mapping->isEmpty()) {
            $was_dry_run = $importer->isDryRun();
            $importer->dryRun();

            try {
                $rows = static::validateRows($importer, $chunk, $mapping, $columns);
            } finally {
                $importer->dryRun($was_dry_run);
            }
        }

        return new static($importer, $headings, $samples, $mapping, $rows, $missing, $reader->format()->value);
    }

    /**
     * @param  array<int, array<int, mixed>>  $chunk
     * @param  array<string, Column>  $columns
     * @return array<int, array<string, mixed>>
     */
    protected static function validateRows(Importer $importer, array $chunk, Mapping $mapping, array $columns): array
    {
        $importer->prepareChunk($chunk, $mapping);

        $rows = [];
        $valid = [];

        foreach ($chunk as $row => $cells) {
            try {
                [$data, $errors] = $importer->processRow($cells, $row, $mapping);
            } catch (RowFailedException $exception) {
                [$data, $errors] = [[], $exception->errors()];
            }

            $values = [];

            foreach (array_keys($columns) as $attribute) {
                $index = $mapping->indexOf($attribute);

                if ($index !== null) {
                    $values[$attribute] = $importer->display($cells[$index] ?? null);
                }
            }

            $rows[$row] = ['row' => $row, 'values' => $values, 'errors' => $errors, 'outcome' => null, 'outcome_label' => null];

            if (! $errors) {
                $valid[$row] = $data;
            }
        }

        if ($valid !== []) {
            $importer->beforePersist($valid);

            foreach ($valid as $row => $data) {
                try {
                    $outcome = $importer->previewOutcome($data, $row);
                } catch (RowFailedException $exception) {
                    $rows[$row]['errors'] = $exception->errors();

                    continue;
                }

                $rows[$row]['outcome'] = $outcome->value;
                $rows[$row]['outcome_label'] = $outcome->label();
            }
        }

        return array_values($rows);
    }

    public function isReady(): bool
    {
        return $this->missing === [] && ! $this->mapping->isEmpty();
    }

    public function hasErrors(): bool
    {
        return $this->invalidCount() > 0;
    }

    public function checkedCount(): int
    {
        return count($this->rows);
    }

    public function invalidCount(): int
    {
        return count(array_filter($this->rows, fn (array $row) => $row['errors'] !== []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function invalidRows(): array
    {
        return array_values(array_filter($this->rows, fn (array $row) => $row['errors'] !== []));
    }

    /**
     * @return array<string, int>
     */
    public function outcomeCounts(): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($this->rows as $row) {
            if ($row['outcome'] !== null) {
                $counts[$row['outcome']]++;
            }
        }

        return $counts;
    }

    /**
     * The fields, with the heading index each one is read from
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $columns = [];

        foreach ($this->importer->resolvedColumns() as $attribute => $column) {
            $columns[] = [
                ...$column->toArray(),
                'required' => $column->isRequired() && ! $column->hasDefault(),
                'heading' => $this->mapping->indexOf($attribute),
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $headings = [];

        foreach ($this->headings as $index => $heading) {
            $headings[] = [
                'index' => $index,
                'heading' => $heading,
                'samples' => $this->samples[$index] ?? [],
                'attribute' => $this->mapping->toArray()[$index] ?? null,
            ];
        }

        return [
            'format' => $this->format,
            'headings' => $headings,
            'columns' => $this->columns(),
            'mapping' => (object) $this->mapping->toArray(),
            'missing' => $this->missing,
            'ready' => $this->isReady(),
            'rows' => $this->rows,
            'counts' => [
                'checked' => $this->checkedCount(),
                'invalid' => $this->invalidCount(),
                ...$this->outcomeCounts(),
            ],
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
