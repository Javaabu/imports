---
title: Overriding controllers and views
sidebar_position: 4
---

# Choosing the stack

```php
// config/imports.php
'views' => 'blade', // or 'inertia'
```

Requests that expect JSON always get JSON, whichever stack you choose.

# Setting up the pages

## Blade

The Blade pages use [javaabu/forms](https://github.com/Javaabu/forms) components, so they follow your `forms.framework` (Onu or Bootstrap 6). They extend your layout:

```php
// config/imports.php
'layout' => 'layouts.app',     // the view the pages @extends
'layout_section' => 'content', // the section they fill
```

Live progress on the Blade pages comes from a small script, `imports::imports`, delivered by [javaabu/manifest](https://github.com/Javaabu/manifest). The pages require it themselves; your app needs manifest's Vite plugin:

```js
// vite.config.js
import manifest from "./vendor/javaabu/manifest/dist/vite.js"

import default defineConfig({
    plugins: [
        laravel({ input: ["resources/css/app.css", "resources/js/app.js"] }),
        manifest(),
    ],
})
```

The pages use Tailwind utilities from Onu's theme (logical ones only, so they work right-to-left). Let Tailwind see them:

```css
/* resources/css/app.css */
@source "../../vendor/javaabu/imports/resources/views";
```

## Inertia React

The pages are `imports::Imports/Index` and `imports::Imports/Show`, written in TypeScript with [@javaabu/forms](https://github.com/Javaabu/react-forms) and [@javaabu/onu](https://www.npmjs.com/package/@javaabu/onu). javaabu/manifest ships them to your app; resolve them with `resolvePage()`:

```tsx
// resources/js/app.tsx
import { createInertiaApp } from "@inertiajs/react"
import { resolvePage } from "@javaabu/manifest/inertia"
import { createRoot } from "react-dom/client"

createInertiaApp({
    resolve: (name) => resolvePage(name, import.meta.glob("./pages/**/*.tsx")),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },
})
```

Install the npm packages the pages need, and check them:

```bash
composer require inertiajs/inertia-laravel
php artisan manifest:check   # prints the install command for anything missing
```

They need `@javaabu/forms`, `@javaabu/onu`, `react`, `react-dom` and `@inertiajs/react`. imports declares these for its pages only, so `manifest:check` asks for them once `@inertiajs/react` is in your `package.json` (or with `--scope=pages`); Blade apps aren't asked to install React. Wrap your app in `FormsProvider` (with the Onu and Inertia adapters) as @javaabu/forms describes, so forms submit as Inertia visits. Your persistent layout wraps the pages as it wraps your own.

The pages' text comes from the server, translated, in the `strings` prop, so they're in Dhivehi when the app's locale is `dv`. For live progress over Reverb, set up Laravel Echo as `window.Echo`; otherwise the pages poll.

You can also use the pages' parts in your own pages:

```tsx
import { ColumnMapping, ImportActions, ImportProgress, PreviewTable, useImportProgress } from "@javaabu/imports"
import type { ImportData, PreviewData } from "@javaabu/imports"
```

# Page props

| Prop | Pages | What |
| --- | --- | --- |
| `title` | both | The translated page title |
| `broadcasting` | both | Whether progress is broadcast; poll otherwise |
| `poll_interval` | both | Milliseconds between polls |
| `routes` | both | Route names by action, e.g. `routes.run` is `admin.imports.run` |
| `urls` | both | `index` and `store` URLs |
| `strings` | both (Inertia only) | The pages' translated text |
| `imports` | index | A paginator (Blade) or a paginated `ImportResource` collection (Inertia) |
| `available` | index | Registered importers the user may run: `[{ type, label, templates: { xlsx, csv, ods } }]` |
| `formats` | index | `[{ value, label }]`, from `imports.formats` |
| `accept`, `max_file_size` | index | The upload's accepted extensions (`.xlsx,.csv,.ods,.txt`) and limit in KB |
| `statuses` | index | `[{ value, label }]` |
| `sees_others` | index | Whether the user sees everyone's imports |
| `filters` | index | `{ status, type, mine }` from the query string |
| `import` | show | The Import model (Blade) or an `ImportResource` array (Inertia) |
| `preview` | show | For drafts, `Preview::toArray()` (or `{ error }` when the file can't be read); otherwise null |
| `failures` | show | The failed rows kept on the import: `[{ row, errors, messages, values }]` |
| `duplicate_options` | show | `[{ value, label }]` for skip, update and fail |

The mapping form posts to `routes.run`. Its Preview button sends `intent=preview`, which saves the mapping and shows the new preview instead of running.

# Overriding Blade views

```bash
php artisan vendor:publish --tag=imports-views
```

Laravel uses `resources/views/vendor/imports/imports/index.blade.php` in place of the package's. Override one view and leave the rest: `imports.index`, `imports.show`, and the partials `imports.partials.row`, `mapping`, `preview`, `results`, `progress`, `actions` and `flash`. Keep the `data-import-*` attributes if you want live progress.

# Overriding Inertia pages

Copy a page into your app and edit it:

```bash
php artisan manifest:override imports js/Pages/Imports/Index.tsx
```

It goes to `resources/vendor/imports/js/Pages/Imports/Index.tsx`, and `resolvePage()` uses it instead of the package's.

# Overriding the controller

Extend the controller and override what you need:

```php
namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Javaabu\Imports\Http\Controllers\ImportsController as BaseImportsController;

class ImportsController extends BaseImportsController
{
    // Only show imports from this tenant
    protected function query(Request $request): Builder
    {
        return parent::query($request)->where('tenant_id', $request->user()->tenant_id);
    }
}
```

Then use it in one of three ways:

```php
// config/imports.php: every Route::imports() call
'controllers' => [
    'imports' => \App\Http\Controllers\ImportsController::class,
],
```

```php
// one registration
Route::imports(['controller' => \App\Http\Controllers\ImportsController::class]);
```

```php
// the container, in a service provider
$this->app->bind(
    \Javaabu\Imports\Http\Controllers\ImportsController::class,
    \App\Http\Controllers\ImportsController::class,
);
```

Useful methods to override: `query()`, `findImport()`, `makeImporter()`, `availableImporters()`, `previewData()`, `formats()`, `meta()`, `strings()` and `reportResponse()`.
