<?php

namespace Javaabu\Imports\Tests\TestSupport\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Javaabu\Imports\Tests\TestSupport\Models\Admin;
use Javaabu\Imports\Tests\TestSupport\Models\Brand;
use Javaabu\Imports\Tests\TestSupport\Models\Category;
use Javaabu\Imports\Tests\TestSupport\Models\Product;
use Javaabu\Imports\Tests\TestSupport\Models\User;

class TestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom([
            __DIR__ . '/../database',
        ]);

        View::addLocation(__DIR__ . '/../views');

        Relation::morphMap([
            'user' => User::class,
            'admin' => Admin::class,
            'brand' => Brand::class,
            'category' => Category::class,
            'product' => Product::class,
        ]);
    }
}
