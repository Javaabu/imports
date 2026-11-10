---
title: Live progress
sidebar_position: 3
---

The imports pages update as imports run: the progress bar, the rows and the counts of new, updated, skipped and failed rows. With [Laravel Reverb](https://reverb.laravel.com) installed, progress is broadcast; without it, the page polls. Nothing else changes.

# Turning it on

```bash
php artisan install:broadcasting   # installs Reverb and Laravel Echo
```

With `imports.broadcasting.enabled` set to `auto` (the default), progress is broadcast when `laravel/reverb` is installed and the default broadcaster isn't `null` or `log`. Set it to `true` to broadcast with another broadcaster (Pusher, Ably), or `false` to always poll.

# Channels

| Channel | Who may listen | |
| --- | --- | --- |
| `private-imports.{uuid}` | Users who may `view` the import | One import |
| `private-imports.owner.{type}.{id}` | The owner only | Every import of a user; `{type}` is the morph type with `\` replaced by `-` |

The package authorizes both. The guards that authenticate the channels are `imports.broadcasting.guards` (null: the defaults). Change the `imports` prefix with `imports.broadcasting.channel`.

| Event | Broadcast as |
| --- | --- |
| `ImportStarted` | `import.started` |
| `ImportProgressed` | `import.progressed` |
| `ImportCompleted` | `import.completed` |
| `ImportFailed` | `import.failed` |
| `ImportCancelled` | `import.cancelled` |

They're broadcast immediately (`ShouldBroadcastNow`), from the job, after every chunk (`imports.chunk_size` rows). Each sends:

```json
{
    "id": "9d3c…",
    "status": "processing",
    "status_label": "Processing",
    "status_color": "info",
    "progress": 25,
    "total_rows": 200000,
    "processed_rows": 50000,
    "created_rows": 49500,
    "updated_rows": 300,
    "skipped_rows": 150,
    "failed_rows": 50,
    "is_finished": false,
    "has_report": false,
    "error": null
}
```

# Listening in your own pages

The API's `broadcast_channel` and `meta.broadcasting` tell a page what to do:

```ts
import { useEcho } from '@laravel/echo-react';

useEcho(`imports.${importId}`, ['.import.progressed', '.import.completed', '.import.failed', '.import.cancelled'], (payload) => {
    setImport((current) => ({ ...current, ...payload }));
});
```

```js
// Blade, with Laravel Echo
Echo.private(`imports.${importId}`)
    .listen('.import.progressed', (payload) => updateRow(payload))
    .listen('.import.completed', (payload) => updateRow(payload));
```

Note the leading dot: the events use `broadcastAs()` names.

# Polling

When `meta.broadcasting` is `false`, poll `GET /imports/{import}` (with `Accept: application/json`) every `meta.poll_interval` milliseconds (`imports.broadcasting.poll_interval`, 3000) until `is_finished` is true. Drafts don't change until they're run, so don't poll them. The package's pages do this themselves.
