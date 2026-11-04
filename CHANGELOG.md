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
