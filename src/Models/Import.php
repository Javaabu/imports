<?php

namespace Javaabu\Imports\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Javaabu\Imports\Enums\Duplicates;
use Javaabu\Imports\Enums\Formats;
use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Events\ImportCancelled;
use Javaabu\Imports\Importer;
use Javaabu\Imports\Imports;
use Javaabu\Imports\Jobs\ProcessImport;
use Javaabu\Imports\Mapping\Mapping;
use Javaabu\Imports\Processing\ImportResult;
use Javaabu\Imports\Processing\Preview;
use Javaabu\Imports\Processing\RowFailure;
use Javaabu\Imports\Support\Broadcasting;
use Javaabu\Imports\Support\TemporaryFile;
use OpenSpout\Common\Exception\OpenSpoutException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * An uploaded file and its import: who uploaded it, how its columns map, how far the import
 * got, what it did, and the report of the rows that failed
 *
 * @property int $id
 * @property string $uuid
 * @property ?string $user_type
 * @property mixed $user_id
 * @property ?string $type
 * @property string $importer_class
 * @property string $importer
 * @property string $name
 * @property ImportStatuses $status
 * @property ?string $disk
 * @property ?string $path
 * @property string $filename
 * @property Formats $format
 * @property ?int $size
 * @property ?array<int, string> $headings
 * @property ?array<int, string> $mapping
 * @property Duplicates $duplicates
 * @property bool $dry_run
 * @property ?int $total_rows
 * @property int $processed_rows
 * @property int $created_rows
 * @property int $updated_rows
 * @property int $skipped_rows
 * @property int $failed_rows
 * @property ?array<int, array<string, mixed>> $failures
 * @property ?string $report_path
 * @property ?string $error
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?Carbon $failed_at
 * @property ?Carbon $cancelled_at
 * @property ?Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Model $user
 */
class Import extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['importer'];

    protected $attributes = [
        'processed_rows' => 0,
        'created_rows' => 0,
        'updated_rows' => 0,
        'skipped_rows' => 0,
        'failed_rows' => 0,
        'dry_run' => false,
        'duplicates' => 'skip',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatuses::class,
            'format' => Formats::class,
            'duplicates' => Duplicates::class,
            'dry_run' => 'boolean',
            'headings' => 'array',
            'mapping' => 'array',
            'failures' => 'array',
            'size' => 'integer',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'created_rows' => 'integer',
            'updated_rows' => 'integer',
            'skipped_rows' => 'integer',
            'failed_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Import $import) {
            $import->uuid ??= (string) Str::uuid();
            $import->status ??= ImportStatuses::DRAFT;
        });

        static::deleting(function (Import $import) {
            $import->deleteFiles();
        });
    }

    public function getTable(): string
    {
        return config('imports.table', parent::getTable());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Store an uploaded file as a draft import, with its headings and a suggested mapping
     *
     * @throws ValidationException (on `file`) when the file can't be read or has no headings
     */
    public static function draft(Importer $importer, UploadedFile|string $file, ?Model $user = null, ?string $type = null): static
    {
        $filename = static::safeFilename($file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file));
        $local = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $format = Formats::resolve(null, $filename);
        $reader = $importer->reader($local, $format);

        try {
            $headings = $reader->headings();
        } catch (OpenSpoutException) {
            throw ValidationException::withMessages(['file' => __('The file could not be read. Check that it is a valid :format file.', ['format' => $format->label()])]);
        }

        if (array_filter($headings, fn (string $heading) => $heading !== '') === []) {
            throw ValidationException::withMessages(['file' => __('The file has no headings in row :row.', ['row' => $importer->headingRow()])]);
        }

        $import = Imports::newImportModel();
        $import->uuid = (string) Str::uuid();
        $import->disk = config('imports.disk');
        $import->path = static::directory() . "/{$import->uuid}/{$filename}";

        TemporaryFile::copyToDisk($local, $import->path, $import->disk);

        $import->forceFill([
            'type' => $type ?? Imports::typeOf($importer),
            'importer_class' => $importer::class,
            'importer' => serialize($importer),
            'name' => $importer->label(),
            'status' => ImportStatuses::DRAFT,
            'filename' => $filename,
            'format' => $format,
            'size' => filesize($local) ?: null,
            'headings' => $headings,
            'mapping' => $importer->resolveMapping($headings)->toArray(),
            'duplicates' => $importer->getDuplicates(),
            'dry_run' => $importer->isDryRun(),
            'expires_at' => now()->addHours(max((int) config('imports.draft_expire_after_hours', 24), 1)),
        ]);

        if ($user) {
            $import->user()->associate($user);
        }

        $import->save();

        return $import;
    }

    /**
     * Store a file and queue its import straight away, with the importer's (or the suggested) mapping
     */
    public static function start(Importer $importer, UploadedFile|string $file, ?Model $user = null, ?string $type = null): static
    {
        return static::draft($importer, $file, $user, $type)->run();
    }

    /**
     * Imports started by the user
     */
    public function scopeOwnedBy(Builder $query, Model $user): void
    {
        $query->where('user_type', $user->getMorphClass())
            ->where('user_id', $user->getKey());
    }

    /**
     * Imports whose files have expired
     */
    public function scopeExpired(Builder $query, ?DateTimeInterface $now = null): void
    {
        $query->where('expires_at', '<=', $now ?? now());
    }

    public function scopeStatus(Builder $query, ImportStatuses|string $status): void
    {
        $query->where('status', $status instanceof ImportStatuses ? $status->value : $status);
    }

    public function isOwnedBy(?Model $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->user_type === $user->getMorphClass()
            && (string) $this->user_id === (string) $user->getKey();
    }

    /**
     * The importer, with this import's mapping, duplicates and dry run applied
     */
    public function importer(): Importer
    {
        $importer = unserialize($this->importer);

        if (! $importer instanceof Importer) {
            throw new RuntimeException("The importer of import {$this->uuid} can't be restored.");
        }

        return $importer
            ->map($this->mapping === null ? null : new Mapping($this->mapping))
            ->duplicates($this->duplicates)
            ->dryRun($this->dry_run);
    }

    /**
     * Change the mapping and options of a draft. The mapping can use heading indexes or text.
     * With $require_complete, a mapping that leaves required columns out isn't saved.
     *
     * @param  array<int|string, string|null>|null  $mapping
     */
    public function configure(?array $mapping = null, Duplicates|string|null $duplicates = null, ?bool $dry_run = null, bool $require_complete = false): static
    {
        $importer = $this->importer();

        if ($mapping !== null) {
            $resolved = Mapping::fromArray($mapping, $this->headings ?? [], $importer->resolvedColumns());

            if ($require_complete) {
                $resolved->ensureComplete($importer->resolvedColumns());
            }

            $this->mapping = $resolved->toArray();
        }

        if ($duplicates !== null) {
            $this->duplicates = Duplicates::resolve($duplicates);
        }

        if ($dry_run !== null) {
            $this->dry_run = $dry_run;
        }

        $this->save();

        return $this;
    }

    /**
     * Preview the file with the current mapping
     */
    public function preview(?int $rows = null): Preview
    {
        $local = $this->localCopy();

        try {
            return $this->importer()->preview($local, $this->format, $rows);
        } finally {
            TemporaryFile::delete($local);
        }
    }

    /**
     * Queue the import. Throws InvalidMappingException when required columns aren't mapped.
     */
    public function run(): static
    {
        if (! in_array($this->status, [ImportStatuses::DRAFT, ImportStatuses::FAILED, ImportStatuses::CANCELLED], true)) {
            throw new RuntimeException(__('This import has already started.'));
        }

        $importer = $this->importer();
        (new Mapping($this->mapping ?? []))->ensureComplete($importer->resolvedColumns());

        $this->forceFill([
            'status' => ImportStatuses::PENDING,
            'total_rows' => null,
            'processed_rows' => 0,
            'created_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'failures' => null,
            'error' => null,
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
            'expires_at' => null,
        ])->save();

        $this->deleteReport();

        $job_class = config('imports.queue.job', ProcessImport::class);
        $job = new $job_class($this);

        dispatch($job)
            ->onConnection(config('imports.queue.connection'))
            ->onQueue(config('imports.queue.queue'));

        return $this;
    }

    /**
     * Progress from 0 to 100, or null while the number of rows is unknown
     */
    public function progress(): ?int
    {
        if ($this->status === ImportStatuses::COMPLETED) {
            return 100;
        }

        if (! $this->total_rows) {
            return $this->total_rows === 0 ? 100 : null;
        }

        return (int) min(floor($this->processed_rows / $this->total_rows * 100), 100);
    }

    public function isDraft(): bool
    {
        return $this->status === ImportStatuses::DRAFT;
    }

    public function isCancellable(): bool
    {
        return $this->status->isCancellable();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether the failure report can be downloaded
     */
    public function hasReport(): bool
    {
        return $this->report_path !== null && $this->failed_rows > 0 && ! $this->hasExpired();
    }

    /**
     * Whether the import was cancelled, checked against the database
     */
    public function wasCancelled(): bool
    {
        $status = static::query()->whereKey($this->getKey())->toBase()->value('status');

        return $status === ImportStatuses::CANCELLED->value;
    }

    /**
     * Stop a pending or processing import. The job stops before its next chunk; rows already
     * saved stay saved.
     */
    public function cancel(): bool
    {
        if (! $this->isCancellable()) {
            return false;
        }

        $this->forceFill([
            'status' => ImportStatuses::CANCELLED,
            'cancelled_at' => now(),
        ])->save();

        event(new ImportCancelled($this));

        return true;
    }

    public function markProcessing(?int $total_rows): void
    {
        $this->forceFill([
            'status' => ImportStatuses::PROCESSING,
            'total_rows' => $total_rows,
            'processed_rows' => 0,
            'started_at' => now(),
            'error' => null,
        ])->save();
    }

    public function recordProgress(ImportResult $result): void
    {
        $this->forceFill($this->counts($result))->save();
    }

    public function markCompleted(ImportResult $result, ?string $report_path): void
    {
        $this->forceFill([
            ...$this->counts($result),
            'status' => ImportStatuses::COMPLETED,
            'total_rows' => $result->processed,
            'failures' => array_map(fn (RowFailure $failure) => $failure->toArray(), $result->failures) ?: null,
            'report_path' => $report_path,
            'completed_at' => now(),
            'expires_at' => now()->addDays(static::expiresAfterDays()),
        ])->save();
    }

    public function markFailed(Throwable|string $error): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $this->forceFill([
            'status' => ImportStatuses::FAILED,
            'error' => Str::limit($message, 1000),
            'failed_at' => now(),
            'expires_at' => now()->addDays(static::expiresAfterDays()),
        ])->save();
    }

    /**
     * The failed rows kept on the import (the first `imports.failure_samples`)
     *
     * @return array<int, RowFailure>
     */
    public function failureSamples(): array
    {
        return array_map(fn (array $failure) => RowFailure::fromArray($failure), $this->failures ?? []);
    }

    /**
     * Copy the uploaded file to a local temporary file, for reading. Delete it when done.
     */
    public function localCopy(): string
    {
        if (! $this->path || ! Storage::disk($this->disk)->exists($this->path)) {
            throw new RuntimeException(__('The uploaded file is no longer available.'));
        }

        return TemporaryFile::fromDisk($this->disk, $this->path, $this->format);
    }

    public function reportFilename(): string
    {
        $extension = pathinfo((string) $this->report_path, PATHINFO_EXTENSION) ?: $this->format->extension();

        return pathinfo($this->filename, PATHINFO_FILENAME) . '-' . __('failed rows') . ".{$extension}";
    }

    /**
     * Stream the failure report to the browser
     */
    public function downloadReport(): StreamedResponse
    {
        $format = Formats::resolve(null, (string) $this->report_path);

        return Storage::disk($this->disk)->download($this->report_path, $this->reportFilename(), [
            'Content-Type' => $format->mimeType(),
        ]);
    }

    public function reportExists(): bool
    {
        return $this->report_path && Storage::disk($this->disk)->exists($this->report_path);
    }

    /**
     * Delete the uploaded file and the failure report
     */
    public function deleteFiles(): void
    {
        if ($this->path) {
            Storage::disk($this->disk)->delete($this->path);
        }

        $this->deleteReport();
    }

    protected function deleteReport(): void
    {
        if (! $this->report_path) {
            return;
        }

        Storage::disk($this->disk)->delete($this->report_path);

        $this->forceFill(['report_path' => null])->save();
    }

    /**
     * A signed link to download the failure report without logging in, for notifications.
     * Null when there's no report or the signed route isn't registered.
     */
    public function temporaryReportUrl(?DateTimeInterface $expires_at = null): ?string
    {
        if (! $this->hasReport()) {
            return null;
        }

        $route = config('imports.routes.signed_report', 'imports.signed-report');

        if (! Route::has($route)) {
            return null;
        }

        $expires_at ??= now()->addMinutes((int) config('imports.signed_url_minutes', 1440));

        if ($this->expires_at && $this->expires_at->lessThan($expires_at)) {
            $expires_at = $this->expires_at;
        }

        return URL::temporarySignedRoute($route, $expires_at, ['import' => $this->uuid]);
    }

    /**
     * The private broadcast channel for this import's progress
     */
    public function broadcastChannel(): string
    {
        return Broadcasting::channelPrefix() . ".{$this->uuid}";
    }

    /**
     * The private broadcast channel for every import of this import's owner
     */
    public function ownerBroadcastChannel(): ?string
    {
        if (! $this->user_type || $this->user_id === null) {
            return null;
        }

        return Broadcasting::channelPrefix() . '.owner.' . Broadcasting::encodeType($this->user_type) . ".{$this->user_id}";
    }

    public static function expiresAfterDays(): int
    {
        return max((int) config('imports.expire_after_days', 7), 1);
    }

    /**
     * Where files are stored on the disk
     */
    public static function directory(): string
    {
        return trim((string) config('imports.directory', 'imports'), '/') ?: 'imports';
    }

    /**
     * A file name without paths or characters file systems reject
     */
    public static function safeFilename(string $filename): string
    {
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '-', $filename), " .-\t");

        return $filename === '' ? 'import.csv' : $filename;
    }

    /**
     * @return array<string, int>
     */
    protected function counts(ImportResult $result): array
    {
        return [
            'processed_rows' => $result->processed,
            'created_rows' => $result->created,
            'updated_rows' => $result->updated,
            'skipped_rows' => $result->skipped,
            'failed_rows' => $result->failed,
        ];
    }
}
