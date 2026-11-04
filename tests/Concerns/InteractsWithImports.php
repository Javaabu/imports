<?php

namespace Javaabu\Imports\Tests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Imports;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Processing\ImportResult;
use Javaabu\Imports\Processing\RowFailure;
use Javaabu\Imports\Tests\TestSupport\Importers\ProductsImporter;
use Javaabu\Imports\Tests\TestSupport\Models\Product;
use Javaabu\Imports\Tests\TestSupport\Policies\ProductPolicy;
use Javaabu\Permissions\Permissions;

trait InteractsWithImports
{
    use RefreshDatabase;

    protected function setUpImports(): void
    {
        Storage::fake('imports');

        config()->set('imports.disk', 'imports');

        Permissions::resource('products', 'products', ['view', 'edit']);
        Permissions::policy(Product::class, ProductPolicy::class);

        Imports::register('products', ProductsImporter::class);

        $this->artisan('permissions:sync');
    }

    protected function tearDownImports(): void
    {
        Imports::forget();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function productRows(): array
    {
        return [
            ['SKU', 'Name', 'Price', 'Notes'],
            ['A-1', 'ފަންކާ', '10', 'x'],
            ['A-2', '', '-1', 'y'],
            ['A-3', 'ލައިޓު', '5', 'z'],
        ];
    }

    protected function draftImport(?Model $user, ?array $rows = null, string $extension = 'csv'): Import
    {
        $path = $this->spreadsheet($rows ?? $this->productRows(), $extension);

        return ProductsImporter::make()->upload($this->uploadedFile($path, "ބަރުދަލު.{$extension}"), $user, 'products');
    }

    protected function completedImport(?Model $user): Import
    {
        $import = $this->draftImport($user);

        $path = "imports/{$import->uuid}/failures.csv";
        Storage::disk('imports')->put($path, "Row,SKU,Errors\n3,A-2,The Name field is required.\n");

        $result = new ImportResult;
        $result->processed = 2;
        $result->created = 2;
        $result->fail(new RowFailure(3, ['name' => ['The Name field is required.']], ['A-2', '', '-1', 'y']));

        $import->forceFill(['status' => ImportStatuses::PROCESSING])->save();
        $import->markCompleted($result, $path);

        return $import;
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function ($router) {
            $router->imports();
            $router->imports(['guard' => 'web_admin', 'prefix' => 'admin/imports', 'name' => 'admin.imports']);
        });
    }
}
