# Queued imports, the Import model and the API

## Drafts

```php
$import = ProductsImporter::make()->upload($request->file('file'), $request->user());

$import->headings;                       // the file's headings
$import->mapping;                        // [0 => 'sku', 1 => 'name'], suggested
$import->configure(['SKU' => 'sku', 'Name' => 'name'], duplicates: 'update', dry_run: false);
$import->preview()->toArray();           // headings, samples, columns, missing, rows, counts
$import->run();                          // queues ProcessImport; InvalidMappingException without required columns
```

## The Import model

`Javaabu\Imports\Models\Import`: `uuid` (route key), `user` (morph), `type`, `name`, `status` (`ImportStatuses`: draft, pending, processing, completed, failed, cancelled), `filename`, `format`, `headings`, `mapping`, `duplicates`, `dry_run`, `total_rows`, `processed_rows`, `created_rows`, `updated_rows`, `skipped_rows`, `failed_rows`, `failures` (first 100), `report_path`, `error`, timestamps, `expires_at`.

```php
$import->progress();            // 0–100 or null
$import->hasReport();
$import->downloadReport();
$import->temporaryReportUrl();  // signed, for emails
$import->cancel();              // stops before the next chunk; saved rows stay
Import::query()->ownedBy($user)->status('completed')->get();
```

## Events and broadcasting

`ImportStarted`, `ImportProgressed` (after each chunk), `ImportCompleted`, `ImportFailed`, `ImportCancelled`, each with `$import`. With Reverb they're broadcast on `private-imports.{uuid}` and `private-imports.owner.{type}.{id}` as `.import.progressed` etc.; otherwise pages poll `GET /imports/{uuid}` with `Accept: application/json`.

## API

| Method | URI | |
| --- | --- | --- |
| GET | `/imports` | List (`meta.available` = importers the user may run, with template links) |
| POST | `/imports` | Upload `type`, `file` → 201 draft + `preview` |
| GET | `/imports/template/{type}?format=xlsx` | Template |
| GET | `/imports/{import}` | The import, its `preview` (drafts) or `failures` |
| POST | `/imports/{import}/preview` | Save `mapping`, `duplicates`, `dry_run` → new `preview` |
| POST | `/imports/{import}/run` | Queue it (202); `intent=preview` previews instead |
| GET | `/imports/{import}/report` | Failed rows |
| POST | `/imports/{import}/cancel`, DELETE `/imports/{import}` | |

## Testing

```php
Storage::fake('local');
Queue::fake();

$import = ProductsImporter::make()->queue($file, $user);

Queue::assertPushed(ProcessImport::class);
```

With the `sync` queue the import runs straight away: `$import->refresh()->created_rows`.
