<?php

namespace Javaabu\Imports\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Importers\ProductsImporter;
use Javaabu\Imports\Tests\TestSupport\Models\Brand;
use Javaabu\Imports\Tests\TestSupport\Models\Product;
use PHPUnit\Framework\Attributes\Test;

class PreviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_previews_headings_samples_mapping_errors_and_outcomes_without_saving(): void
    {
        Brand::query()->create(['name' => 'Sony']);
        Product::query()->create(['sku' => 'A-1', 'name' => 'Old']);

        $path = $this->spreadsheet([
            ['Code', 'Name', 'Brand', 'Notes'],
            ['A-1', 'ފަންކާ', 'Sony', 'x'],
            ['A-2', '', 'Nokia', 'y'],
            ['A-3', 'ލައިޓު', '', 'z'],
            ['A-4', 'ބޯޓު', 'Sony', ''],
        ]);

        $preview = ProductsImporter::make()->duplicates('update')->preview($path, rows: 3);
        $data = $preview->toArray();

        $this->assertTrue($preview->isReady());
        $this->assertSame([0 => 'sku', 1 => 'name', 2 => 'brand_id'], $preview->mapping->toArray());
        $this->assertSame(['Code', 'Name', 'Brand', 'Notes'], array_column($data['headings'], 'heading'));
        $this->assertSame(['A-1', 'A-2', 'A-3'], $data['headings'][0]['samples']);
        $this->assertNull($data['headings'][3]['attribute']);
        $this->assertSame(0, collect($data['columns'])->firstWhere('attribute', 'sku')['heading']);
        $this->assertNull(collect($data['columns'])->firstWhere('attribute', 'stock')['heading']);

        $this->assertSame(3, $preview->checkedCount());
        $this->assertSame(1, $preview->invalidCount());
        $this->assertSame([3], array_column($preview->invalidRows(), 'row'));
        $this->assertEqualsCanonicalizing(['name', 'brand_id'], array_keys($preview->rows[1]['errors']));
        $this->assertSame('updated', $preview->rows[0]['outcome']);
        $this->assertSame('created', $preview->rows[2]['outcome']);
        $this->assertSame(['checked' => 3, 'invalid' => 1, 'created' => 1, 'updated' => 1, 'skipped' => 0], $data['counts']);

        $this->assertSame(1, Product::query()->count());
    }

    #[Test]
    public function it_lists_required_columns_that_are_not_mapped(): void
    {
        $preview = ProductsImporter::make()->preview($this->spreadsheet([['Code', 'Notes'], ['A-1', 'x']]));

        $this->assertFalse($preview->isReady());
        $this->assertSame(['name'], $preview->missing);
        $this->assertSame([], $preview->rows);
    }

    #[Test]
    public function it_previews_a_given_mapping_of_an_uploaded_file(): void
    {
        $file = $this->uploadedFile($this->spreadsheet([['A', 'B'], ['S-1', 'ފެން']], 'xlsx'), 'ބަރުދަލު.xlsx');

        $preview = ProductsImporter::make()->map(['A' => 'sku', 'B' => 'name'])->preview($file);

        $this->assertSame('xlsx', $preview->format);
        $this->assertSame([], $preview->rows[0]['errors']);
        $this->assertSame(['sku' => 'S-1', 'name' => 'ފެން'], $preview->rows[0]['values']);
    }
}
