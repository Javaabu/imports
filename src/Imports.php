<?php

namespace Javaabu\Imports;

use Illuminate\Support\Facades\Route;
use Javaabu\Imports\Http\Controllers\ImportsController;

class Imports
{

    protected static array $importables = [];

    public static function registerImports(array $importables): void
    {
        static::$importables = $importables;
    }

    public static function getImportables(): array
    {
        return static::$importables;
    }

    public static function registerRoutes(): void
    {
        Route::get('import', [ImportsController::class, 'index'])->name('imports.index');
        Route::post('import', [ImportsController::class, 'store'])->name('imports.store');
    }
}
