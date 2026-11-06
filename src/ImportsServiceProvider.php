<?php

namespace Javaabu\Imports;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Javaabu\Imports\Commands\MakeImporterCommand;
use Javaabu\Imports\Commands\PruneImportsCommand;
use Javaabu\Imports\Policies\ImportPolicy;
use Javaabu\Imports\Routing\ImportRoutes;
use Javaabu\Imports\Support\Broadcasting;
use Javaabu\Manifest\Concerns\RegistersAssets;
use Javaabu\Manifest\Package;
use Javaabu\Permissions\Permissions;

class ImportsServiceProvider extends ServiceProvider
{
    use RegistersAssets;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/imports.php', 'imports');
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'imports');

        $this->registerFrontendAssets();
        $this->registerRouteMacros();
        $this->registerPermissions();
        $this->registerBroadcastChannels();

        if ($this->app->runningInConsole()) {
            $this->registerMigrations();
            $this->registerPublishing();

            $this->commands([
                MakeImporterCommand::class,
                PruneImportsCommand::class,
            ]);
        }
    }

    /**
     * The Inertia pages (imports::Imports/Index), the components apps can import as
     *
     * @javaabu/imports, and the script the Blade pages load
     */
    protected function registerFrontendAssets(): void
    {
        $this->registerAssets('imports', __DIR__ . '/../resources', function (Package $package) {
            $package
                ->script('imports', 'js/imports.ts')
                ->js()
                ->pages()
                // The Blade script imports nothing from npm; only the Inertia pages and
                // the components apps import through @javaabu/imports need these
                ->npm([
                    '@javaabu/forms' => 'github:Javaabu/react-forms#next-gen',
                    '@javaabu/onu' => '^0.2.1',
                ], peer_dependencies: [
                    'react' => '^18.3 || ^19.0',
                    'react-dom' => '^18.3 || ^19.0',
                    '@inertiajs/react' => '^2.0 || ^3.0',
                ], for: 'pages');
        });
    }

    protected function registerRouteMacros(): void
    {
        Route::macro('imports', function (array $options = []) {
            ImportRoutes::register($this, $options);
        });
    }

    /**
     * view_imports, view_others_imports, delete_imports, delete_others_imports, and the
     * default policy, which the app can override
     */
    protected function registerPermissions(): void
    {
        if (! config('imports.register_permissions', true)) {
            return;
        }

        Permissions::register('imports', [
            'view_imports' => 'View and start own imports',
            'view_others_imports' => 'View other users\' imports',
            'delete_imports' => 'Cancel and delete own imports',
            'delete_others_imports' => 'Cancel and delete other users\' imports',
        ], label: 'Imports');

        Permissions::policy(Imports::importModel(), config('imports.policy', ImportPolicy::class));
    }

    /**
     * Authorize the progress channels when broadcasting is on (Reverb installed, or forced)
     */
    protected function registerBroadcastChannels(): void
    {
        if (! Broadcasting::enabled()) {
            return;
        }

        Broadcasting::registerChannels();
    }

    protected function registerMigrations(): void
    {
        if (Imports::$runs_migrations) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/imports.php' => config_path('imports.php'),
        ], 'imports-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'imports-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/imports'),
        ], 'imports-views');

        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/vendor/imports'),
        ], 'imports-stubs');
    }
}
