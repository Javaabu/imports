# Imports

[![Latest Version on Packagist](https://img.shields.io/packagist/v/javaabu/imports.svg?style=flat-square)](https://packagist.org/packages/javaabu/imports)
[![Test Status](../../actions/workflows/run-tests.yml/badge.svg)](../../actions/workflows/run-tests.yml)
![Code Coverage Badge](./.github/coverage.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/javaabu/imports.svg?style=flat-square)](https://packagist.org/packages/javaabu/imports)

## Introduction
Streamline excel data import to your application

## Documentation

You'll find the documentation on [https://docs.javaabu.com/docs/imports](https://docs.javaabu.com/docs/imports).

Find yourself stuck using the package? Found a bug? Do you have general questions or suggestions for improving this package? Feel free to create an [issue](../../issues) on GitHub, we'll try to address it as soon as possible.

If you've found a bug regarding security please mail [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Usage
Install the package via composer:

```bash
composer require javaabu/imports
```

Create your controller and use the `ImportsData` trait
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Javaabu\Imports\Traits\ImportsData;

class ImportsController extends Controller
{
    use ImportsData;
}
```

Register your routes
```php
    Route::get('import', [ImportsController::class, 'index'])->name('imports.index');
    Route::post('import', [ImportsController::class, 'store'])->name('imports.store');
```

For more customization you can override the methods in `ImportsData` trait.

## Testing

You can run the tests with

``` bash
./vendor/bin/phpunit
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Credits

- [Javaabu Pvt. Ltd.](https://github.com/javaabu)
- [Hussain Afeef (@ibnnajjaar)](https://github.com/ibnnajjaar)
- [Arushad Ahmed (@dash8x)](http://arushad.com)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
