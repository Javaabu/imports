<?php

namespace Javaabu\Imports\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Schema\ModelSchema;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * `php artisan imports:make ProductsImporter --model=Product`
 *
 * Writes a model importer whose columns() lists the columns worked out from the table, ready
 * to adjust.
 */
class MakeImporterCommand extends GeneratorCommand
{
    protected $name = 'imports:make';

    protected $description = 'Create a model importer class';

    protected $type = 'Importer';

    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/vendor/imports/importer.stub');

        return file_exists($published) ? $published : __DIR__ . '/../../stubs/importer.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Imports';
    }

    protected function buildClass($name): string
    {
        $model = $this->modelClass($name);

        return str_replace(
            ['{{ modelNamespace }}', '{{ model }}', '{{ columns }}'],
            [Str::beforeLast($model, '\\'), class_basename($model), $this->columns($model)],
            parent::buildClass($name),
        );
    }

    /**
     * @return class-string<Model>|string
     */
    protected function modelClass(string $name): string
    {
        $model = $this->option('model')
            ?: Str::singular(Str::beforeLast(class_basename($name), 'Importer'));

        $model = str_replace('/', '\\', $model);

        if (! str_contains($model, '\\')) {
            $model = $this->qualifyModel($model);
        }

        return ltrim($model, '\\');
    }

    protected function columns(string $model): string
    {
        $lines = [];

        try {
            $columns = class_exists($model) ? ModelSchema::for($model)->columns() : [];
        } catch (Throwable) {
            $columns = [];
        }

        foreach ($columns as $column) {
            $lines[] = '            ' . $this->columnCode($column) . ',';
        }

        return $lines ? implode("\n", $lines) : "            Column::make('name')->required(),";
    }

    protected function columnCode(Column $column): string
    {
        $code = "Column::make('{$column->attribute()}')";

        if ($column->isRequired()) {
            $code .= '->required()';
        }

        if ($lookup = $column->getLookup()) {
            $by = implode("', '", $lookup->by());
            $code .= '->lookup(\\' . $lookup->model() . "::class, ['{$by}'])";
        } elseif ($enum = $column->getEnum()) {
            $code .= '->enum(\\' . $enum . '::class)';
        } elseif ($type = $column->getType()) {
            $code .= "->{$type}()";
        }

        if ($rules = $column->ownRules()) {
            $code .= "->rules('" . implode('|', array_filter($rules, 'is_string')) . "')";
        }

        return $code;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model the importer imports'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the importer if it exists'],
        ];
    }
}
