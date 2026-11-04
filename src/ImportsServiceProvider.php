<?php

namespace Javaabu\Imports;

use Illuminate\Support\ServiceProvider;

class ImportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/imports.php', 'imports');
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/imports.php' => config_path('imports.php'),
        ], 'imports-config');
    }
}
