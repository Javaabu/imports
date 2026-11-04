<?php

namespace Javaabu\Imports;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Javaabu\Imports\Models\Import;

/**
 * Package-wide settings and the registry of importers the imports page can run by name
 */
class Imports
{
    /** Whether the package loads its migrations. Turn off with ignoreMigrations(). */
    public static bool $runs_migrations = true;

    /** @var array<string, class-string<Importer>> */
    protected static array $registered = [];

    /**
     * Let the imports page and API run an importer by name
     *
     * ```php
     * Imports::register('products', ProductsImporter::class);
     * ```
     *
     * @param  string|array<string, class-string<Importer>>  $type
     * @param  class-string<Importer>|null  $importer_class
     */
    public static function register(string|array $type, ?string $importer_class = null): void
    {
        if (is_array($type)) {
            foreach ($type as $name => $class) {
                static::register($name, $class);
            }

            return;
        }

        if (! $importer_class || ! is_a($importer_class, Importer::class, true)) {
            throw new InvalidArgumentException("The importer for [{$type}] must extend " . Importer::class . '.');
        }

        static::$registered[$type] = $importer_class;
    }

    /**
     * The v1 name of register()
     *
     * @param  array<string, class-string<Importer>>  $importables
     *
     * @deprecated use register()
     */
    public static function registerImports(array $importables): void
    {
        static::register($importables);
    }

    /**
     * @return array<string, class-string<Importer>>
     */
    public static function registered(): array
    {
        return static::$registered;
    }

    /**
     * @return class-string<Importer>|null
     */
    public static function importerClass(string $type): ?string
    {
        return static::$registered[$type] ?? null;
    }

    /**
     * The registered name of an importer class, if it has one
     */
    public static function typeOf(Importer|string $importer): ?string
    {
        $class = is_string($importer) ? $importer : $importer::class;

        $type = array_search($class, static::$registered, true);

        return $type === false ? null : $type;
    }

    public static function forget(?string $type = null): void
    {
        if ($type === null) {
            static::$registered = [];

            return;
        }

        unset(static::$registered[$type]);
    }

    /**
     * The Import model class, from `config('imports.models.import')`
     *
     * @return class-string<Import>
     */
    public static function importModel(): string
    {
        return config('imports.models.import', Import::class);
    }

    public static function newImportModel(): Import
    {
        $class = static::importModel();

        return new $class;
    }

    /**
     * Don't load the package's migrations; publish them with `--tag=imports-migrations` instead
     */
    public static function ignoreMigrations(): void
    {
        static::$runs_migrations = false;
    }

    /**
     * The morph type and key of a user, for querying imports by owner
     *
     * @return array{0: string, 1: mixed}
     */
    public static function ownerKey(Model $user): array
    {
        return [$user->getMorphClass(), $user->getKey()];
    }
}
