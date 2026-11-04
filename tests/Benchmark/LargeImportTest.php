<?php

namespace Javaabu\Imports\Tests\Benchmark;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Importers\ProductsImporter;
use Javaabu\Imports\Tests\TestSupport\Models\Brand;
use Javaabu\Imports\Tests\TestSupport\Models\Product;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Imports a big file and checks memory stays flat. 200,000 rows by default; set
 * IMPORTS_BENCHMARK_ROWS to change it. Run with `composer benchmark`.
 */
#[Group('benchmark')]
class LargeImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_hundreds_of_thousands_of_rows_with_flat_memory(): void
    {
        $count = (int) (getenv('IMPORTS_BENCHMARK_ROWS') ?: 200000);
        $extension = getenv('IMPORTS_BENCHMARK_FORMAT') ?: 'csv';

        Brand::query()->create(['name' => 'Sony', 'code' => 'SNY']);
        Brand::query()->create(['name' => 'ސެމްސަންގް', 'code' => 'SAM']);

        $path = $this->writeRows($count, $extension);

        gc_collect_cycles();
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $started = microtime(true);

        $result = ProductsImporter::make()->import($path);

        $seconds = microtime(true) - $started;
        $peak = memory_get_peak_usage() - $before;

        fwrite(STDERR, sprintf(
            "\nImported %s %s rows in %.1fs (%s rows/s), peak memory +%.1f MB\n",
            number_format($count),
            $extension,
            $seconds,
            number_format($count / max($seconds, 0.001)),
            $peak / 1048576,
        ));

        $this->assertSame($count - intdiv($count, 1000), $result->created);
        $this->assertSame(intdiv($count, 1000), $result->failed);
        $this->assertSame($result->created, Product::query()->count());
        $this->assertLessThan(64 * 1048576, $peak, 'Memory should not grow with the number of rows');
    }

    protected function writeRows(int $count, string $extension): string
    {
        $path = sys_get_temp_dir() . '/imports-benchmark-' . uniqid() . '.' . $extension;
        $this->temporary_files[] = $path;

        $writer = $extension === 'xlsx' ? new XlsxWriter : new CsvWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['SKU', 'Name', 'Price', 'Stock', 'Status', 'Active', 'Released On', 'Brand']));

        for ($i = 1; $i <= $count; $i++) {
            // One row in a thousand has a brand that doesn't exist
            $brand = $i % 1000 === 0 ? 'Nokia' : ($i % 2 ? 'Sony' : 'SAM');

            $writer->addRow(Row::fromValues(["SKU-{$i}", "ޕްރޮޑަކްޓް {$i}", $i % 500 + 0.5, $i % 50, 'Published', $i % 2 ? 'Yes' : 'No', '2025-01-01', $brand]));
        }

        $writer->close();

        return $path;
    }
}
