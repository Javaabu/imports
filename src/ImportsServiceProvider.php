<?php

namespace Javaabu\Imports;

use Illuminate\Support\ServiceProvider;
use Javaabu\Imports\Commands\CreateImportCommand;

class ImportsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        // declare publishes
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/imports.php' => config_path('imports.php'),
            ], 'imports-config');
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'imports');

        $this->commands([
            CreateImportCommand::class,
        ]);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // merge package config with user defined config
        $this->mergeConfigFrom(__DIR__ . '/../config/imports.php', 'imports');
    }
}
