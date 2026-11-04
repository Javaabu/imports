<?php

namespace Javaabu\Imports\Tests\Unit;

use DateTimeImmutable;
use Javaabu\Imports\Enums\Formats;
use Javaabu\Imports\Readers\SpreadsheetReader;
use Javaabu\Imports\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ValueError;

class SpreadsheetReaderTest extends TestCase
{
    public static function formats(): array
    {
        return [['csv'], ['xlsx'], ['ods']];
    }

    #[Test]
    #[DataProvider('formats')]
    public function it_reads_headings_and_rows_with_their_spreadsheet_row_numbers(string $extension): void
    {
        $path = $this->spreadsheet([
            ['Name', 'Island', ''],
            ['އަހުމަދު', 'މާލެ'],
            ['', ''],
            ['Aishath', 'Hulhumalé'],
        ], $extension);

        $reader = SpreadsheetReader::make($path);

        $this->assertSame(['Name', 'Island'], $reader->headings());
        $this->assertSame([2 => ['އަހުމަދު', 'މާލެ'], 4 => ['Aishath', 'Hulhumalé']], array_map(
            fn (array $cells) => array_slice($cells, 0, 2),
            iterator_to_array($reader->rows(), true),
        ));
        $this->assertSame(2, $reader->count());
        $this->assertSame(Formats::from($extension), $reader->format());
    }

    #[Test]
    public function it_limits_the_rows_it_reads(): void
    {
        $path = $this->spreadsheet([['n'], [1], [2], [3]]);

        $this->assertSame([2, 3], array_keys(iterator_to_array(SpreadsheetReader::make($path)->rows(2), true)));
    }

    #[Test]
    public function it_reads_the_heading_row_it_is_given(): void
    {
        $path = $this->spreadsheet([['Products export'], ['SKU', 'Name'], ['A1', 'Fan']], 'xlsx');

        $reader = SpreadsheetReader::make($path, heading_row: 2);

        $this->assertSame(['SKU', 'Name'], $reader->headings());
        $this->assertSame([3 => ['A1', 'Fan']], iterator_to_array($reader->rows(), true));
    }

    #[Test]
    public function xlsx_dates_and_numbers_keep_their_types(): void
    {
        $date = new DateTimeImmutable('2025-03-01 10:30:00');
        $path = $this->spreadsheet([['Date', 'Price', 'Stock'], [$date, 12.5, 3]], 'xlsx');

        $row = iterator_to_array(SpreadsheetReader::make($path)->rows(), false)[0];

        $this->assertInstanceOf(DateTimeImmutable::class, $row[0]);
        $this->assertSame('2025-03-01 10:30', $row[0]->format('Y-m-d H:i'));
        $this->assertSame(12.5, $row[1]);
        $this->assertSame(3, $row[2]);
    }

    #[Test]
    public function it_reads_csv_files_with_a_byte_order_mark(): void
    {
        $path = sys_get_temp_dir() . '/imports-bom-' . uniqid() . '.csv';
        $this->temporary_files[] = $path;
        file_put_contents($path, "\u{FEFF}ނަން,ރަށް\nއަހުމަދު,މާލެ\n");

        $reader = SpreadsheetReader::make($path);

        $this->assertSame(['ނަން', 'ރަށް'], $reader->headings());
        $this->assertSame([2 => ['އަހުމަދު', 'މާލެ']], iterator_to_array($reader->rows(), true));
    }

    #[Test]
    public function it_reads_a_sheet_by_name(): void
    {
        $path = $this->spreadsheet([['A'], ['1']], 'xlsx');

        $this->assertSame([], SpreadsheetReader::make($path, sheet: 'Missing')->headings());
        $this->assertSame(['A'], SpreadsheetReader::make($path, sheet: 'Sheet1')->headings());
    }

    #[Test]
    public function it_rejects_unknown_formats(): void
    {
        $this->expectException(ValueError::class);

        SpreadsheetReader::make('/tmp/file.pdf');
    }

    #[Test]
    public function txt_files_are_read_as_csv(): void
    {
        $this->assertSame(Formats::CSV, Formats::resolve(null, 'export.TXT'));
        $this->assertContains('txt', Formats::extensions());
    }
}
