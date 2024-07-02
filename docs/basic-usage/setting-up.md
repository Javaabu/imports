---
title: Setting Up
sidebar_position: 1.4
---

## Create Controller
First, create your imports controller and use the `ImportsData` trait.

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

## Register Routes
Register your routes in your route file.

```php
Route::get('import', [ImportsController::class, 'index'])->name('imports.index');
Route::post('import', [ImportsController::class, 'store'])->name('imports.store');
```

## Customize
For more customization you can override the methods in `ImportsData` trait.
Below are some of the methods you may want to override.

- `getStoreRouteUrl()` // default: 'admin.imports.store'
- `getIndexView()` // default: 'imports::material-admin.imports.index'
- `getLayoutsView()` // default: 'layouts.admin'

## Authorization
Since the imports controller is a standard controller, you can use Laravel's built-in authorization features to protect your routes. 
For example, you can use the can middleware in the controller's constructor.

```php
    public function __construct()
    {
        $this->middleware('can:view_imports');
    }
```

