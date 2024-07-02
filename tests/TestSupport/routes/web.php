<?php


use Illuminate\Support\Facades\Route;
use Javaabu\Imports\Http\Controllers\ImportsController;

Route::middleware(['web', 'auth'])
    ->group(function () {
        Route::get('import', [ImportsController::class, 'index'])->name('imports.index');
        Route::post('import', [ImportsController::class, 'store'])->name('imports.store');
    });
