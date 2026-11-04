<?php

namespace Javaabu\Imports\Processing;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Javaabu\Imports\Exceptions\ImportCancelledException;
use Javaabu\Imports\Exceptions\RowFailedException;
use Javaabu\Imports\Importer;
use Javaabu\Imports\Mapping\Mapping;
use Javaabu\Imports\Readers\SpreadsheetReader;

/**
 * Runs an import: streams the file a chunk at a time, validates each row, saves the valid
 * ones in one transaction per chunk, and records failures
 *
 * A failing row never stops the import. It's rolled back on its own (each row runs in a
 * savepoint), counted, kept as a sample in the result and written to the failure report.
 */
class ImportRunner
{
    protected ImportResult $result;

    /**
     * @param  Closure(ImportResult): void|null  $on_progress  called after each chunk
     * @param  Closure(): bool|null  $should_stop  checked before each chunk; true cancels the import
     */
    public function __construct(
        protected Importer $importer,
        protected SpreadsheetReader $reader,
        protected Mapping $mapping,
        protected ?FailureReport $report = null,
        protected ?Closure $on_progress = null,
        protected ?Closure $should_stop = null,
    ) {}

    public function run(): ImportResult
    {
        $this->result = new ImportResult($this->importer->isDryRun());
        $chunk_size = $this->importer->chunkSize();
        $chunk = [];

        try {
            foreach ($this->reader->rows() as $row => $cells) {
                $chunk[$row] = $cells;

                if (count($chunk) >= $chunk_size) {
                    $this->processChunk($chunk);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                $this->processChunk($chunk);
            }
        } finally {
            $this->report?->close();
            $this->result->report_path = $this->report?->path();
        }

        return $this->result;
    }

    public function result(): ImportResult
    {
        return $this->result;
    }

    /**
     * @param  array<int, array<int, mixed>>  $chunk  row number => cells
     */
    protected function processChunk(array $chunk): void
    {
        if ($this->should_stop && ($this->should_stop)()) {
            throw new ImportCancelledException;
        }

        $this->importer->prepareChunk($chunk, $this->mapping);

        $valid = [];

        foreach ($chunk as $row => $cells) {
            try {
                [$data, $errors] = $this->importer->processRow($cells, $row, $this->mapping);
            } catch (RowFailedException $exception) {
                $this->fail($row, $exception->errors(), $cells);

                continue;
            }

            if ($errors) {
                $this->fail($row, $errors, $cells);

                continue;
            }

            $valid[$row] = $data;
        }

        if ($valid !== []) {
            $this->persistChunk($valid, $chunk);
        }

        if ($this->on_progress) {
            ($this->on_progress)($this->result);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $valid  row number => data
     * @param  array<int, array<int, mixed>>  $chunk  row number => cells
     */
    protected function persistChunk(array $valid, array $chunk): void
    {
        if ($this->importer->isDryRun()) {
            $this->importer->beforePersist($valid);

            foreach ($valid as $row => $data) {
                try {
                    $this->result->record($this->importer->previewOutcome($data, $row));
                } catch (RowFailedException $exception) {
                    $this->fail($row, $exception->errors(), $chunk[$row]);
                }
            }

            return;
        }

        DB::connection($this->importer->connectionName())->transaction(function () use ($valid, $chunk) {
            $this->importer->beforePersist($valid);

            foreach ($valid as $row => $data) {
                try {
                    $this->result->record($this->importer->persistRow($data, $row));
                } catch (RowFailedException $exception) {
                    $this->fail($row, $exception->errors(), $chunk[$row]);
                } catch (QueryException $exception) {
                    report($exception);

                    $this->fail($row, ['row' => [__('The row could not be saved.')]], $chunk[$row]);
                }
            }
        });
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<int, mixed>  $cells
     */
    protected function fail(int $row, array $errors, array $cells): void
    {
        $failure = new RowFailure($row, $errors, $cells);

        $this->result->fail($failure);
        $this->report?->add($failure);
    }
}
