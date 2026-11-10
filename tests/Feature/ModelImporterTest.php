<?php

namespace Javaabu\Imports\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Enums\Duplicates;
use Javaabu\Imports\Exceptions\InvalidMappingException;
use Javaabu\Imports\Tests\TestCase;
use Javaabu\Imports\Tests\TestSupport\Enums\ProductStatuses;
use Javaabu\Imports\Tests\TestSupport\Importers\BrandsImporter;
use Javaabu\Imports\Tests\TestSupport\Importers\DefaultProductsImporter;
use Javaabu\Imports\Tests\TestSupport\Importers\ProductsImporter;
use Javaabu\Imports\Tests\TestSupport\Models\Brand;
use Javaabu\Imports\Tests\TestSupport\Models\Product;
use Javaabu\Imports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class ModelImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Brand::query()->create(['name' => 'Sony', 'code' => 'SNY']);
        Brand::query()->create(['name' => 'ސެމްސަންގް', 'code' => 'SAM']);
    }

    #[Test]
    public function it_imports_models_mapping_headings_automatically(): void
    {
        $path = $this->spreadsheet([
            ['Item code', 'Name *', 'Price', 'Stock', 'Status', 'Active', 'Released On', 'Brand'],
            ['TV-1', 'ޓީވީ', '1,250.50', '3', 'Published', 'Yes', '2025-03-01', 'sony'],
            ['TV-2', 'ފޯނު', '', '', '', 'No', '', 'SAM'],
        ]);

        $result = ProductsImporter::make()->import($path);

        $this->assertSame(2, $result->created, json_encode($result));
        $this->assertSame(0, $result->failed);

        $tv = Product::query()->where('sku', 'TV-1')->firstOrFail();
        $this->assertSame('ޓީވީ', $tv->name);
        $this->assertSame('1250.50', $tv->price);
        $this->assertSame(3, $tv->stock);
        $this->assertSame(ProductStatuses::PUBLISHED, $tv->status);
        $this->assertTrue($tv->is_active);
        $this->assertSame('2025-03-01', $tv->released_on->toDateString());
        $this->assertSame('Sony', $tv->brand->name);

        $phone = Product::query()->where('sku', 'TV-2')->firstOrFail();
        $this->assertSame('0.00', $phone->price);
        $this->assertSame(ProductStatuses::DRAFT, $phone->status);
        $this->assertFalse($phone->is_active);
        $this->assertSame('ސެމްސަންގް', $phone->brand->name);
    }

    #[Test]
    public function invalid_rows_fail_without_stopping_the_import(): void
    {
        $path = $this->spreadsheet([
            ['SKU', 'Name', 'Price', 'Brand', 'Status'],
            ['A-1', 'ފަންކާ', '-5', 'Sony', 'draft'],
            ['A-2', '', '10', 'Nokia', 'sold'],
            ['A-3', 'ލައިޓު', '10', 'Sony', 'draft'],
        ]);

        $report = sys_get_temp_dir() . '/imports-report-' . uniqid() . '.csv';
        $this->temporary_files[] = $report;

        $result = ProductsImporter::make()->import($path, report_path: $report);

        $this->assertSame(1, $result->created);
        $this->assertSame(2, $result->failed);
        $this->assertSame(['A-3'], Product::query()->pluck('sku')->all());

        [$first, $second] = $result->failures;
        $this->assertSame(2, $first->row);
        $this->assertArrayHasKey('price', $first->errors);
        $this->assertSame(3, $second->row);
        $this->assertSame(['brand_id', 'name', 'status'], array_keys(array_intersect_key(['brand_id' => 1, 'name' => 1, 'status' => 1], $second->errors)));
        $this->assertSame(['No Brand matches "Nokia".'], $second->errors['brand_id']);

        $this->assertSame($report, $result->report_path);
        $rows = $this->readSpreadsheet($report);
        $this->assertSame(['Row', 'SKU', 'Name', 'Price', 'Brand', 'Status', 'Errors'], $rows[0]);
        $this->assertSame(['2', 'A-1', 'ފަންކާ'], array_slice($rows[1], 0, 3));
        $this->assertStringContainsString('Price', $rows[1][6]);
        $this->assertCount(3, $rows);
    }

    #[Test]
    public function no_report_is_written_when_every_row_imports(): void
    {
        $path = $this->spreadsheet([['SKU', 'Name'], ['A-1', 'Fan']]);
        $report = sys_get_temp_dir() . '/imports-report-' . uniqid() . '.csv';

        $result = ProductsImporter::make()->import($path, report_path: $report);

        $this->assertNull($result->report_path);
        $this->assertFileDoesNotExist($report);
    }

    #[Test]
    public function existing_records_are_skipped_by_default(): void
    {
        Product::query()->create(['sku' => 'A-1', 'name' => 'Old']);

        $result = ProductsImporter::make()->import($this->spreadsheet([['SKU', 'Name'], ['A-1', 'New'], ['A-2', 'Other']]));

        $this->assertSame(1, $result->skipped);
        $this->assertSame(1, $result->created);
        $this->assertSame('Old', Product::query()->where('sku', 'A-1')->value('name'));
    }

    #[Test]
    public function existing_records_can_be_updated_keeping_values_of_empty_cells(): void
    {
        Product::query()->create(['sku' => 'A-1', 'name' => 'Old', 'stock' => 7, 'price' => 3]);

        $result = ProductsImporter::make()
            ->duplicates(Duplicates::UPDATE)
            ->import($this->spreadsheet([['SKU', 'Name', 'Stock'], ['A-1', ' އައު', '']]));

        $this->assertSame(1, $result->updated);

        $product = Product::query()->where('sku', 'A-1')->firstOrFail();
        $this->assertSame('އައު', $product->name);
        $this->assertSame(7, $product->stock);
    }

    #[Test]
    public function rows_that_already_exist_can_fail(): void
    {
        Product::query()->create(['sku' => 'A-1', 'name' => 'Old']);

        $result = ProductsImporter::make()->duplicates('fail')->import($this->spreadsheet([['SKU', 'Name'], ['A-1', 'New']]));

        $this->assertSame(1, $result->failed);
        $this->assertSame(['A matching record already exists.'], $result->failures[0]->messages());
    }

    #[Test]
    public function repeated_rows_in_one_file_update_the_first(): void
    {
        $result = ProductsImporter::make()
            ->duplicates('update')
            ->import($this->spreadsheet([['SKU', 'Name'], ['A-1', 'First'], ['A-1', 'Second']]));

        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->updated);
        $this->assertSame(['Second'], Product::query()->pluck('name')->all());
    }

    #[Test]
    public function a_dry_run_validates_and_counts_without_saving(): void
    {
        Product::query()->create(['sku' => 'A-1', 'name' => 'Old']);

        $result = ProductsImporter::make()
            ->dryRun()
            ->duplicates('update')
            ->import($this->spreadsheet([['SKU', 'Name'], ['A-1', 'New'], ['A-2', 'Other'], ['A-3', '']]));

        $this->assertTrue($result->dry_run);
        $this->assertSame([1, 1, 1], [$result->created, $result->updated, $result->failed]);
        $this->assertSame(1, Product::query()->count());
    }

    #[Test]
    public function headings_can_be_mapped_by_text_or_position(): void
    {
        $path = $this->spreadsheet([['Code', 'Title', 'Notes'], ['A-1', 'Fan', 'ignored']]);

        ProductsImporter::make()->map(['Code' => 'sku', 'title' => 'name', 'Notes' => null])->import($path);
        ProductsImporter::make()->map([0 => 'sku', 1 => 'name'])->duplicates('update')->import($path);

        $this->assertSame(['A-1' => 'Fan'], Product::query()->pluck('name', 'sku')->all());
    }

    #[Test]
    public function required_columns_must_be_mapped(): void
    {
        $path = $this->spreadsheet([['Code', 'Notes'], ['A-1', 'x']]);

        try {
            ProductsImporter::make()->import($path);
            $this->fail('The import should not run.');
        } catch (InvalidMappingException $exception) {
            $this->assertSame(['mapping.name'], array_keys($exception->errors()));
        }
    }

    #[Test]
    public function mappings_must_name_real_headings_and_fields_once(): void
    {
        $path = $this->spreadsheet([['Code', 'Name'], ['A-1', 'x']]);

        try {
            ProductsImporter::make()->map(['Missing' => 'sku', 'Code' => 'nope', 'Name' => 'name', 1 => 'name'])->import($path);
            $this->fail('The import should not run.');
        } catch (InvalidMappingException $exception) {
            $this->assertSame(['mapping.Missing', 'mapping.0', 'mapping.1'], array_keys($exception->errors()));
        }
    }

    #[Test]
    public function missing_related_models_can_be_created(): void
    {
        $importer = new class extends ProductsImporter
        {
            public function columns(): array
            {
                return [
                    Column::make('sku'),
                    Column::make('name'),
                    Column::make('brand_id')->label('Brand')->lookup(Brand::class)->createMissing(),
                ];
            }
        };

        $importer->import($this->spreadsheet([['SKU', 'Name', 'Brand'], ['A-1', 'Fan', 'ޕެނަސޮނިކް'], ['A-2', 'Iron', 'ޕެނަސޮނިކް']]));

        $brand = Brand::query()->where('name', 'ޕެނަސޮނިކް')->firstOrFail();
        $this->assertSame(2, $brand->products()->count());
    }

    #[Test]
    public function the_default_columns_come_from_the_table(): void
    {
        $columns = DefaultProductsImporter::make()->resolvedColumns();

        $this->assertSame(['sku', 'name', 'description', 'price', 'stock', 'status', 'is_active', 'released_on', 'brand_id', 'category_id'], array_keys($columns));
        $this->assertTrue($columns['sku']->isRequired());
        $this->assertSame(Column::STRING, $columns['sku']->getType());
        $this->assertFalse($columns['price']->isRequired());
        $this->assertSame(ProductStatuses::class, $columns['status']->getEnum());
        $this->assertSame(Column::BOOLEAN, $columns['is_active']->getType());
        $this->assertSame(Column::DATE, $columns['released_on']->getType());
        $this->assertSame(Column::NUMBER, $columns['price']->getType());
        $this->assertSame(Brand::class, $columns['brand_id']->getLookup()->model());
        $this->assertSame(['name', 'code'], $columns['brand_id']->getLookup()->by());
        $this->assertSame('Brand', $columns['brand_id']->getLabel());
        $this->assertSame(['sku'], DefaultProductsImporter::make()->uniqueBy());
    }

    #[Test]
    public function the_default_columns_import_an_exported_file(): void
    {
        $result = DefaultProductsImporter::make()->duplicates('update')->import($this->spreadsheet([
            ['Sku', 'Name', 'Price', 'Status', 'Is Active', 'Released On', 'Brand', 'Category'],
            ['B-1', 'ބޯޓު', 100, 'Archived', 'No', '2024-12-31', 'Sony', ''],
        ], 'xlsx'));

        $this->assertSame(1, $result->created, json_encode($result->failures));

        $product = Product::query()->firstOrFail();
        $this->assertSame(ProductStatuses::ARCHIVED, $product->status);
        $this->assertFalse($product->is_active);
        $this->assertSame('Sony', $product->brand->name);
    }

    #[Test]
    public function plain_importers_save_rows_themselves(): void
    {
        $result = BrandsImporter::make('X-')->import($this->spreadsheet([
            ['Name', 'Brand code'],
            ['LG', 'LG'],
            ['Forbidden', 'NO'],
        ]));

        $this->assertSame(1, $result->created);
        $this->assertSame(['This brand is not allowed.'], $result->failures[0]->errors['name']);
        $this->assertTrue(Brand::query()->where('name', 'X-LG')->where('code', 'LG')->exists());
    }

    #[Test]
    public function database_errors_fail_the_row_only(): void
    {
        $result = BrandsImporter::make()->import($this->spreadsheet([['Name'], ['Sony'], ['LG']]));

        $this->assertSame(1, $result->created);
        $this->assertSame(['The row could not be saved.'], $result->failures[0]->messages());
        $this->assertTrue(Brand::query()->where('name', 'LG')->exists());
    }

    #[Test]
    public function it_reads_rows_in_chunks(): void
    {
        config()->set('imports.chunk_size', 2);

        $rows = [['SKU', 'Name']];

        foreach (range(1, 7) as $number) {
            $rows[] = ["S-{$number}", "Item {$number}"];
        }

        $result = ProductsImporter::make()->import($this->spreadsheet($rows, 'ods'));

        $this->assertSame(7, $result->created);
        $this->assertSame(7, Product::query()->count());
    }

    #[Test]
    public function model_importers_authorize_with_the_create_ability(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(ProductsImporter::make()->authorize($user));
        $this->assertFalse(ProductsImporter::make()->authorize(null));
        $this->assertSame('Products', ProductsImporter::make()->label());
        $this->assertSame('Brands', BrandsImporter::make()->label());
    }

    #[Test]
    public function it_writes_a_template_with_headings_and_an_example(): void
    {
        $path = ProductsImporter::make()->template('csv');
        $this->temporary_files[] = $path;

        $rows = $this->readSpreadsheet($path);

        $this->assertSame(['SKU *', 'Name *', 'Price', 'Stock', 'Status', 'Active', 'Released On', 'Brand'], $rows[0]);
        $this->assertSame(['TV-001', 'ޓީވީ', '1250.5', '10'], array_slice($rows[1], 0, 4));
        $this->assertSame('products-template.xlsx', ProductsImporter::make()->templateFilename());
    }

    #[Test]
    public function importers_serialize_without_their_caches(): void
    {
        $importer = BrandsImporter::make('ބ-');
        $importer->resolvedColumns();

        $copy = unserialize(serialize($importer));

        $this->assertSame('ބ-', $copy->prefix);
        $this->assertCount(2, $copy->resolvedColumns());
    }
}
