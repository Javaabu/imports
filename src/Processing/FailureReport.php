<?php

namespace Javaabu\Imports\Processing;

use Javaabu\Imports\Enums\Formats;
use Javaabu\Imports\Writers\SpreadsheetWriter;

/**
 * Streams failed rows to a spreadsheet: the row number, the row as it was uploaded, and the errors
 *
 * People fix the rows in this file and import it again, so the original headings are kept
 * (the extra Row and Errors columns aren't mapped to anything). The file is only created
 * when the first row fails.
 */
class FailureReport
{
    protected ?SpreadsheetWriter $writer = null;

    protected int $count = 0;

    /**
     * @param  array<int, string>  $headings  the uploaded file's headings
     */
    public function __construct(
        protected string $path,
        protected Formats $format,
        protected array $headings,
    ) {}

    public function add(RowFailure $failure): void
    {
        if (! $this->writer) {
            $this->writer = (new SpreadsheetWriter)->open($this->path, $this->format);
            $this->writer->addHeadings([__('Row'), ...$this->headings, __('Errors')]);
        }

        $values = [];

        foreach (array_keys($this->headings) as $index) {
            $values[] = $failure->values[$index] ?? null;
        }

        $this->writer->addRow([$failure->row, ...$values, implode("\n", $failure->messages())]);
        $this->count++;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * The path of the report, or null when no row failed
     */
    public function path(): ?string
    {
        return $this->count > 0 ? $this->path : null;
    }

    public function format(): Formats
    {
        return $this->format;
    }

    public function close(): void
    {
        $this->writer?->close();
        $this->writer = null;
    }
}
