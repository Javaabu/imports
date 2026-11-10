<?php

namespace Javaabu\Imports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Events\ImportCompleted;
use Javaabu\Imports\Events\ImportFailed;
use Javaabu\Imports\Events\ImportProgressed;
use Javaabu\Imports\Events\ImportStarted;
use Javaabu\Imports\Exceptions\ImportCancelledException;
use Javaabu\Imports\Mapping\Mapping;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Processing\FailureReport;
use Javaabu\Imports\Processing\ImportResult;
use Javaabu\Imports\Processing\ImportRunner;
use Javaabu\Imports\Support\TemporaryFile;
use Throwable;

/**
 * Runs a queued import, saving progress after every chunk
 *
 * The uploaded file is copied from its disk to a local temporary file and streamed. The
 * rows are counted first so progress can be shown. Failed rows are written to a report
 * that's stored next to the upload. A cancelled import stops before its next chunk; rows
 * already saved stay saved. One try: running an import twice could save rows twice, so
 * a failed import is retried from the imports page instead.
 */
class ProcessImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public bool $deleteWhenMissingModels = true;

    /** The locale the import was queued in, used for labels and error messages */
    public ?string $locale;

    public function __construct(
        public Import $import,
    ) {
        $this->timeout = (int) config('imports.queue.timeout', 3600);
        $this->locale = app()->getLocale();
    }

    public function handle(): void
    {
        $previous_locale = app()->getLocale();

        if ($this->locale) {
            app()->setLocale($this->locale);
        }

        try {
            $this->process();
        } finally {
            app()->setLocale($previous_locale);
        }
    }

    protected function process(): void
    {
        $import = $this->import;

        $import->refresh();

        if ($import->status !== ImportStatuses::PENDING) {
            return;
        }

        $importer = $import->importer();
        $local_path = $import->localCopy();
        $report_format = $importer->reportFormat($import->format);
        $report_path = TemporaryFile::path($report_format, 'import-failures');

        try {
            $reader = $importer->reader($local_path, $import->format);

            $import->markProcessing($reader->count());

            event(new ImportStarted($import));

            $mapping = new Mapping($import->mapping ?? []);
            $mapping->ensureComplete($importer->resolvedColumns());

            $runner = new ImportRunner(
                $importer,
                $reader,
                $mapping,
                new FailureReport($report_path, $report_format, $reader->headings()),
                fn (ImportResult $result) => $this->progressed($result),
                fn () => $import->wasCancelled(),
            );

            try {
                $result = $runner->run();
            } catch (ImportCancelledException) {
                $import->recordProgress($runner->result());

                return;
            }

            $stored_report = null;

            if ($result->report_path) {
                $stored_report = $this->reportStoragePath($report_format->extension());
                TemporaryFile::copyToDisk($result->report_path, $stored_report, $import->disk);
            }

            $import->markCompleted($result, $stored_report);
        } finally {
            TemporaryFile::delete($local_path);
            TemporaryFile::delete($report_path);
        }

        event(new ImportCompleted($import));

        $this->notifyUser('completed');
    }

    public function failed(?Throwable $exception): void
    {
        $import = $this->import->fresh();

        if (! $import || $import->status->isFinished()) {
            return;
        }

        $this->import = $import;

        $import->markFailed($exception ?? __('The import failed.'));

        event(new ImportFailed($import));

        $this->notifyUser('failed');
    }

    protected function progressed(ImportResult $result): void
    {
        $this->import->recordProgress($result);

        event(new ImportProgressed($this->import));
    }

    /**
     * `imports/{uuid}/failures.{extension}`, next to the upload
     */
    protected function reportStoragePath(string $extension): string
    {
        return Import::directory() . "/{$this->import->uuid}/failures.{$extension}";
    }

    protected function notifyUser(string $type): void
    {
        if (! config('imports.notifications.enabled', true)) {
            return;
        }

        $notification = config("imports.notifications.{$type}");

        if (! $notification) {
            return;
        }

        $user = $this->import->user;

        if (! $user || ! method_exists($user, 'notify')) {
            return;
        }

        $user->notify(new $notification($this->import));
    }
}
