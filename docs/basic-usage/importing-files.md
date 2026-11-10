---
title: Importing files
sidebar_position: 2
---

Importers read CSV, XLSX and ODS files (and `.txt` as CSV), from a path or an `UploadedFile`. The format comes from the file name; pass it when the name has no extension.

# Previewing

```php
$preview = ProductsImporter::make()->preview($request->file('file'));

$preview->isReady();        // every required column is mapped
$preview->missing;          // ['name']: required columns that aren't
$preview->mapping->toArray(); // [0 => 'sku', 1 => 'name']: heading index => attribute
$preview->invalidCount();   // rows with errors among those checked
$preview->invalidRows();    // [['row' => 3, 'values' => [...], 'errors' => ['name' => ['The Name field is required.']], ...]]
$preview->toArray();        // everything, for an API or a page
```

A preview reads the headings, three sample values of each column (`imports.preview.samples`), and validates the first 100 rows (`imports.preview.rows`, or `preview($file, rows: 20)`), saying for each valid row whether it would be `created`, `updated` or `skipped`. It writes nothing: lookups that `createMissing()` don't create.

# Mapping

Headings are matched to columns ignoring case, spacing, punctuation and the ` *` templates add to required columns, comparing them with the attribute (`brand_id`, `brand`), the label in the current locale and in English, and the aliases. Files in Dhivehi match Dhivehi labels.

Choose the mapping yourself by heading text or position; columns left out aren't imported:

```php
ProductsImporter::make()
    ->map(['Item code' => 'sku', 'Description' => 'name', 'Notes' => null])
    ->import($path);

ProductsImporter::make()->map([0 => 'sku', 2 => 'name'])->import($path);
```

A mapping that names a heading the file doesn't have, a field the importer doesn't have, or a field twice throws `InvalidMappingException` (a `ValidationException`, so it becomes a 422 in requests). So does running without the required columns.

# Importing

```php
$result = ProductsImporter::make()->import($path);

$result->created;   // 1,180
$result->updated;   // 12
$result->skipped;   // 3
$result->failed;    // 5
$result->processed; // 1,200
$result->failures;  // the first 100 RowFailure: ->row, ->errors, ->messages(), ->values
```

`import()` runs during the request. Rows are read and saved a chunk at a time (500 by default); each chunk is a transaction and each row a savepoint, so a row that fails rolls back alone, and the import carries on. Use [`queue()`](queued-imports.md) for anything larger than a few thousand rows.

# The failure report

```php
$result = ProductsImporter::make()->import($path, report_path: storage_path('app/failures.xlsx'));

$result->report_path; // the path, or null when no row failed
```

The report has a `Row` column, the row as it was uploaded (with its headings, so it imports again once fixed) and an `Errors` column. It's written in the uploaded file's format (`imports.report_format` to change that), as rows fail, so memory stays flat. In CSV reports, values a spreadsheet would run as a formula are prefixed with `'`.

# Dry runs

```php
$result = ProductsImporter::make()->dryRun()->import($path);
```

validates every row and counts what would happen, without saving.

# Templates

```php
return ProductsImporter::make()->downloadTemplate('xlsx'); // products-template.xlsx

$path = ProductsImporter::make()->template('csv', storage_path('app/template.csv'));
```

A template has the columns' labels (` *` after required ones) and, when columns have `example()` values, an example row.

# Testing

Write a file in the test and import it:

```php
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer;

$path = tempnam(sys_get_temp_dir(), 'import') . '.csv';
$writer = new Writer;
$writer->openToFile($path);
$writer->addRow(Row::fromValues(['SKU', 'Name', 'Brand']));
$writer->addRow(Row::fromValues(['TV-1', 'ޓީވީ', 'Sony']));
$writer->close();

$result = ProductsImporter::make()->import($path);

$this->assertSame(1, $result->created);
```
