<?php

namespace Javaabu\Imports\Commands;

use Illuminate\Console\Command;
use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Imports;
use Javaabu\Imports\Models\Import;

class PruneImportsCommand extends Command
{
    protected $signature = 'imports:prune
                            {--days= : Also delete import records created more than this many days ago}
                            {--pretend : List what would be deleted without deleting it}';

    protected $description = 'Delete expired drafts, and the files of expired imports (keeping their records)';

    public function handle(): int
    {
        $pretend = (bool) $this->option('pretend');
        $drafts = 0;
        $files = 0;
        $records = 0;

        Imports::importModel()::query()->expired()->lazyById()->each(function (Import $import) use ($pretend, &$drafts, &$files) {
            if ($import->status === ImportStatuses::DRAFT) {
                $this->line("Deleting draft import {$import->uuid} ({$import->filename})...");
                $pretend || $import->delete();
                $drafts++;

                return;
            }

            if (! $import->path && ! $import->report_path) {
                return;
            }

            $this->line("Deleting the files of import {$import->uuid} ({$import->filename})...");

            if (! $pretend) {
                $import->deleteFiles();
                $import->forceFill(['path' => null])->save();
            }

            $files++;
        });

        if ($days = (int) $this->option('days')) {
            Imports::importModel()::query()
                ->where('created_at', '<=', now()->subDays($days))
                ->whereNotIn('status', [ImportStatuses::PENDING->value, ImportStatuses::PROCESSING->value])
                ->lazyById()
                ->each(function (Import $import) use ($pretend, &$records) {
                    $this->line("Deleting import {$import->uuid} ({$import->filename})...");
                    $pretend || $import->delete();
                    $records++;
                });
        }

        $this->components->info(($pretend ? 'Would delete' : 'Deleted') . " {$drafts} drafts, the files of {$files} imports and {$records} import records.");

        return self::SUCCESS;
    }
}
