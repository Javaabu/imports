<?php

namespace Javaabu\Imports\Commands;

use Illuminate\Console\Command;

class CreateImportCommand extends Command
{
    protected $signature = 'imports:make {table_name}';

    protected $description = 'Create a new import class';

    public function handle(): void
    {
        $table_name = $this->argument('table_name');
        $importer = file_get_contents(__DIR__ . '/../stubs/import.stub');
        $importer_classname = str($table_name)->singular()->studly()->replace('_', '')->toString() . 'Importer';
        $importer = str_replace([
            '{{ ModelName }}',
            '{{ ImporterClassName }}'
        ], [
            str($table_name)->singular()->studly()->replace('_', '')->toString(),
            $importer_classname,
        ], $importer);

        $target_directory = app_path('Imports/Importers');
        if (!is_dir($target_directory)) {
            mkdir($target_directory, 0755, true);
        }

        $path = app_path("Imports/Importers/$importer_classname.php");
        file_put_contents($path, $importer);

        $this->components->info("Importer created successfully at $path");
    }
}
