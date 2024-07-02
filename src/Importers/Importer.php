<?php

namespace Javaabu\Imports\Importers;

use Illuminate\Http\Response;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Auth\User;
use Javaabu\Imports\Jobs\ImportData;
use Javaabu\Imports\ImportsRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Javaabu\Imports\Exports\ErrorsExport;
use Javaabu\Imports\Exports\ImportTemplate;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Javaabu\Imports\Exceptions\TooManyRowsException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Javaabu\Imports\Exceptions\ImportValidationException;
use Javaabu\Imports\Exceptions\DownloadableImportValidationException;

abstract class Importer implements ToCollection, WithHeadingRow
{
    /** Whether the import should not be queued */
    protected bool $non_queued = false;

    /** Min. number of rows required to trigger a queue */
    protected int $queue_threshold = 100;

    /** The name of the queue to be used */
    protected ?string $queue_name = null;

    /** The name of the queue connection to be used */
    protected ?string $queue_connection = null;

    /* Whether to overwrite duplicates +*/
    protected bool $overwrite_duplicates = false;

    /* Whether to download errors */
    protected bool $download_errors = false;

    /* Get the duplicates */
    protected array $duplicates = [];

    /** The number of rows */
    protected int $num_rows = 0;

    /** The notifiable */
    protected $notifiable;

    /**  The file name */
    protected ?string $file_name;

    /** The importable slug */
    protected ?string $importable;

    /** The importable meta */
    protected array $meta;

    public function __construct(
        bool $overwrite_duplicates = false,
        $error_handler = null,
        $notifiable = null,
        $importable = null,
        ?array $meta = null
    ) {
        $this->setOverwriteDuplicates($overwrite_duplicates);
        $this->setErrorHandler($error_handler);
        $this->setQueueName(config('imports.queue_name'));
        $this->setQueueConnection(config('imports.queue_connection'));
        $this->notifiable = $notifiable;
        $this->importable = $importable;

        $this->setMeta($meta);
    }

    /**
     * Get dummy data for the import template
     */
    abstract public function dummyData(): array;

    /**
     * Get headings the import template
     */
    abstract public function headings(): array;

    /**
     * Get the validation rules for the
     * row
     */
    abstract public function rowValidationRules(array $row): array;

    /**
     * Get the existing model for the given row
     */
    abstract public function getExistingModel(array $row): ?Model;

    /**
     * Save the model for the given row
     */
    abstract public function saveRow(array $row, ?Model $existing_model = null): Model;

    /**
     * Whether the current user can import using
     * this importer
     */
    public static function canImport(?User $user = null): bool
    {
        if ($user) {
            $model_class = ImportsRepository::getModelClass(static::class);

            return $user->can('import', $model_class);
        }

        return false;
    }

    /**
     * Check whether to download errors
     */
    public function shouldDownloadErrors(): bool
    {
        return $this->download_errors;
    }

    /**
     * Check whether to overwrite duplicates
     */
    public function shouldOverwriteDuplicates(): bool
    {
        return $this->overwrite_duplicates;
    }

    /**
     * Set whether to not queue
     */
    public function setNonQueued(bool $non_queued = true): Importer
    {
        $this->non_queued = $non_queued;

        return $this;
    }

    /**
     * Set the meta data
     */
    public function setMeta(?array $meta = null): Importer
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Set whether to overwrite duplicates
     */
    public function setOverwriteDuplicates(bool $overwrite = true): Importer
    {
        $this->overwrite_duplicates = $overwrite;

        return $this;
    }

    /**
     * Set whether to download errors
     */
    public function setShouldDownloadErrors(bool $download = true): Importer
    {
        $this->download_errors = $download;

        return $this;
    }

    /**
     * Get the current user
     */
    public function getUser(): ?User
    {
        if ($this->notifiable instanceof User) {
            return $this->notifiable;
        }

        return null;
    }

    /**
     * Get the meta data
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Get the queue name
     */
    public function getQueueName(): ?string
    {
        return $this->queue_name;
    }

    /**
     * Get the queue connection
     */
    public function getQueueConnection(): ?string
    {
        return $this->queue_connection;
    }

    public function setQueueName(?string $queue_name): Importer
    {
        $this->queue_name = $queue_name;

        return $this;
    }

    public function setQueueConnection(?string $queue_connection): Importer
    {
        $this->queue_connection = $queue_connection;

        return $this;
    }

    /**
     * Set the queue threshold
     */
    public function setQueueThreshold(int $queue_threshold): Importer
    {
        $this->queue_threshold = $queue_threshold;

        return $this;
    }

    /**
     * Get the queue threshold
     */
    public function getQueueThreshold(): int
    {
        return $this->queue_threshold;
    }

    public function getFileName(): ?string
    {
        return $this->file_name;
    }

    public function setFileName(string $file_name): Importer
    {
        $this->file_name = $file_name;

        return $this;
    }

    /**
     * Check if should queue the import
     */
    public function shouldQueue(): bool
    {
        return $this->count() >= $this->getQueueThreshold() &&
                (! $this->non_queued);
    }

    /**
     * Set the error handler
     */
    public function setErrorHandler(?string $handler): void
    {
        if ($handler && (! in_array($handler, ['display', 'download']))) {
            throw new InvalidArgumentException('Invalid import error handler value.');
        }

        $this->setShouldDownloadErrors($handler == 'download');
    }

    /**
     * Get the import template
     */
    public function importTemplate(): ImportTemplate
    {
        return new ImportTemplate(
            $this->headings(),
            [$this->dummyData()]
        );
    }

    /**
     * Get the import template file name
     */
    public function importTemplateFileName(): string
    {
        $class_name = class_basename(get_class($this));

        return $class_name.'Template.xlsx';
    }

    /**
     * Download import template
     */
    public function downloadImportTemplate(): Response|BinaryFileResponse
    {
        return $this->importTemplate()
            ->download($this->importTemplateFileName());
    }

    /**
     * Get the errors export file name
     */
    public function errorsExportFileName(): string
    {
        $class_name = class_basename(get_class($this));

        return $class_name.'Errors.xlsx';
    }

    /**
     * Get the errors export template
     */
    public function errorsExport(array $valid_rows, array $invalid_rows): ErrorsExport
    {
        return new ErrorsExport(
            $valid_rows,
            $invalid_rows,
            $this->headings()
        );
    }

    /**
     * Validate and save the collection
     */
    public function collection(Collection $collection): void
    {
        $this->validate($collection);

        if ($this->shouldQueue()) {
            $this->dispatchImportJob($collection);

            throw new TooManyRowsException($this->count(), $this->getFileName());
        }

        $this->save($collection);
    }

    /**
     * Dispatch import job
     */
    protected function dispatchImportJob(Collection $data): void
    {
        $job = ImportData::dispatch(
            $data,
            get_class($this),
            $this->getFileName(),
            $this->shouldOverwriteDuplicates(),
            $this->notifiable,
            $this->importable,
            $this->getMeta()
        );

        if ($connection = $this->getQueueConnection()) {
            $job->onConnection($connection);
        }

        if ($queue = $this->getQueueName()) {
            $job->onQueue($queue);
        }
    }

    /**
     * Get the validator for the given row
     */
    protected function normalizeRow(Collection $row): array
    {
        return $this->trimStrings($row->toArray());
    }

    /**
     * Get the validator for the given row
     */
    protected function rowValidator(array $row): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make(
            $row,
            $this->rowValidationRules($row),
            $this->rowValidationMessages($row),
            $this->rowValidationCustomAttributes($row)
        );
    }

    /**
     * Get the validation messages for the
     * row
     */
    public function rowValidationMessages(array $row): array
    {
        return [];
    }

    /**
     * Get the validation custom attributes
     * for the row
     */
    public function rowValidationCustomAttributes(array $row): array
    {
        return [];
    }

    /**
     * Get which fields not to trim
     */
    public function getTrimExcept(): array
    {
        return property_exists($this, 'trim_except') ? $this->trim_except : [];
    }

    /**
     * Get the model class used for this importer
     */
    public function getModelClass(): string
    {
        return ImportsRepository::getModelClass(get_class($this));
    }

    /**
     * Check if should trim column
     */
    public function shouldTrim($field): bool
    {
        return ! in_array($field, $this->getTrimExcept());
    }

    /**
     * Trim the input strings
     */
    public function trimStrings(array $row): array
    {
        foreach ($row as $field => $data) {
            if ($this->shouldTrim($field)) {
                $row[$field] = trim($data);
            }
        }

        return $row;
    }

    /**
     * Get the row count
     */
    public function count(): int
    {
        return $this->num_rows;
    }

    /**
     * Get the duplicates
     */
    public function duplicates(): array
    {
        return $this->duplicates;
    }

    /**
     * Get the number of duplicates
     */
    public function numDuplicates(): int
    {
        return count($this->duplicates);
    }

    /**
     * Validate the data for the give collection
     * and keep track of the errors
     */
    protected function validate(Collection $rows): void
    {
        $this->num_rows = 0;

        $errors = [];
        $valid_rows = [];
        $invalid_rows = [];

        foreach ($rows as $row) {
            $this->num_rows++;
            $data = $this->normalizeRow($row);

            $validator = $this->rowValidator($data);

            if ($validator->fails()) {
                if ($this->shouldDownloadErrors()) {
                    $invalid_rows[] = $validator->getData();
                }

                // id is excel row id
                $errors[$this->num_rows + 1] = $validator->errors()->all();
            } else {
                if ($this->shouldDownloadErrors()) {
                    $valid_rows[] = $validator->getData();
                }
            }
        }

        if ($errors) {
            if ($this->shouldDownloadErrors()) {
                $errors_export = $this->errorsExport($valid_rows, $invalid_rows);

                throw new DownloadableImportValidationException(
                    $errors_export,
                    $this->errorsExportFileName(),
                    $errors
                );
            }

            throw new ImportValidationException($errors);
        }
    }

    /**
     * Save the collection data to the db
     */
    public function save(Collection $rows): void
    {
        $row_id = 0;
        $this->duplicates = [];

        foreach ($rows as $row) {
            $row_id++;
            $data = $this->normalizeRow($row);

            $model = $this->getExistingModel($data);

            if ($model) {
                $this->duplicates[] = $row_id + 1; // row id is excel row id

                if (! $this->shouldOverwriteDuplicates()) {
                    continue;
                }
            }

            $this->saveRow($data, $model);
        }
    }
}
