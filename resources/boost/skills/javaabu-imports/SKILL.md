---
name: javaabu-imports
description: Import CSV, XLSX or ODS spreadsheets into a Laravel app with javaabu/imports. Use whenever the project depends on javaabu/imports and the task writes or changes an importer, maps or validates spreadsheet columns, looks up related models from file values, adds an upload or import button, previews a file before importing, imports a large file or queues an import, shows import progress or failed rows, sets up the imports page or API, or changes who may import.
license: MIT
metadata:
  author: Javaabu
---

# Imports with javaabu/imports

Imports stream: files are read a row at a time with OpenSpout and saved in chunks, and a bad row never stops an import; it goes to a failure report. The docs ship with the package: `vendor/javaabu/imports/llms.txt` lists every page in `vendor/javaabu/imports/docs/`.

## Writing an importer

```bash
php artisan imports:make ProductsImporter --model=Product
```

```php
namespace App\Imports;

use App\Enums\ProductStatuses;
use App\Models\Brand;
use App\Models\Product;
use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\ModelImporter;

class ProductsImporter extends ModelImporter
{
    public function modelClass(): string
    {
        return Product::class;
    }

    public function columns(): array
    {
        return [
            Column::make('sku')->label(__('SKU'))->required()->rules('string|max:50')->aliases(['code']),
            Column::make('name')->required()->string(),
            Column::make('price')->number()->rules('min:0')->default(0),
            Column::make('status')->enum(ProductStatuses::class),           // value, name or label
            Column::make('is_active')->label(__('Active'))->boolean(),      // Yes/No, 1/0, އާނ/ނޫން
            Column::make('released_on')->date(),                            // date cells, serials, text
            Column::make('brand_id')->label(__('Brand'))->lookup(Brand::class, ['name', 'code']),
        ];
    }

    public function uniqueBy(): array
    {
        return ['sku'];
    }
}
```

Without `columns()`, the columns, types, required flags, lookups (from `BelongsTo` relations) and `uniqueBy()` (the first unique index) come from the table. Often a model importer needs nothing but `modelClass()`.

## Using it

```php
// Check a file first: headings, samples, the automatic mapping, the first rows' errors
$preview = ProductsImporter::make()->preview($request->file('file'));

// Small files, now
$result = ProductsImporter::make()->duplicates('update')->import($request->file('file'));
$result->created; $result->updated; $result->skipped; $result->failed; $result->failures;

// Big files, on the queue; the user is notified with a link to the failed rows
$import = ProductsImporter::make()->queue($request->file('file'), $request->user());

// Choose the mapping yourself (heading text or index => attribute)
ProductsImporter::make()->map(['Item code' => 'sku', 'Title' => 'name'])->import($path);

// Templates
return ProductsImporter::make()->downloadTemplate('xlsx');
```

## The imports page

```php
// routes/web.php, inside the app's group
Route::imports();
Route::imports(['guard' => 'web_admin', 'prefix' => 'admin/imports', 'name' => 'admin.imports']);

// AppServiceProvider::boot()
Imports::register('products', ProductsImporter::class);
```

Upload → map columns (automatic, with sample values) → preview the first rows → run → live progress, counts and failed rows. Permissions: `view_imports`, `view_others_imports`, `delete_imports`, `delete_others_imports` (`php artisan permissions:sync`); running an importer also needs its `authorize($user)` (default: `create` on the model). Schedule `imports:prune` daily.

## Rules

- Use `lookup()` for foreign keys, not `exists:` rules or queries in hooks; use `uniqueBy()` + `duplicates()` for existing records, not `unique:` rules or per-row queries.
- Use the column types (`boolean()`, `number()`, `date()`, `enum()`) instead of parsing cells yourself.
- Fail a row from a hook with `throw RowFailedException::withMessage(__('…'), 'attribute')`; never abort the whole import.
- Load what a chunk needs in `beforePersist(array $rows)`, not per row in `beforeSave()`/`afterSave()`.
- Use `queue()` beyond a few thousand rows; `import()` runs in the request.
- Keep constructor arguments small and serializable; add `fromRequest(Request $request)` for the imports page.
- Labels go through `__()`: add Dhivehi to `lang/dv.json`. Use Thaana in tests.
- Don't use maatwebsite/excel (`ToCollection`, `WithHeadingRow`, `Excel::import()`) or the v1 methods (`rowValidationRules()`, `getExistingModel()`, `saveRow()`).

See [references/common-mistakes.md](references/common-mistakes.md), and [references/queued-imports.md](references/queued-imports.md) for the Import model, the job, events and the API.
