<?php

namespace Javaabu\Imports\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Javaabu\Imports\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MakeImporterCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('Imports'));

        parent::tearDown();
    }

    #[Test]
    public function it_writes_an_importer_with_the_columns_of_the_table(): void
    {
        $this->artisan('imports:make', [
            'name' => 'ProductsImporter',
            '--model' => 'Javaabu\\Imports\\Tests\\TestSupport\\Models\\Product',
        ])->assertSuccessful();

        $code = File::get(app_path('Imports/ProductsImporter.php'));

        $this->assertStringContainsString('namespace App\\Imports;', $code);
        $this->assertStringContainsString('use Javaabu\\Imports\\Tests\\TestSupport\\Models\\Product;', $code);
        $this->assertStringContainsString('class ProductsImporter extends ModelImporter', $code);
        $this->assertStringContainsString('return Product::class;', $code);
        $this->assertStringContainsString("Column::make('sku')->required()->string()", $code);
        $this->assertStringContainsString("Column::make('status')->enum(\\Javaabu\\Imports\\Tests\\TestSupport\\Enums\\ProductStatuses::class)", $code);
        $this->assertStringContainsString("Column::make('brand_id')->lookup(\\Javaabu\\Imports\\Tests\\TestSupport\\Models\\Brand::class, ['name', 'code'])", $code);
        $this->assertStringNotContainsString('api_token', $code);

        $this->assertSame('0', exec('php -l ' . escapeshellarg(app_path('Imports/ProductsImporter.php')) . ' > /dev/null 2>&1; echo $?'));
    }

    #[Test]
    public function it_falls_back_to_a_name_column_for_unknown_models(): void
    {
        $this->artisan('imports:make', ['name' => 'WidgetsImporter'])->assertSuccessful();

        $code = File::get(app_path('Imports/WidgetsImporter.php'));

        $this->assertStringContainsString('use App\\Models\\Widget;', $code);
        $this->assertStringContainsString("Column::make('name')->required(),", $code);
    }
}
