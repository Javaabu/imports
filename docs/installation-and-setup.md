---
title: Installation & Setup
sidebar_position: 1.2
---

# Installing

```bash
composer require javaabu/imports
```

The service provider is discovered automatically. Nothing else is needed to [import files from code](basic-usage/importing-files.md).

# Migrating

[Queued imports](basic-usage/queued-imports.md) and the imports page keep their uploads in an `imports` table. The package loads its migration itself:

```bash
php artisan migrate
```

Imports belong to whoever uploaded them through a `user` morph, so any user model works. If your user models have UUID, ULID or string keys, set `user_key_type` before migrating:

```bash
php artisan vendor:publish --tag=imports-config
```

```php
// config/imports.php
'user_key_type' => 'uuid', // integer (default), uuid, ulid or string
```

To change the migration itself, publish it and stop the package from loading its copy:

```bash
php artisan vendor:publish --tag=imports-migrations
```

```php
// AppServiceProvider::register()
\Javaabu\Imports\Imports::ignoreMigrations();
```

# Storage

Uploads and failure reports are stored on `imports.disk` (`IMPORTS_DISK`, the app's default disk when unset), under `imports/{uuid}/`. Use a private disk: the files hold your users' data.

```dotenv
IMPORTS_DISK=local
```

# Permissions

The package registers four permissions with [javaabu/permissions](https://github.com/Javaabu/permissions). Write them to the database, as on every deploy:

```bash
php artisan permissions:sync
```

| Permission | Allows |
| --- | --- |
| `view_imports` | Uploading, mapping, running and following your own imports |
| `view_others_imports` | Seeing everyone's imports and downloading their failure reports |
| `delete_imports` | Cancelling and deleting your own imports |
| `delete_others_imports` | Cancelling and deleting everyone's imports |

Which importers someone may run is up to each importer: a model importer needs the `create` ability on its model. See [Permissions and policies](advanced-usage/permissions-and-policies.md).

# The imports page

Register the routes inside your own route group, so you choose the prefix, middleware and guard:

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::imports();
});
```

Then register the importers users may run from that page:

```php
// AppServiceProvider::boot()
use App\Imports\ProductsImporter;
use Javaabu\Imports\Imports;

Imports::register('products', ProductsImporter::class);
```

The pages extend your `layouts.app` layout (`imports.layout`) and need manifest's Vite plugin for live progress; for Inertia React, set `imports.views` to `inertia`. See [Overriding controllers and views](advanced-usage/overriding-controllers-and-views.md#setting-up-the-pages) and [Imports page and API](basic-usage/imports-page-and-api.md).

# Cleaning up

Drafts that are never run expire after a day, and other imports keep their files for 7 days (`imports.draft_expire_after_hours`, `imports.expire_after_days`). Schedule the prune command:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('imports:prune')->daily();
```

It deletes expired drafts, and the uploads and reports of expired imports. Their records stay, as a history; add `--days=90` to delete records older than that too.

# Publishing

| Tag | What |
| --- | --- |
| `imports-config` | `config/imports.php` |
| `imports-migrations` | The `imports` table migration |
| `imports-views` | The Blade views, to `resources/views/vendor/imports` |
| `imports-stubs` | The `imports:make` stub, to `stubs/vendor/imports` |

Every option is described in [Configuration](advanced-usage/configuration.md).
