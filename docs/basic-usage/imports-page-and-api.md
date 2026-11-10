---
title: Imports page and API
sidebar_position: 4
---

`Route::imports()` adds pages where people upload a file, map and preview its columns, run it and follow it, and a JSON API that does the same.

```php
// routes/web.php
Route::middleware('web')->group(function () {
    Route::imports();
});

// AppServiceProvider::boot()
Imports::register('products', ProductsImporter::class);
Imports::register([
    'brands' => BrandsImporter::class,
    'categories' => CategoriesImporter::class,
]);
```

Only registered importers can be run from the page, and only by users their `authorize()` allows.

# Options

```php
Route::imports([
    'prefix' => 'admin/imports',        // default: imports
    'name' => 'admin.imports',          // default: imports
    'guard' => 'web_admin',             // default: the default guard
    'middleware' => ['auth:web_admin', 'verified'], // default: auth (or auth:{guard})
    'controller' => ImportsController::class,       // default: imports.controllers.imports
    'signed' => true,                   // the signed report route for emails
]);
```

Register it once per guard to have imports in several panels.

# The flow

1. **Upload** (`POST /imports`, `type` and `file`): the file is stored as a draft and the user lands on its page.
2. **Map** (`GET /imports/{import}`): each heading with sample values and the field it fills, chosen automatically. Required fields that aren't mapped are listed. Choose what happens to existing records and whether it's a dry run.
3. **Preview**: the first 100 rows checked with the mapping, with their errors and whether each would be new, an update or skipped. Nothing is saved.
4. **Run** (`POST /imports/{import}/run`): queued; the page shows live progress, then the counts, the failed rows and a link to download them.

Templates for each importer are linked from the upload form.

# Routes and the API

Every route answers JSON when the request expects it (`Accept: application/json`).

| Method | URI | Name | |
| --- | --- | --- | --- |
| GET | `/imports` | `imports.index` | The user's imports (`?status=`, `?type=`, `?mine=1`, `?per_page=`); `meta.available` lists the importers they may run, with template links |
| POST | `/imports` | `imports.store` | Upload: `type`, `file`. 201 with the draft and its `preview`. |
| GET | `/imports/template/{type}` | `imports.template` | A template: `?format=xlsx\|csv\|ods` |
| GET | `/imports/{import}` | `imports.show` | The import; `preview` for drafts, `failures` once finished |
| POST | `/imports/{import}/preview` | `imports.preview` | Save `mapping`, `duplicates`, `dry_run` on a draft and return the new `preview` |
| POST | `/imports/{import}/run` | `imports.run` | Save the options and queue it (202). With `intent=preview` it previews instead. |
| GET | `/imports/{import}/report` | `imports.report` | Download the failed rows |
| GET | `/imports/{import}/signed-report` | `imports.signed-report` | The same, with a signed URL and no login |
| POST | `/imports/{import}/cancel` | `imports.cancel` | Cancel a pending or running import |
| DELETE | `/imports/{import}` | `imports.destroy` | Delete it and its files |

`mapping` is an object of heading index => attribute (empty to skip the column):

```bash
curl -X POST https://app.test/imports/9d3c…/run \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"mapping": {"0": "sku", "1": "name", "2": ""}, "duplicates": "update", "dry_run": false}'
```

A mapping that leaves out required fields, names a field twice or names an unknown field is a 422 with errors under `mapping.{attribute}` or `mapping.{index}`.

An import (`ImportResource`):

```json
{
    "id": "9d3c…",
    "type": "products",
    "name": "Products",
    "filename": "ބަރުދަލު.xlsx",
    "format": "xlsx",
    "status": "processing",
    "status_label": "Processing",
    "status_color": "info",
    "progress": 40,
    "total_rows": 12000,
    "processed_rows": 4800,
    "created_rows": 4700,
    "updated_rows": 60,
    "skipped_rows": 20,
    "failed_rows": 20,
    "duplicates": "update",
    "dry_run": false,
    "is_draft": false,
    "is_finished": false,
    "is_cancellable": true,
    "has_report": false,
    "can": { "update": true, "download": true, "cancel": true, "delete": false },
    "links": { "show": "…", "preview": null, "run": "…", "report": null, "cancel": "…", "destroy": "…" },
    "broadcast_channel": "imports.9d3c…",
    "created_at": "2026-11-04T10:00:00+05:00"
}
```

A preview:

```json
{
    "format": "xlsx",
    "headings": [{ "index": 0, "heading": "SKU", "samples": ["TV-1", "TV-2", "TV-3"], "attribute": "sku" }],
    "columns": [{ "attribute": "sku", "label": "SKU", "description": null, "required": true, "aliases": ["code"], "heading": 0 }],
    "mapping": { "0": "sku" },
    "missing": [],
    "ready": true,
    "rows": [{ "row": 2, "values": { "sku": "TV-1" }, "errors": {}, "outcome": "created", "outcome_label": "New" }],
    "counts": { "checked": 100, "invalid": 3, "created": 90, "updated": 7, "skipped": 0 }
}
```

# The pages

`imports.views` chooses the pages: `blade` (javaabu/forms v2 components, extending `imports.layout`) or `inertia` (React, `imports::Imports/Index` and `imports::Imports/Show`, shipped through javaabu/manifest). Both have the same parts:

| Blade view | React component (`@javaabu/imports`) | |
| --- | --- | --- |
| `imports::imports.index` | `Pages/Imports/Index` | Upload, templates, the list |
| `imports::imports.show` | `Pages/Imports/Show` | A draft's mapping step, or an import's results |
| `imports::imports.partials.mapping` | `ColumnMapping` | Headings, samples, field selects, options, Preview / Start import |
| `imports::imports.partials.preview` | `PreviewTable` | The checked rows |
| `imports::imports.partials.results` | `ImportCounts`, `FailuresTable` | Counts and failed rows |
| `imports::imports.partials.progress` | `ImportProgress` | A progress bar |
| `imports::imports.partials.actions` | `ImportActions` | Download failed rows, try again, cancel, delete |

To use the components in your own React pages:

```tsx
import { ColumnMapping, ImportProgress, useImportProgress, type ImportData } from "@javaabu/imports";

const imports = useImportProgress(props.imports.data, { broadcasting: props.broadcasting, pollInterval: props.poll_interval });
```

See [Overriding controllers and views](../advanced-usage/overriding-controllers-and-views.md).
