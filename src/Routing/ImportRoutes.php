<?php

namespace Javaabu\Imports\Routing;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Javaabu\Imports\Http\Controllers\ImportsController;

/**
 * Registers the imports routes for `Route::imports()`
 *
 * ```php
 * Route::imports();                                                  // /imports, imports.*, auth
 * Route::imports(['guard' => 'web_admin', 'prefix' => 'admin/imports', 'name' => 'admin.imports']);
 * ```
 */
class ImportRoutes
{
    /** The route action key that holds the guard the routes were registered for */
    public const string GUARD_ACTION = 'imports_guard';

    /**
     * @param  array{
     *     guard?: string|null,
     *     prefix?: string,
     *     name?: string,
     *     middleware?: string|array<int, string>|null,
     *     controller?: class-string|null,
     *     signed?: bool,
     *     signed_middleware?: string|array<int, string>
     * }  $options
     */
    public static function register(Router $router, array $options = []): void
    {
        $guard = $options['guard'] ?? null;
        $prefix = trim($options['prefix'] ?? 'imports', '/');
        $name = rtrim($options['name'] ?? 'imports', '.');
        $middleware = $options['middleware'] ?? [$guard ? "auth:{$guard}" : 'auth'];
        $controller = $options['controller'] ?? config('imports.controllers.imports', ImportsController::class);

        $router->group([
            'prefix' => $prefix,
            'as' => "{$name}.",
            self::GUARD_ACTION => $guard,
        ], function (Router $router) use ($controller, $middleware, $options) {
            $router->group(['middleware' => $middleware], function (Router $router) use ($controller) {
                $router->get('/', [$controller, 'index'])->name('index');
                $router->post('/', [$controller, 'store'])->name('store');
                $router->get('/template/{type}', [$controller, 'template'])->name('template');
                $router->get('/{import}', [$controller, 'show'])->name('show');
                $router->post('/{import}/preview', [$controller, 'preview'])->name('preview');
                $router->post('/{import}/run', [$controller, 'run'])->name('run');
                $router->get('/{import}/report', [$controller, 'report'])->name('report');
                $router->post('/{import}/cancel', [$controller, 'cancel'])->name('cancel');
                $router->delete('/{import}', [$controller, 'destroy'])->name('destroy');
            });

            if ($options['signed'] ?? true) {
                $router->get('/{import}/signed-report', [$controller, 'signedReport'])
                    ->middleware($options['signed_middleware'] ?? ['signed'])
                    ->name('signed-report');
            }
        });
    }

    /**
     * The guard the current route was registered for, or null for the default guard
     */
    public static function guard(Request $request): ?string
    {
        return $request->route()?->getAction(self::GUARD_ACTION);
    }

    /**
     * The route name prefix of the current route, e.g. `admin.imports`
     */
    public static function namePrefix(Request $request): string
    {
        $name = (string) $request->route()?->getName();

        return str_contains($name, '.')
            ? substr($name, 0, strrpos($name, '.'))
            : 'imports';
    }
}
