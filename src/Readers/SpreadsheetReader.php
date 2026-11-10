<?php

namespace Javaabu\Imports\Readers;

use DateTimeInterface;
use Generator;
use Javaabu\Imports\Enums\Formats;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Options as OdsOptions;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Stringable;

/**
 * Streams the rows of a CSV, XLSX or ODS file with OpenSpout
 *
 * Rows are yielded one at a time with their spreadsheet row number, so memory stays flat
 * however big the file is. Empty rows are skipped but still counted, so row numbers match
 * what people see in their spreadsheet. Dates in XLSX and ODS cells come back as
 * DateTimeImmutable, numbers as int or float, everything else as strings.
 */
class SpreadsheetReader
{
    /**
     * @param  int|string  $sheet  the sheet's index (from 0) or name
     */
    public function __construct(
        protected string $path,
        protected Formats $format,
        protected int $heading_row = 1,
        protected int|string $sheet = 0,
    ) {}

    public static function make(string $path, Formats|string|null $format = null, int $heading_row = 1, int|string $sheet = 0): static
    {
        return new static($path, Formats::resolve($format, $path), max($heading_row, 1), $sheet);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function format(): Formats
    {
        return $this->format;
    }

    public function headingRow(): int
    {
        return $this->heading_row;
    }

    /**
     * The headings, as strings, in column order. Blank headings stay as empty strings so
     * column positions line up with the data rows.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        foreach ($this->allRows() as $number => $cells) {
            if ($number < $this->heading_row) {
                continue;
            }

            if ($number > $this->heading_row) {
                break;
            }

            $headings = array_map(fn (mixed $value) => trim($this->stringify($value)), $cells);

            while ($headings !== [] && end($headings) === '') {
                array_pop($headings);
            }

            return $headings;
        }

        return [];
    }

    /**
     * The data rows after the heading row, keyed by spreadsheet row number. Empty rows are skipped.
     *
     * @return Generator<int, array<int, mixed>>
     */
    public function rows(?int $limit = null): Generator
    {
        $count = 0;

        foreach ($this->allRows() as $number => $cells) {
            if ($number <= $this->heading_row) {
                continue;
            }

            if ($this->isEmpty($cells)) {
                continue;
            }

            if ($limit !== null && $count >= $limit) {
                return;
            }

            $count++;

            yield $number => $cells;
        }
    }

    /**
     * The number of data rows (not counting empty rows). Reads the whole file.
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->rows() as $row) {
            $count++;
        }

        return $count;
    }

    /**
     * Every row of the chosen sheet, keyed by row number (from 1), empty rows included
     *
     * @return Generator<int, array<int, mixed>>
     */
    protected function allRows(): Generator
    {
        $reader = $this->makeReader();
        $reader->open($this->path);

        try {
            $sheet = $this->findSheet($reader);

            if (! $sheet) {
                return;
            }

            $number = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $number++;

                yield $number => $row->toArray();
            }
        } finally {
            $reader->close();
        }
    }

    protected function findSheet(ReaderInterface $reader): ?SheetInterface
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            if (is_int($this->sheet) && $sheet->getIndex() === $this->sheet) {
                return $sheet;
            }

            if (is_string($this->sheet) && $sheet->getName() === $this->sheet) {
                return $sheet;
            }
        }

        return null;
    }

    protected function makeReader(): ReaderInterface
    {
        return match ($this->format) {
            Formats::CSV => new CsvReader(new CsvOptions(
                SHOULD_PRESERVE_EMPTY_ROWS: true,
                FIELD_DELIMITER: (string) config('imports.csv.delimiter', ','),
                FIELD_ENCLOSURE: (string) config('imports.csv.enclosure', '"'),
                ENCODING: (string) config('imports.csv.encoding', 'UTF-8'),
            )),
            Formats::XLSX => new XlsxReader(new XlsxOptions(
                SHOULD_FORMAT_DATES: false,
                SHOULD_PRESERVE_EMPTY_ROWS: true,
                tempFolder: config('imports.temporary_directory') ?: null,
            )),
            Formats::ODS => new OdsReader(new OdsOptions(
                SHOULD_FORMAT_DATES: false,
                SHOULD_PRESERVE_EMPTY_ROWS: true,
            )),
        };
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    protected function isEmpty(array $cells): bool
    {
        foreach ($cells as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return false;
        }

        return true;
    }

    protected function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_string($value) => $value,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof Stringable, is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
