<?php

namespace Javaabu\Imports;

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
}
