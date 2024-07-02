<?php

namespace Javaabu\Imports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Str;

abstract class ImportsRepository
{

    /**
     * Get importables list.
     * Optionally filter only models that the
     * given user can import
     */
    public static function getImportablesList(?User $user = null): array
    {
        $list = [];
        $importables = Imports::getImportables();

        foreach ($importables as $model_slug => $importer) {
            if (empty($user) || $importer::canImport($user)) {
                $list[$model_slug] = __(Str::plural(slug_to_title($model_slug)));
            }
        }

        return $list;
    }

    /**
     * Check if the given user can import
     * any model type
     */
    public static function canImportAny(User $user): bool
    {
        $importables = Imports::getImportables();

        foreach ($importables as $model_slug => $importer) {
            if ($importer::canImport($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the instantiated importer from the model class
     *
     * @param Notifiable|null $notifiable
     * @return Importer
     */
    public static function getImporter(
        $model_slug,
        bool $overwrite_duplicates = false,
        $error_handler = null,
        $notifiable = null,
        array $meta = null
    ) {
        $importable_class = Imports::getImportables()[$model_slug];

        return new $importable_class($overwrite_duplicates, $error_handler, $notifiable, $model_slug, $meta);
    }

    /**
     * Get the model class from the importer
     *
     * @param $importer_class
     * @return string
     */
    public static function getModelClass($importer_class)
    {
        $importables = Imports::getImportables();

        foreach ($importables as $model_slug => $importer) {
            if ($importer == $importer_class) {
                return Model::getActualClassNameForMorph($model_slug);
            }
        }

        return null;
    }

}
