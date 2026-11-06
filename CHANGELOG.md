# Changelog

All notable changes will be documented in this file.

## 2.0.0 - Unreleased

- Requires PHP 8.4 and Laravel 12 or 13.
- Replaced maatwebsite/excel with OpenSpout. See `docs/upgrading.md`.
- Importers are definitions: extend `ModelImporter` (or `Importer` for anything that isn't a model) and describe `columns()` with `Column::make()`: labels, rules, required, aliases for matching headings, defaults, transforms, types (boolean, integer, number, date, datetime, string, enum by value or label) and lookups of related models by name or code (optionally creating missing ones).
- Model importers without `columns()` take them from the table: no keys, timestamps, hidden attributes or secrets; types from casts and column types; required from NOT NULL; foreign keys become lookups through their BelongsTo. The first single-column unique index finds existing records (`uniqueBy()`).
- Rows matching an existing record are skipped, updated or failed (`duplicates()`); empty cells don't overwrite values.
- Files are streamed with OpenSpout and saved in chunks, one transaction per chunk and a savepoint per row. Invalid rows don't stop an import: they're counted, kept as samples and written to a failure report with their row numbers and errors.
- `preview()` shows headings, sample values, the automatic (or given) mapping, unmapped required columns and the first rows validated with what would happen to them, without writing anything. `dryRun()` validates a whole file without saving.
- Templates with headings and examples: `template()` and `downloadTemplate()`.
- Imports run on the queue and are tracked by an `Import` model (status, the uploaded file, its headings and mapping, counts of created, updated, skipped and failed rows, the first failures, the failure report, the user morph). `upload()` stores a draft to map and preview first; `queue()` starts straight away. `ProcessImport` counts the rows, saves progress after each chunk, stops when cancelled, stores the failure report next to the upload and notifies the user (mail by default) with a signed link to it.
- Import events: `ImportStarted`, `ImportProgressed`, `ImportCompleted`, `ImportFailed`, `ImportCancelled`, broadcast on private channels when Reverb is installed.
- `imports:prune` deletes expired drafts and the files of expired imports; `--days` also deletes old records.
- `imports:make` writes a model importer with the columns worked out from the table. The stub lives in `stubs/` and can be published.
- `Route::imports()` registers the imports routes (index, upload, template, show, preview, run, report, cancel, destroy, signed report) with the app's prefix, name, guard and middleware. `ImportsController` answers JSON or renders pages, and can be replaced from config, the macro or the container.
- `ImportPolicy` and the permissions `view_imports`, `view_others_imports`, `delete_imports`, `delete_others_imports`, registered with javaabu/permissions. Only the uploader maps and runs a draft; which importers a user may run is each importer's `authorize()` (for model importers, `create` on the model). Imports belong to any user model through a morph; nothing assumes the `web` guard.
- `Imports::register('products', ProductsImporter::class)` lets the imports page run an importer by name (`registerImports()` still works).
- Imports pages in Blade (javaabu/forms v2 components) and Inertia React (TypeScript, @javaabu/forms and @javaabu/onu, shipped through javaabu/manifest), chosen with `imports.views`: upload with template links, the mapping step with sample values, automatic matches and a preview of the first rows, and live progress, counts and failed rows. Components `ColumnMapping`, `PreviewTable`, `ImportProgress`, `ImportCounts`, `ImportActions`, `FailuresTable` and `useImportProgress` can be imported as `@javaabu/imports`.
- English and Dhivehi translations for every string; pages and XLSX reports are right-to-left in Dhivehi.
- Docs for people and agents in `docs/`, `llms.txt`, a Laravel Boost guideline and skill, and `AGENTS.md`.
- Uploads that can't be read, or have no headings, are rejected with a validation error on `file`.
- The npm packages of the Inertia React pages (`@javaabu/forms`, `@javaabu/onu`, React, `@inertiajs/react`) are declared for pages only, so `php artisan manifest:check` doesn't ask Blade apps to install them.
