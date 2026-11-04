---
title: Requirements
sidebar_position: 1.1
---

- PHP 8.4 or 8.5, with the `zip`, `xmlreader` and `dom` extensions (for XLSX and ODS)
- Laravel 12 or 13
- [javaabu/permissions](https://github.com/Javaabu/permissions) v3, [javaabu/forms](https://github.com/Javaabu/forms) v2 and [javaabu/manifest](https://github.com/Javaabu/manifest) v1, installed with this package
- For the imports pages: Vite with manifest's plugin. The Inertia React pages also need `@javaabu/forms`, `@javaabu/onu`, React 18.3 or 19 and `@inertiajs/react` 2 or 3 in the app (`php artisan manifest:check` lists them).

Optional:

| Package | For |
| --- | --- |
| `laravel/reverb` ^1.12 | Live progress on the imports page. Without it, the page polls. See [Live progress](advanced-usage/live-progress.md). |
| `inertiajs/inertia-laravel` ^2.0 | The Inertia React pages (`imports.views` = `inertia`). See [Imports page and API](basic-usage/imports-page-and-api.md). |

A queue worker is needed for [queued imports](basic-usage/queued-imports.md). With the `sync` queue they run during the request.
