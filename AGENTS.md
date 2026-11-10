# imports: notes for AI coding agents

`javaabu/imports` imports CSV, XLSX and ODS files into Laravel apps. Importers are definitions (`ModelImporter`/`Importer` with `Column`s); files are streamed with OpenSpout and saved in chunks; `preview()` shows the mapping and the first rows' errors before anything is written. Queued imports are tracked by an `Import` model with progress, failure reports, notifications and broadcasting. Routes with an overridable controller and JSON API, Blade and Inertia React pages, and a policy with permissions from `javaabu/permissions`. Apps subclass `ModelImporter` and `Importer`, so treat their public and protected methods, `Column`, `Imports`, `Route::imports()`, the `ImportResource` and `Preview::toArray()` JSON shapes, and the React components' props as public contracts.

## Layout

| Path | What |
| --- | --- |
| `src/Importer.php`, `src/ModelImporter.php` | The base classes: row pipeline (read, convert, look up, validate), hooks, `import()`, `preview()`, `queue()`, templates |
| `src/Columns` | `Column` definitions, `Lookup`, `ValueConverter` (types) |
| `src/Mapping` | `Mapping` (heading index => attribute) and `ColumnMatcher` (automatic matching) |
| `src/Processing` | `ImportRunner` (chunks, transactions, savepoints), `ImportResult`, `RowFailure`, `Preview`, `FailureReport` |
| `src/Schema/ModelSchema.php` | Default columns, types, lookups and unique key from the table |
| `src/Readers`, `src/Writers`, `src/Support` | OpenSpout reader and writer, temporary files, broadcasting |
| `src/Models/Import.php`, `src/Enums` | The Import model; `Formats`, `Duplicates`, `RowOutcomes`, `ImportStatuses` |
| `src/Jobs`, `src/Events`, `src/Notifications`, `src/Commands` | `ProcessImport`, events (broadcastable), notifications, `imports:make` and `imports:prune` |
| `src/Routing`, `src/Http`, `src/Policies`, `src/Views` | `Route::imports()`, controller, requests, resource, policy, `ViewRenderer` |
| `config/imports.php`, `database/migrations`, `stubs` | Options; the `imports` table; the `imports:make` stub |
| `resources/views` | Blade pages (javaabu/forms components) |
| `resources/js` | Inertia React pages and components (TypeScript), and the Blade pages' script, shipped through javaabu/manifest |
| `lang/en.json`, `lang/dv.json` | Translations; English strings are the keys |
| `docs`, `llms.txt` | Docusaurus pages for docs.javaabu.com and the index for AI assistants |
| `resources/boost` | Laravel Boost guideline and skill, shipped in the package |
| `tests` | Testbench tests; two guards (`web` → `User`, `web_admin` → `Admin`); `tests/Benchmark` is the 200k-row benchmark; `tests/js` are Vitest tests |

## Rules

- Follow Javaabu codestyle ([boost-javaabu-guidelines](https://github.com/Javaabu/boost-javaabu-guidelines)): snake_case variables and properties, camelCase methods, early returns, `#[Test]` and `it_…` test names. Pint enforces formatting.
- Memory must stay flat: never collect a file's rows, never buffer a report in memory. Per-row queries are bugs: resolve lookups and existing models per chunk. `composer benchmark` guards this.
- A bad row never stops an import: it becomes a `RowFailure`. Only real failures (the file can't be read, a bug) fail the job.
- Reports repeat user data: write strings as text and escape CSV formulas (the writer does; don't bypass it).
- Previews and dry runs must not write: no model saves, no `createMissing()` creations.
- Nothing may assume the `web` guard or `App\Models\User`. Type-hint users as `Model`; imports belong to users through the `user` morph. Test on both guards.
- Reverb and Inertia are optional: feature-detect them, keep them in `suggest`, and test both ways.
- Every user-facing string goes through `__()` with an English key, and gets a line in `lang/en.json` and `lang/dv.json` (`TranslationsTest` checks). Use Thaana in tests of user-facing behaviour. List uncertain translations in `docs/advanced-usage/translations.md`. Views use logical CSS only (`ms-`, `pe-`, `start-`), never `left`/`right`.
- Blade and React pages stay in step: a change to one goes in the other, with tests on both sides.
- Every change needs tests, docs (linked from `llms.txt`), the Boost skill if it changes how the package is used, and a line in `CHANGELOG.md`.
- Work on the `next-gen` branch; don't commit to `main`.

## Commands

```bash
composer install && pnpm install
composer lint       # vendor/bin/pint --test
composer format     # vendor/bin/pint
composer test       # vendor/bin/phpunit
composer benchmark  # 200k rows; IMPORTS_BENCHMARK_ROWS and IMPORTS_BENCHMARK_FORMAT to change
pnpm check          # typecheck and Vitest for resources/js
```
