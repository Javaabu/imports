<?php

namespace Javaabu\Imports\Processing;

use Javaabu\Imports\Enums\RowOutcomes;
use JsonSerializable;

/**
 * The counts of an import: rows read, created, updated, skipped and failed, and the first failures
 *
 * Only the first `imports.failure_samples` failures are kept in memory; the failure report
 * has every one.
 */
class ImportResult implements JsonSerializable
{
    public int $processed = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $failed = 0;

    /** @var array<int, RowFailure> */
    public array $failures = [];

    /** The local path of the failure report, when one was written */
    public ?string $report_path = null;

    public function __construct(
        public readonly bool $dry_run = false,
        protected ?int $keep_failures = null,
    ) {
        $this->keep_failures ??= max((int) config('imports.failure_samples', 100), 0);
    }

    public function record(RowOutcomes $outcome): void
    {
        $this->processed++;

        match ($outcome) {
            RowOutcomes::CREATED => $this->created++,
            RowOutcomes::UPDATED => $this->updated++,
            RowOutcomes::SKIPPED => $this->skipped++,
        };
    }

    public function fail(RowFailure $failure): void
    {
        $this->processed++;
        $this->failed++;

        if (count($this->failures) < $this->keep_failures) {
            $this->failures[] = $failure;
        }
    }

    /**
     * Rows that were created or updated (or would be, in a dry run)
     */
    public function imported(): int
    {
        return $this->created + $this->updated;
    }

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dry_run,
            'processed' => $this->processed,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'failures' => array_map(fn (RowFailure $failure) => $failure->toArray(), $this->failures),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
