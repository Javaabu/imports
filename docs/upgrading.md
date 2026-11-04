---
title: Upgrading from v1
sidebar_position: 1.25
---

v2 is a rewrite. Importers are definitions, files are streamed, and imports have a preview step, a model, a queue job and a page. Plan to rewrite each importer; most become shorter.

# Requirements

- PHP 8.4 and Laravel 12 or 13.
- **maatwebsite/excel is no longer used.** PhpSpreadsheet loads whole files into memory and doesn't install on PHP 8.5; v2 reads with OpenSpout, a row at a time. Remove `maatwebsite/excel` if nothing else uses it.
- javaabu/forms v2, javaabu/permissions v3 and javaabu/manifest are required. javaabu/helpers isn't.

# Importers

v1 importers extended `Javaabu\Imports\Importers\Importer` and implemented five methods. In v2 they extend `Javaabu\Imports\ModelImporter`:

| v1 | v2 |
| --- | --- |
| `headings()` | `columns()`: `Column::make('name')->label(...)`. Headings are matched to columns automatically, or chosen on the mapping step. |
| `dummyData()` | `Column::example()`; templates come from `template()` / `downloadTemplate()` |
| `rowValidationRules(array $row)` | `Column::rules()` and `required()`, plus `rules()` for rules across columns |
| `rowValidationMessages()`, `rowValidationCustomAttributes()` | `messages()`; attribute names are the column labels |
| `getExistingModel(array $row)` | `uniqueBy()` (`['sku']`), or override `existingModel(array $data)` |
| `saveRow(array $row, ?Model $existing)` | Nothing for plain attributes. Use `defaults()`, `beforeSave()` / `afterSave()` for the rest, or override `persist()` |
| `$overwrite_duplicates` | `duplicates('skip' | 'update' | 'fail')` |
| `$trim_except` | `Column::withoutTrimming()` |
| `canImport(?User $user)` | `authorize(?Model $user)`; the default checks `create` on the model |
| `$meta`, constructor arguments | Constructor arguments of your own: `ProductsImporter::make($store_id)` (keep them serializable) |
| `$queue_threshold`, `TooManyRowsException` | Gone. Use `import()` for small files in code; the imports page always queues |
| `ImportValidationException`, `DownloadableImportValidationException`, `ErrorsExport` | Gone. Invalid rows don't stop an import: they're in `ImportResult::$failures` and the failure report |
| `ImportResultNotification`, `ImportValidationErrorsNotification`, `ImportFailedNotification` | `ImportCompletedNotification` (with a link to the failed rows) and `ImportFailedNotification` |

Before:

```php
class ProductImporter extends Importer
{
    public function headings(): array
    {
        return ['Slug *', 'Name', 'Brand'];
    }

    public function dummyData(): array
    {
        return [['tv', 'TV', 'sony']];
    }

    public function rowValidationRules(array $row): array
    {
        return [
            'slug' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'exists:brands,slug'],
        ];
    }

    public function getExistingModel(array $row): ?Model
    {
        return Product::where('slug', $row['slug'])->first();
    }

    public function saveRow(array $row, ?Model $existing_model = null): Model
    {
        $model = $existing_model ?: new Product;
        $model->slug = $row['slug'];
        $model->name = $row['name'];
        $model->brand_id = Brand::where('slug', $row['brand'])->value('id');
        $model->save();

        return $model;
    }
}
```

After:

```php
class ProductsImporter extends ModelImporter
{
    public function modelClass(): string
    {
        return Product::class;
    }

    public function columns(): array
    {
        return [
            Column::make('slug')->required()->rules('string|max:255')->example('tv'),
            Column::make('name')->required()->rules('string|max:255')->example('TV'),
            Column::make('brand_id')->label(__('Brand'))->lookup(Brand::class, 'slug')->example('sony'),
        ];
    }

    public function uniqueBy(): array
    {
        return ['slug'];
    }
}
```

Lookups replace `exists:` rules and the query in `saveRow()`: they resolve a whole chunk with one query and report values that match nothing.

`php artisan imports:make ProductsImporter --model=Product` writes an importer with the columns worked out from the table. The stub moved from `src/stubs` to `stubs/` and can be published.

# Registering and the page

| v1 | v2 |
| --- | --- |
| `Imports::registerImports(['product' => ProductImporter::class])` | `Imports::register('products', ProductsImporter::class)` (`registerImports()` still works) |
| Your controller with the `ImportsData` trait and `ImportsRequest` | `Route::imports()` with the package's controller; extend `ImportsController` to change it |
| `imports::material-admin.imports.index` | `imports::imports.index` and `imports::imports.show` (javaabu/forms v2), or the Inertia pages |
| The `import` ability on each model | `view_imports` to use the page, and `create` on the model to run its importer (override `authorize()` to keep using `import`) |

Run the migration and `permissions:sync`, then [set up the page](installation-and-setup.md#the-imports-page).

# Behaviour changes

- Empty rows are skipped; row numbers in errors match the spreadsheet.
- Text is trimmed and blank cells are null. On update, blank cells leave values alone unless the column `clearsWhenEmpty()`.
- Rows are saved in chunks of 500, each chunk in a transaction and each row in a savepoint. Model events still fire.
- `ImportResult` replaces the `import_result` array (`created`, `updated`, `skipped`, `failed`, `failures`).
