<?php

namespace Javaabu\Imports\Importers;

use App\Helpers\User\User;
use InvalidArgumentException;
use App\Imports\Jobs\ImportData;
use App\Imports\ImportsRepository;
use Illuminate\Support\Collection;
use App\Imports\Exports\ErrorsExport;
use App\Imports\Exports\ImportTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Imports\Exceptions\TooManyRowsException;
use App\Imports\Exceptions\ImportValidationException;
use App\Imports\Exceptions\DownloadableImportValidationException;

abstract class Importer implements ToCollection, WithHeadingRow
{
    /**
     * Whether the import should not be queued
     *
     * @var bool
     */
    protected $non_queued = false;

    /**
     * Min. number of rows required to trigger a queue
     *
     * @var int
     */
    protected $queue_threshold = 100;

    /**
     * The name of the queue to be used
     *
     * @var string
     */
    protected $queue_name = null;

    /**
     * The name of the queue connection to be used
     *
     * @var string
     */
    protected $queue_connection = null;

    /**
     * Whether to overwrite duplicates
     *
     * @var bool
     */
    protected $overwrite_duplicates = false;

    /**
     * Whether to download errors
     *
     * @var bool
     */
    protected $download_errors = false;

    /**
     * Get the duplicates
     *
     * @var array
     */
    protected $duplicates = [];

    /**
     * The number of rows
     *
     * @var int
     */
    protected $num_rows = 0;

    /**
     * The notifiable
     *
     * @var Notifiable
     */
    protected $notifiable;

    /**
     * The file name
     *
     * @var string
     */
    protected $file_name;

    /**
     * The importable slug
     *
     * @var string
     */
    protected $importable;

    /**
     * The importable meta
     *
     * @var array
     */
    protected $meta;

    /**
     * Constructor
     *
     * @param bool $overwrite_duplicates
     * @param null $error_handler
     * @param Notifiable|null $notifiable
     * @param null $importable
     * @param array $meta
     */
    public function __construct(
        $overwrite_duplicates = false,
        $error_handler = null,
        $notifiable = null,
        $importable = null,
        $meta = null
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
     *
     * @return array
     */
    abstract public function dummyData(): array;

    /**
     * Get headings the import template
     *
     * @return array
     */
    abstract public function headings(): array;

    /**
     * Get the validation rules for the
     * row
     *
     * @param array $row
     * @return array
     */
    abstract public function rowValidationRules(array $row): array;

    /**
     * Get the existing model for the given row
     *
     * @param array $row
     * @return Model
     */
    abstract public function getExistingModel(array $row): ?Model;

    /**
     * Save the model for the given row
     *
     * @param array $row
     * @param Model|null $existing_model
     * @return Model
     */
    abstract public function saveRow(array $row, Model $existing_model = null): Model;

    /**
     * Whether the current user can import using
     * this importer
     *
     * @param ?User $user
     * @return bool
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
     *
     * @return bool
     */
    public function shouldDownloadErrors()
    {
        return $this->download_errors;
    }

    /**
     * Check whether to overwrite duplicates
     *
     * @return bool
     */
    public function shouldOverwriteDuplicates()
    {
        return $this->overwrite_duplicates;
    }

    /**
     * Set whether to not queue
     *
     * @param bool $non_queued
     * @return Importer
     */
    public function setNonQueued($non_queued = true): Importer
    {
        $this->non_queued = $non_queued;

        return $this;
    }

    /**
     * Set the meta data
     *
     * @param array $meta
     * @return Importer
     */
    public function setMeta($meta = null): Importer
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Set whether to overwrite duplicates
     *
     * @param bool $overwrite
     * @return Importer
     */
    public function setOverwriteDuplicates($overwrite = true): Importer
    {
        $this->overwrite_duplicates = $overwrite;

        return $this;
    }

    /**
     * Set whether to download errors
     *
     * @param bool $download
     * @return Importer
     */
    public function setShouldDownloadErrors($download = true): Importer
    {
        $this->download_errors = $download;

        return $this;
    }

    /**
     * Get the current user
     *
     * @return User
     */
    public function getUser()
    {
        if ($this->notifiable instanceof User) {
            return $this->notifiable;
        }

        return null;
    }

    /**
     * Get the meta data
     *
     * @return array
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * Get the queue name
     *
     * @return string
     */
    public function getQueueName(): ?string
    {
        return $this->queue_name;
    }

    /**
     * Get the queue connection
     *
     * @return string
     */
    public function getQueueConnection(): ?string
    {
        return $this->queue_connection;
    }

    /**
     * @param string $queue_name
     * @return Importer
     */
    public function setQueueName($queue_name): Importer
    {
        $this->queue_name = $queue_name;

        return $this;
    }

    /**
     * @param string $queue_connection
     * @return Importer
     */
    public function setQueueConnection($queue_connection): Importer
    {
        $this->queue_connection = $queue_connection;

        return $this;
    }

    /**
     * Set the queue threshold
     *
     * @param int $queue_threshold
     * @return Importer
     */
    public function setQueueThreshold(int $queue_threshold): Importer
    {
        $this->queue_threshold = $queue_threshold;

        return $this;
    }

    /**
     * Get the queue threshold
     *
     * @return int
     */
    public function getQueueThreshold(): int
    {
        return $this->queue_threshold;
    }

    /**
     * @return string
     */
    public function getFileName(): ?string
    {
        return $this->file_name;
    }

    /**
     * @param string $file_name
     * @return Importer
     */
    public function setFileName(string $file_name): Importer
    {
        $this->file_name = $file_name;

        return $this;
    }

    /**
     * Check if should queue the import
     *
     * @return bool
     */
    public function shouldQueue(): bool
    {
        return $this->count() >= $this->getQueueThreshold() &&
                (! $this->non_queued);
    }

    /**
     * Set the error handler
     *
     * @param string $handler download|display
     */
    public function setErrorHandler($handler)
    {
        if ($handler && (! in_array($handler, ['display', 'download']))) {
            throw new InvalidArgumentException('Invalid import error handler value.');
        }

        $this->setShouldDownloadErrors($handler == 'download');
    }

    /**
     * Get the import template
     *
     * @return Exportable
     */
    public function importTemplate()
    {
        return new ImportTemplate(
            $this->headings(),
            [$this->dummyData()]
        );
    }

    /**
     * Get the import template file name
     *
     * @return string
     */
    public function importTemplateFileName()
    {
        $class_name = class_basename(get_class($this));

        return $class_name . 'Template.xlsx';
    }

    /**
     * Download import template
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadImportTemplate()
    {
        return $this->importTemplate()
                    ->download($this->importTemplateFileName());
    }

    /**
     * Get the errors export file name
     *
     * @return string
     */
    public function errorsExportFileName()
    {
        $class_name = class_basename(get_class($this));

        return $class_name . 'Errors.xlsx';
    }

    /**
     * Get the errors export template
     *
     * @param array $valid_rows
     * @param array $invalid_rows
     * @return Exportable
     */
    public function errorsExport(array $valid_rows, array $invalid_rows)
    {
        return new ErrorsExport(
            $valid_rows,
            $invalid_rows,
            $this->headings()
        );
    }


    /**
     * Validate and save the collection
     *
     * @param Collection $collection
     */
    public function collection(Collection $collection)
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
     *
     * @param Collection $data
     */
    protected function dispatchImportJob(Collection $data)
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
     *
     * @param Collection $row
     * @return array
     */
    protected function normalizeRow(Collection $row): array
    {
        return $this->trimStrings($row->toArray());
    }

    /**
     * Get the validator for the given row
     *
     * @param array $row
     * @return \Illuminate\Contracts\Validation\Validator
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
     *
     * @param array $row
     * @return array
     */
    public function rowValidationMessages(array $row): array
    {
        return [];
    }

    /**
     * Get the validation custom attributes
     * for the row
     *
     * @param array $row
     * @return array
     */
    public function rowValidationCustomAttributes(array $row): array
    {
        return [];
    }

    /**
     * Get which fields not to trim
     *
     * @return array
     */
    public function getTrimExcept(): array
    {
        return property_exists($this, 'trim_except') ? $this->trim_except : [];
    }

    /**
     * Get the model class used for this importer
     *
     * @return string
     */
    public function getModelClass(): string
    {
        return ImportsRepository::getModelClass(get_class($this));
    }

    /**
     * Check if should trim column
     *
     * @param $field
     * @return bool
     */
    public function shouldTrim($field): bool
    {
        return ! in_array($field, $this->getTrimExcept());
    }

    /**
     * Trim the input strings
     *
     * @param array $row
     * @return array
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
     *
     * @return int
     */
    public function count(): int
    {
        return $this->num_rows;
    }

    /**
     * Get the duplicates
     *
     * @return array
     */
    public function duplicates(): array
    {
        return $this->duplicates;
    }

    /**
     * Get the number of duplicates
     *
     * @return int
     */
    public function numDuplicates(): int
    {
        return count($this->duplicates);
    }

    /**
     * Validate the data for the give collection
     * and keep track of the errors
     *
     * @param $rows
     */
    protected function validate(Collection $rows)
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
     *
     * @param $rows
     */
    public function save(Collection $rows)
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
