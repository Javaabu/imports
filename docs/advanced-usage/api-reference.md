---
title: API reference
sidebar_position: 20
---

# `Javaabu\Imports\ModelImporter`

Extends `Importer`. Imports rows as Eloquent models.

| Method | |
| --- | --- |
| `modelClass(): string` | **Abstract.** The model class |
| `columns(): array` | Default: from the table (`ModelSchema`) |
| `uniqueBy(): array` | Attributes that find existing models. Default: the first single-column unique index among the columns |
| `defaults(array $data): array` | Attributes for new models; closures get the row |
| `authorize(?Model $user): bool` | Default: `create` on the model |
| `label(): string` | Default: the model's plural, translated |
| `newModel(): Model` | |
| `existingQuery(): Builder` | The query existing models are found in |
| `existingModel(array $data): ?Model` | The existing model a row matches |
| `beforePersist(array $rows): void` | Loads the chunk's existing models |
| `persist(array $data, int $row): RowOutcomes` | Creates, updates or skips the model |
| `previewOutcome(array $data, int $row): RowOutcomes` | What `persist()` would do |
| `fill(Model $model, array $data, bool $exists)` | Protected. Sets defaults (new models) and non-empty values |
| `beforeSave(Model $model, array $data, int $row)`, `afterSave(...)` | Protected hooks |

# `Javaabu\Imports\Importer`

| Method | |
| --- | --- |
| `static make(...$arguments): static` | |
| `columns(): array` | **Abstract.** `Column`s or attribute names |
| `persist(array $data, int $row): RowOutcomes` | **Abstract.** Save one valid row |
| `label(): string` | `ProductsImporter` → `Products` |
| `authorize(?Model $user): bool` | Default: true |
| `rules(): array`, `messages(): array` | Extra validation rules by attribute, and messages |
| `prepareRow(array $data, int $row): array` | After reading, before validation |
| `beforePersist(array $rows): void` | Each chunk's valid rows, before saving |
| `previewOutcome(array $data, int $row): RowOutcomes` | Default: `CREATED` |
| `headingRow(): int`, `sheet(): int\|string`, `chunkSize(): int`, `connectionName(): ?string` | Reading settings |
| `map(array\|Mapping\|null $mapping): static` | Heading index or text => attribute |
| `duplicates(Duplicates\|string $duplicates): static`, `getDuplicates()` | |
| `dryRun(bool $dry_run = true): static`, `isDryRun()` | |
| `resolvedColumns(): array`, `column(string $attribute): ?Column` | Columns keyed by attribute |
| `resolveMapping(array $headings): Mapping` | The given or suggested mapping |
| `import($file, $format = null, ?string $report_path = null): ImportResult` | Import now |
| `preview($file, $format = null, ?int $rows = null): Preview` | |
| `queue($file, ?Model $user = null, ?string $type = null): Import` | Store and queue |
| `upload($file, ?Model $user = null, ?string $type = null): Import` | Store as a draft |
| `reader($file, $format = null): SpreadsheetReader` | |
| `reportFormat(Formats $source): Formats` | |
| `template($format = 'xlsx', ?string $path = null): string` | Write a template; returns the path |
| `templateHeadings(): array`, `templateFilename($format): string` | |
| `downloadTemplate($format = 'xlsx', ?string $filename = null): BinaryFileResponse` | |
| `processRow(array $cells, int $row, Mapping $mapping): array` | `[$data, $errors]` for one row |
| `prepareChunk(array $rows, Mapping $mapping)`, `persistRow(array $data, int $row)` | Used by `ImportRunner` |
| `display(mixed $value): string` | A cell as text |
| `reset(): void` | Forget columns and caches |

$file is a path or an `UploadedFile`; $format a `Formats` case, `'csv'`/`'xlsx'`/`'ods'`, or null (from the file name).

# `Javaabu\Imports\Columns\Column`

`make(string $attribute)`, `attribute()`, `label(string|Closure)`, `getLabel()`, `getLabelIn(string $locale)`, `description(?string)`, `getDescription()`, `rules(string|array|Rule)`, `getRules()`, `typeRules()`, `ownRules()`, `required(bool = true)`, `nullable()`, `isRequired()`, `aliases(array|string)`, `getAliases()`, `default(mixed)`, `hasDefault()`, `getDefault(array $row)`, `transform(Closure)`, `type(?string)`, `boolean()`, `integer()`, `number()`, `date()`, `datetime()`, `string()`, `getType()`, `enum(class-string)`, `getEnum()`, `lookup(string $model, string|array $by = 'name', ?string $key = null)`, `createMissing(bool|Closure = true)`, `getLookup()`, `virtual(bool = true)`, `isVirtual()`, `clearsWhenEmpty(bool = true)`, `withoutTrimming()`, `example(mixed)`, `getExample()`, `baseName()`, `toArray()`. Types: `Column::BOOLEAN`, `INTEGER`, `NUMBER`, `DATE`, `DATETIME`, `STRING`. See [Writing importers](../basic-usage/writing-importers.md#columns).

# `Javaabu\Imports\Columns\Lookup`

`model()`, `by()`, `keyName()`, `createMissing()`, `createsMissing()`, `warm(array $values)`, `find(mixed $value)` (the key, null for an empty value, false for no match), `create(mixed $value)`, `forget()`.

# Results

`Processing\ImportResult`: public `$processed`, `$created`, `$updated`, `$skipped`, `$failed`, `$failures` (`RowFailure[]`), `$report_path`, readonly `$dry_run`; `imported()`, `hasFailures()`, `toArray()`.

`Processing\RowFailure`: readonly `$row`, `$errors` (attribute => messages), `$values` (the cells); `messages()`, `toArray()`, `fromArray()`.

`Processing\Preview`: readonly `$headings`, `$samples`, `$mapping`, `$rows`, `$missing`, `$format`; `isReady()`, `hasErrors()`, `checkedCount()`, `invalidCount()`, `invalidRows()`, `outcomeCounts()`, `columns()`, `toArray()`.

`Processing\ImportRunner`: `new ImportRunner($importer, $reader, $mapping, ?FailureReport $report, ?Closure $on_progress, ?Closure $should_stop)`, `run(): ImportResult`, `result()`.

`Processing\FailureReport`: `new FailureReport(string $path, Formats $format, array $headings)`, `add(RowFailure)`, `count()`, `path()`, `close()`.

# Mapping

`Mapping\Mapping`: `new Mapping(array $map)`, `static suggest(array $headings, array $columns)`, `static fromArray(array $mapping, array $headings, array $columns)` (throws `InvalidMappingException`), `indexOf(string $attribute)`, `isMapped()`, `missingRequired(array $columns)`, `ensureComplete(array $columns)`, `isEmpty()`, `toArray()`.

`Mapping\ColumnMatcher`: `match(array $headings, array $columns): array`, `normalize(string $value, bool $loose = false)`.

# Reading and writing files

`Readers\SpreadsheetReader`: `static make(string $path, $format = null, int $heading_row = 1, int|string $sheet = 0)`, `headings()`, `rows(?int $limit = null)` (a generator keyed by row number), `count()`, `path()`, `format()`, `headingRow()`.

`Writers\SpreadsheetWriter`: `static write(string $path, Formats $format, array $headings, iterable $rows): int`, `open()`, `addHeadings()`, `addRow()`, `rowCount()`, `close()`.

`Support\TemporaryFile`: `path()`, `fromDisk()`, `copyToDisk()`, `delete()`.

`Schema\ModelSchema`: `static for($model)`, `columns()`, `uniqueBy(array $attributes)`, `static flush()`.

# `Javaabu\Imports\Models\Import`

Relations: `user()` (morph). Scopes: `ownedBy(Model $user)`, `expired(?DateTimeInterface $now)`, `status($status)`. Static: `draft()`, `start()`, `expiresAfterDays()`, `directory()`, `safeFilename()`. Methods: `importer()`, `configure(?array $mapping, $duplicates, ?bool $dry_run, bool $require_complete = false)`, `preview(?int $rows)`, `run()`, `progress()`, `isDraft()`, `isCancellable()`, `hasExpired()`, `hasReport()`, `wasCancelled()`, `cancel()`, `isOwnedBy()`, `markProcessing()`, `recordProgress()`, `markCompleted()`, `markFailed()`, `failureSamples()`, `localCopy()`, `reportFilename()`, `downloadReport()`, `reportExists()`, `deleteFiles()`, `temporaryReportUrl()`, `broadcastChannel()`, `ownerBroadcastChannel()`. See [Queued imports](../basic-usage/queued-imports.md#the-import-model).

# `Javaabu\Imports\Imports`

`register(string|array $type, ?string $importer_class)`, `registerImports(array)` (v1 name), `registered()`, `importerClass(string $type)`, `typeOf($importer)`, `forget(?string $type)`, `importModel()`, `newImportModel()`, `ignoreMigrations()`, `ownerKey(Model $user)`.

# Enums

| Enum | Cases | Methods |
| --- | --- | --- |
| `Enums\Formats` | `CSV`, `XLSX`, `ODS` | `extension()`, `mimeType()`, `label()`, `resolve()`, `enabled()`, `extensions()` |
| `Enums\Duplicates` | `SKIP`, `UPDATE`, `FAIL` | `label()`, `resolve()` |
| `Enums\RowOutcomes` | `CREATED`, `UPDATED`, `SKIPPED` | `label()` |
| `Enums\ImportStatuses` | `DRAFT`, `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `CANCELLED` | `label()`, `color()`, `isFinished()`, `isCancellable()` |

# Exceptions

`Exceptions\RowFailedException` (`withMessage(string $message, string $attribute = 'row')`, `errors()`), `Exceptions\InvalidMappingException` (a `ValidationException`), `Exceptions\ImportCancelledException`.

# HTTP and the rest

- `Routing\ImportRoutes`: `register(Router $router, array $options)`, `guard(Request)`, `namePrefix(Request)`; `Route::imports(array $options)`.
- `Http\Controllers\ImportsController`: `index`, `store`, `template`, `show`, `preview`, `run`, `report`, `signedReport`, `cancel`, `destroy`.
- `Http\Requests\UploadImportRequest`, `Http\Requests\ImportOptionsRequest` (`mapping()`), `Http\Resources\ImportResource`.
- `Policies\ImportPolicy`, `Views\ViewRenderer` (`render()`, `stack()`, `isInertia()`, `inertiaPage()`).
- `Jobs\ProcessImport`, `Events\Import{Started,Progressed,Completed,Failed,Cancelled}`, `Notifications\Import{Completed,Failed}Notification`, `Support\Broadcasting` (`enabled()`, `pollInterval()`, `channelPrefix()`, `registerChannels()`, `encodeType()`, `payload()`).
- Commands: `imports:make {name} {--model=} {--force}`, `imports:prune {--days=} {--pretend}`.
