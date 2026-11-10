# Imports

[![Tests](https://github.com/Javaabu/imports/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Javaabu/imports/actions/workflows/tests.yml?query=branch%3Amain)
[![codecov](https://codecov.io/gh/Javaabu/imports/branch/main/graph/badge.svg)](https://codecov.io/gh/Javaabu/imports/tree/main)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/javaabu/imports.svg)](https://packagist.org/packages/javaabu/imports)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

Spreadsheet imports for Laravel. Upload a CSV, XLSX or ODS file, see how its columns map and which rows would fail, then import it, however big it is: 200,000 rows take under a minute and about 4 MB of memory.

- Preview first: headings with sample values, columns matched to fields automatically (in English or Dhivehi), and the first rows validated, with what would happen to each, before anything is written.
- Importers are definitions: columns with labels, rules, defaults, types (Yes/No, numbers, dates, enums) and lookups of related models by name or code. Without one, the columns come from the table.
- Existing records are skipped, updated or failed by a unique key.
- A bad row never stops an import: it goes to a downloadable report with its row number and errors.
- Background imports with progress, cancellation and notifications; an imports page and JSON API, in Blade or Inertia React, with live progress over Laravel Reverb (or polling).
- A policy and permissions through javaabu/permissions, for any guard and user model; Dhivehi translations and right-to-left pages.

```php
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
            Column::make('sku')->required()->aliases(['code']),
            Column::make('name')->required(),
            Column::make('price')->number()->default(0),
            Column::make('brand_id')->label(__('Brand'))->lookup(Brand::class, ['name', 'code']),
        ];
    }
}

$preview = ProductsImporter::make()->preview($request->file('file'));
$result = ProductsImporter::make()->duplicates('update')->import($request->file('file'));

// big files go on the queue; the user is notified when it's done
ProductsImporter::make()->queue($request->file('file'), $request->user());
```

## Documentation

You'll find the documentation on [https://docs.javaabu.com/imports](https://docs.javaabu.com/imports), and in the [`docs`](docs) folder. Upgrading from v1? Read [`docs/upgrading.md`](docs/upgrading.md).

Find yourself stuck using the package? Found a bug? Do you have general questions or suggestions for improving this package? Feel free to create an [issue](../../issues) on GitHub, we'll try to address it as soon as possible.

If you've found a bug regarding security please mail [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Testing

```bash
composer test
composer lint
composer benchmark   # 200,000 rows
pnpm install && pnpm check   # the React pages
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Credits

- [Javaabu Pvt. Ltd.](https://github.com/javaabu)
- [Arushad Ahmed (@dash8x)](http://arushad.com)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
