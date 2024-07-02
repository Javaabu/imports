<?php

namespace Javaabu\Imports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Javaabu\Imports\Importers\Importer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Javaabu\Imports\Exceptions\ImportValidationException;
use Javaabu\Imports\Notifications\ImportFailedNotification;
use Javaabu\Imports\Notifications\ImportResultNotification;
use Javaabu\Imports\Notifications\ImportValidationErrorsNotification;

class ImportData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 0; // no timeout

    protected $notifiable;

    protected Collection $data;

    /**
     * Whether to overwrite duplicates
     */
    protected bool $overwrite_duplicates;

    /**
     * The importer class
     */
    protected string $importer_class;

    /**
     * The file name
     */
    protected string $file_name;

    /**
     * Meta data
     */
    protected array $meta;

    /**
     * The importable type
     */
    protected string $importable;

    public function __construct(
        Collection $data,
        $importer_class,
        $file_name,
        bool $overwrite_duplicates = false,
        $notifiable = null,
        $importable = null,
        $meta = null
    ) {
        $this->data = $data;
        $this->overwrite_duplicates = $overwrite_duplicates;
        $this->notifiable = $notifiable;
        $this->importer_class = $importer_class;
        $this->file_name = $file_name;
        $this->importable = $importable;
        $this->meta = $meta;
    }

    public function handle(): void
    {
        $importer = $this->getImporter()
            ->setNonQueued();

        $model_type = $this->importable;

        try {
            $importer->collection($this->data);

            $import_result = [
                'num_imported' => $importer->count(),
                'num_duplicates' => $importer->numDuplicates(),
                'duplicates' => $importer->duplicates(),
                'overwrite' => $this->overwrite_duplicates,
            ];

            if ($this->notifiable) {
                $this->notifiable->notify(new ImportResultNotification(
                    $import_result,
                    $this->file_name,
                    $model_type
                ));
            }
        } catch (ImportValidationException $e) {
            Log::error("ImportJob Validation Error: file_name:$this->file_name model_type;$model_type message:".$e->getMessage());

            if ($this->notifiable) {
                $this->notifiable->notify(new ImportValidationErrorsNotification(
                    $e->getErrors(),
                    $this->file_name,
                    $model_type
                ));
            }
        } catch (\Exception $e) {
            Log::error("ImportJob Failed: file_name:$this->file_name model_type;$model_type message:".$e->getMessage());

            if ($this->notifiable) {
                $this->notifiable->notify(new ImportFailedNotification(
                    $e->getMessage(),
                    $this->file_name,
                    $model_type
                ));
            }
        }

    }

    protected function getImporter(): Importer
    {
        $importer = new $this->importer_class($this->overwrite_duplicates, null, $this->notifiable, $this->importable, $this->meta);
        return $importer;
    }
}
