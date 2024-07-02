<?php

namespace Javaabu\Imports\Tests\TestSupport\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Javaabu\Imports\Tests\TestSupport\Models\User;

class TestServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->loadMigrationsFrom([
            __DIR__.'/../database',
        ]);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Relation::morphMap([
            'user' => User::class,
        ]);
    }

    /**
     * Register the application services.
     */
    public function register()
    {

    }
}
