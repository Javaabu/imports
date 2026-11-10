---
title: Introduction
sidebar_position: 1.0
---

# Imports

[javaabu/imports](https://github.com/Javaabu/imports) imports CSV, XLSX and ODS spreadsheets into your Laravel app.

- **Preview before anything is written.** Upload a file, see its headings with sample values, check which field each column fills (matched automatically, from attribute names, labels in English or Dhivehi, and aliases), and see the first rows validated, with their errors and whether each would be created, updated or skipped. Then run it.
- **Importers are definitions.** Describe each model's columns once: labels, rules, required columns, defaults, transforms, types (Yes/No, numbers, dates, enums by value or label) and lookups of related models by name or code. Without a definition, the columns come from the table.
- **Existing records** are found by a unique key and skipped, updated or failed.
- **Streaming.** Files are read with [OpenSpout](https://github.com/openspout/openspout) a row at a time and saved in chunks, so memory stays flat. 200,000 rows import in under a minute with about 4 MB of memory.
- **Partial failures.** A bad row never stops an import. It's counted and written, with its row number and errors, to a report people fix and import again.
- **Background imports.** Imports run on the queue with progress, cancellation and notifications, tracked by an `Import` model.
- **An imports page and API.** Routes, an overridable controller, a JSON API and Blade or Inertia React pages with live progress (Laravel Reverb when installed, polling otherwise), a policy and permissions through [javaabu/permissions](https://github.com/Javaabu/permissions), for any guard and user model.
- **Dhivehi.** Every string is translated, and pages and reports work right-to-left.

```php
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
            Column::make('name')->required(),
            Column::make('price')->number()->rules('min:0')->default(0),
            Column::make('brand_id')->label(__('Brand'))->lookup(Brand::class, ['name', 'code']),
        ];
    }
}

// Check a file without saving anything
$preview = ProductsImporter::make()->preview($request->file('file'));

// Import it now
$result = ProductsImporter::make()->duplicates('update')->import($request->file('file'));

// Or on the queue, for big files
$import = ProductsImporter::make()->queue($request->file('file'), $request->user());
```

Start with [Installation & Setup](installation-and-setup.md), then [Writing importers](basic-usage/writing-importers.md). Coming from v1? Read [Upgrading](upgrading.md).
