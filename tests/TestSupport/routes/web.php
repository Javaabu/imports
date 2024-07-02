<?php

use Illuminate\Support\Facades\Route;
use Javaabu\Imports\Tests\TestSupport\Controllers\ImportsController;

Route::middleware(['web'])
    ->group(function () {
        Route::get('import', [ImportsController::class, 'index'])->name('imports.index');
        Route::post('import', [ImportsController::class, 'store'])->name('imports.store');
    });
