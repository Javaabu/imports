<?php

namespace App\Imports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use App\Imports\Importers\Importer;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Notifiable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Imports\Exceptions\ImportValidationException;
use App\Imports\Notifications\ImportFailedNotification;
use App\Imports\Notifications\ImportResultNotification;
use App\Imports\Notifications\ImportValidationErrorsNotification;

class ImportData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 0; // no timeout

    /**
     * @var Notifiable
     */
    protected $notifiable;

    /**
     * @var Collection
     */
    protected $data;

    /**
     * Whether to overwrite duplicates
     *
     * @var bool
     */
    protected $overwrite_duplicates;

    /**
     * The importer class
     *
     * @var string
     */
    protected $importer_class;

    /**
     * The file name
     *
     * @var string
     */
    protected $file_name;

    /**
     * Meta data
     *
     * @var array
     */
    protected $meta;

    /**
     * The importable type
     *
     * @var string
     */
    protected $importable;

    /**
     * Create a new job instance.
     *
     * @param Collection $data
     * @param $importer_class
     * @param $file_name
     * @param bool $overwrite_duplicates
     * @param Notifiable|null $notifiable
     * @param null $meta
     * @param null $importable
     */
    public function __construct(
        Collection $data,
        $importer_class,
        $file_name,
        $overwrite_duplicates = false,
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

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
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

    /**
     * Get the importer
     *
     * @return Importer
     */
    protected function getImporter(): Importer
    {
        $importer = new $this->importer_class($this->overwrite_duplicates, null, $this->notifiable, $this->importable, $this->meta);

        return $importer;
    }
}
