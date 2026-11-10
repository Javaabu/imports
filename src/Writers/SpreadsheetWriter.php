<?php

namespace Javaabu\Imports\Writers;

use BackedEnum;
use DateTimeInterface;
use Javaabu\Imports\Enums\Formats;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AbstractWriterMultiSheets;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\ODS\Options as OdsOptions;
use OpenSpout\Writer\ODS\Writer as OdsWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Stringable;
use UnitEnum;

/**
 * Writes import templates and failure reports to CSV, XLSX or ODS with OpenSpout, a row at a time
 *
 * Strings are always written as text, never as formulas. In CSV files, values a spreadsheet
 * would run as a formula are prefixed with an apostrophe: failure reports repeat what users
 * uploaded, so they must not turn it into something that runs.
 */
class SpreadsheetWriter
{
    /** Characters a spreadsheet treats as the start of a formula */
    protected const array FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    protected ?WriterInterface $writer = null;

    protected Formats $format;

    protected int $rows = 0;

    /** @var array<int, int> column index => widest value, for auto-sizing */
    protected array $widths = [];

    /**
     * Write a whole file: a bold heading row, then the rows
     *
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     * @return int the number of data rows written
     */
    public static function write(string $path, Formats $format, array $headings, iterable $rows): int
    {
        $writer = (new static)->open($path, $format);

        try {
            $writer->addHeadings($headings);

            foreach ($rows as $row) {
                $writer->addRow($row);
            }
        } finally {
            $writer->close();
        }

        return $writer->rowCount();
    }

    public function open(string $path, Formats $format): static
    {
        $this->format = $format;
        $this->rows = 0;
        $this->widths = [];

        $this->writer = $this->makeWriter($format);
        $this->writer->openToFile($path);

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->writer !== null;
    }

    /**
     * @param  array<int, string>  $headings
     */
    public function addHeadings(array $headings): static
    {
        $writer = $this->writer ?? throw new RuntimeException('Open the writer first.');

        if ($this->format === Formats::XLSX && $this->isRtl() && $writer instanceof XlsxWriter) {
            $writer->getCurrentSheet()->setSheetView((new SheetView)->withRightToLeft(true)->withFreezeRow(2));
        }

        $writer->addRow(new Row($this->cells($headings, (new Style)->withFontBold(true))));

        return $this;
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    public function addRow(array $values): static
    {
        $writer = $this->writer ?? throw new RuntimeException('Open the writer first.');

        $writer->addRow(new Row($this->cells(array_values($values))));
        $this->rows++;

        return $this;
    }

    public function rowCount(): int
    {
        return $this->rows;
    }

    public function close(): void
    {
        if (! $this->writer) {
            return;
        }

        $this->applyColumnWidths($this->writer);

        $this->writer->close();
        $this->writer = null;
    }

    protected function makeWriter(Formats $format): WriterInterface
    {
        $temporary_directory = $this->temporaryDirectory();

        return match ($format) {
            Formats::CSV => new CsvWriter(new CsvOptions(
                FIELD_DELIMITER: (string) config('imports.csv.delimiter', ','),
                FIELD_ENCLOSURE: (string) config('imports.csv.enclosure', '"'),
                SHOULD_ADD_BOM: true,
            )),
            Formats::XLSX => new XlsxWriter(new XlsxOptions(tempFolder: $temporary_directory)),
            Formats::ODS => new OdsWriter(new OdsOptions(tempFolder: $temporary_directory)),
        };
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, Cell>
     */
    protected function cells(array $values, ?Style $style = null): array
    {
        $cells = [];

        foreach ($values as $index => $value) {
            $cell = $this->cell($value, $style);

            $this->trackWidth($index, $cell);

            $cells[] = $cell;
        }

        return $cells;
    }

    protected function cell(mixed $value, ?Style $style = null): Cell
    {
        if ($value === null || $value === '') {
            return new EmptyCell(null, $style);
        }

        if (is_bool($value)) {
            return new BooleanCell($value, $style);
        }

        if (is_int($value) || is_float($value)) {
            return new NumericCell($value, $style);
        }

        return new StringCell($this->escape($this->stringify($value)), $style);
    }

    protected function stringify(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Stringable => (string) $value,
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            is_object($value) => $value::class,
            default => (string) $value,
        };
    }

    /**
     * Stop a CSV value from being run as a formula when the file is opened in a spreadsheet
     */
    protected function escape(string $value): string
    {
        if ($this->format !== Formats::CSV) {
            return $value;
        }

        if (! in_array($value[0], self::FORMULA_TRIGGERS, true)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        return "'{$value}";
    }

    protected function trackWidth(int $index, Cell $cell): void
    {
        $value = $cell->getValue();

        $length = is_string($value) ? mb_strlen($value) : strlen((string) $value);

        if ($length > ($this->widths[$index] ?? 0)) {
            $this->widths[$index] = $length;
        }
    }

    protected function applyColumnWidths(WriterInterface $writer): void
    {
        if ($this->format !== Formats::XLSX || ! $writer instanceof AbstractWriterMultiSheets) {
            return;
        }

        $sheet = $writer->getCurrentSheet();

        foreach ($this->widths as $index => $length) {
            $sheet->setColumnWidth(min(max($length + 2, 8), 60), $index + 1);
        }
    }

    protected function isRtl(): bool
    {
        return in_array(app()->getLocale(), config('imports.rtl_locales', ['dv', 'ar', 'fa', 'he', 'ur']), true);
    }

    protected function temporaryDirectory(): string
    {
        $directory = config('imports.temporary_directory') ?: sys_get_temp_dir();

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }
}
