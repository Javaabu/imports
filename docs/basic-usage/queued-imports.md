---
title: Queued imports
sidebar_position: 3
---

Big files import on the queue. The file is stored on `imports.disk`, an `Import` record tracks it, and the user is told when it's done.

```php
$import = ProductsImporter::make()->queue($request->file('file'), $request->user());

return redirect()->route('imports.show', $import);
```

`queue()` uses the importer's mapping (from `map()`, or the suggested one) and its `duplicates()` and `dryRun()` settings.

# Drafts: map and preview first

```php
$import = ProductsImporter::make()->upload($request->file('file'), $request->user());

$import->headings;   // ['SKU', 'Name', 'Brand', 'Notes']
$import->mapping;    // [0 => 'sku', 1 => 'name', 2 => 'brand_id'], suggested

$import->configure(['SKU' => 'sku', 'Name' => 'name'], duplicates: 'update');
$preview = $import->preview();

$import->run();      // queues it; throws InvalidMappingException without the required columns
```

This is what the [imports page](imports-page-and-api.md) does. Drafts that aren't run expire after `imports.draft_expire_after_hours` (24).

# The Import model

`Javaabu\Imports\Models\Import` (replace it with `imports.models.import`):

| Attribute | |
| --- | --- |
| `uuid` | The route key |
| `user` | Who uploaded it (a morph: any user model) |
| `type`, `importer_class`, `name` | The registered name, the importer class, its label |
| `status` | `ImportStatuses`: `draft`, `pending`, `processing`, `completed`, `failed`, `cancelled` |
| `disk`, `path`, `filename`, `format`, `size` | The uploaded file |
| `headings`, `mapping`, `duplicates`, `dry_run` | How it's read |
| `total_rows`, `processed_rows`, `created_rows`, `updated_rows`, `skipped_rows`, `failed_rows` | Counts |
| `failures` | The first `imports.failure_samples` (100) failed rows |
| `report_path` | The failure report, on the same disk |
| `error` | Why it failed |
| `started_at`, `completed_at`, `failed_at`, `cancelled_at`, `expires_at` | |

```php
$import->progress();          // 0–100, or null while counting rows
$import->isDraft();
$import->hasReport();         // there are failed rows and the report hasn't expired
$import->downloadReport();    // a streamed download
$import->temporaryReportUrl(); // a signed link (for emails)
$import->failureSamples();    // RowFailure objects
$import->cancel();            // stops before the next chunk; saved rows stay
$import->run();               // runs a draft, or a failed or cancelled import again
$import->importer();          // the importer, with the mapping applied

Import::query()->ownedBy($user)->status('completed')->latest()->get();
```

Deleting an import deletes its upload and report.

# The job

`Javaabu\Imports\Jobs\ProcessImport` (replace it with `imports.queue.job`):

1. copies the upload to a local temporary file and counts its rows, so progress can be shown;
2. imports it a chunk at a time, saving the counts (and broadcasting) after each chunk, and stopping if the import was cancelled;
3. stores the failure report next to the upload as `imports/{uuid}/failures.{xlsx|csv|ods}`;
4. notifies the user.

It runs in the locale the import was queued in, so messages and labels are in the user's language. It tries once and times out after `imports.queue.timeout` seconds: running an import twice could save rows twice, so failed imports are run again from the page (or with `run()`), and rows already saved are skipped or updated by the importer's unique key.

```dotenv
IMPORTS_QUEUE_CONNECTION=redis
IMPORTS_QUEUE=imports
```

# Notifications

When the user model uses `Notifiable`, they get `ImportCompletedNotification` (the counts, and a signed link to the failed rows) or `ImportFailedNotification`, by mail. Change the channels or the classes, or turn them off:

```php
'notifications' => [
    'enabled' => true,
    'channels' => ['mail', 'database'],
    'completed' => \App\Notifications\ImportDone::class, // gets the Import
    'failed' => null,
],
```

The link uses the `imports.signed-report` route from `Route::imports()`; set `imports.routes.signed_report` if you named the routes differently.

# Events

| Event | When |
| --- | --- |
| `ImportStarted` | The job started (rows counted) |
| `ImportProgressed` | After each chunk |
| `ImportCompleted` | Finished; some rows may have failed (`failed_rows`) |
| `ImportFailed` | The job failed |
| `ImportCancelled` | Someone cancelled it |

Each has a public `$import`. They're [broadcast](../advanced-usage/live-progress.md) when Reverb is installed.

# Pruning

```bash
php artisan imports:prune             # expired drafts, and files of expired imports
php artisan imports:prune --days=90   # also import records older than 90 days
php artisan imports:prune --pretend
```

# Testing

```php
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Javaabu\Imports\Jobs\ProcessImport;

Storage::fake('local');
Queue::fake();

$import = ProductsImporter::make()->queue($file, $user);

Queue::assertPushed(ProcessImport::class, fn ($job) => $job->import->is($import));
```

With the `sync` queue the import runs straight away, so `$import->refresh()` has the results.
