---
title: Configuration
sidebar_position: 10
---

```bash
php artisan vendor:publish --tag=imports-config
```

| Option | Default | |
| --- | --- | --- |
| `formats` | `['xlsx', 'csv', 'ods']` | What people may upload (`.txt` counts as CSV), and the template formats |
| `report_format` | `null` | Failure reports' format; null: the uploaded file's |
| `chunk_size` | `500` | Rows validated and saved at a time, in one transaction |
| `heading_row` | `1` | The row with the headings; importers can override `headingRow()` |
| `temporary_directory` | `null` | Where files are copied to be read and reports written; null: the system's |
| `csv.delimiter`, `csv.enclosure`, `csv.encoding` | `,`, `"`, `UTF-8` | How CSV files are read (and CSV reports written) |
| `exclude` | `password`, `remember_token`, `*_token`, `*_secret`, `two_factor_recovery_codes`, `api_key` | Columns model importers leave out by default (patterns allowed) |
| `duplicates` | `skip` | What happens to rows matching an existing record when the import doesn't say: `skip`, `update`, `fail` |
| `lookup_cache` | `10000` | Values each lookup remembers |
| `failure_samples` | `100` | Failed rows kept on results and on the `Import` (the report has all) |
| `preview.rows` | `100` | Rows the preview validates |
| `preview.samples` | `3` | Sample values shown for each column |
| `rtl_locales` | `['dv', 'ar', 'fa', 'he', 'ur']` | Locales whose XLSX templates and reports are right-to-left |
| `disk` | `env('IMPORTS_DISK')` | Where uploads and reports are stored; null: the default disk |
| `directory` | `imports` | The folder on the disk; files go in `{directory}/{uuid}/` |
| `max_file_size` | `51200` | The upload limit, in KB |
| `table` | `imports` | The imports table |
| `user_key_type` | `integer` | The key type of user models: `integer`, `uuid`, `ulid` or `string` (before migrating) |
| `models.import` | `Import::class` | Replace the model with a subclass |
| `queue.connection`, `queue.queue` | `env('IMPORTS_QUEUE_CONNECTION')`, `env('IMPORTS_QUEUE')` | Where `ProcessImport` runs; null: the defaults |
| `queue.timeout` | `3600` | Seconds before the job times out |
| `queue.job` | `ProcessImport::class` | Replace the job with a subclass |
| `draft_expire_after_hours` | `24` | Drafts that aren't run expire after this |
| `expire_after_days` | `7` | Finished imports keep their files this long |
| `signed_url_minutes` | `1440` | Signed report links in notifications last this long (or until the files expire) |
| `notifications.enabled` | `true` | Notify the user when a queued import finishes |
| `notifications.channels` | `['mail']` | |
| `notifications.completed`, `notifications.failed` | The package's notifications | A class that takes the `Import`, or null to turn one off |
| `routes.signed_report` | `imports.signed-report` | The signed report route's name, for notifications |
| `broadcasting.enabled` | `env('IMPORTS_BROADCASTING', 'auto')` | `auto` (Reverb installed and a real broadcaster), `true` or `false` |
| `broadcasting.channel` | `imports` | The channel prefix |
| `broadcasting.guards` | `null` | Guards that authorize the channels |
| `broadcasting.poll_interval` | `3000` | Milliseconds between polls when not broadcasting |
| `views` | `env('IMPORTS_VIEWS', 'blade')` | `blade` or `inertia` |
| `layout`, `layout_section` | `layouts.app`, `content` | What the Blade pages extend and fill |
| `controllers.imports` | `ImportsController::class` | The controller `Route::imports()` uses |
| `policy` | `ImportPolicy::class` | The policy the package registers for the Import model |
| `register_permissions` | `true` | Register the four permissions and the policy |
