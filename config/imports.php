<?php

use Javaabu\Imports\Http\Controllers\ImportsController;
use Javaabu\Imports\Jobs\ProcessImport;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Notifications\ImportCompletedNotification;
use Javaabu\Imports\Notifications\ImportFailedNotification;
use Javaabu\Imports\Policies\ImportPolicy;

return [

    /*
    |--------------------------------------------------------------------------
    | Formats
    |--------------------------------------------------------------------------
    |
    | The file formats people may upload, and that templates can be downloaded
    | in: xlsx, csv and ods. Failure reports are written in the uploaded file's
    | format, unless report_format is set.
    |
    */

    'formats' => ['xlsx', 'csv', 'ods'],

    'report_format' => null,

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    |
    | Rows are read, validated and saved chunk_size at a time, each chunk in one
    | database transaction (each row in its own savepoint, so one failing row
    | doesn't undo the others). heading_row is the row (from 1) holding the
    | headings. Files are copied here before they're read (null: the system
    | temporary directory).
    |
    */

    'chunk_size' => 500,

    'heading_row' => 1,

    'temporary_directory' => null,

    'csv' => [
        'delimiter' => ',',
        'enclosure' => '"',
        'encoding' => 'UTF-8',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default columns
    |--------------------------------------------------------------------------
    |
    | Model importers without columns() import the table's columns except the
    | primary key, timestamps, hidden attributes, JSON columns and these names
    | (patterns allowed).
    |
    */

    'exclude' => [
        'password',
        'remember_token',
        '*_token',
        '*_secret',
        'two_factor_recovery_codes',
        'api_key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Duplicates
    |--------------------------------------------------------------------------
    |
    | What happens to rows that match an existing record, unless the import says
    | otherwise: skip, update or fail.
    |
    */

    'duplicates' => 'skip',

    /*
    |--------------------------------------------------------------------------
    | Lookups and failures
    |--------------------------------------------------------------------------
    |
    | Lookups remember up to lookup_cache values each. Results keep the first
    | failure_samples failed rows (shown on the import's page); the failure
    | report has all of them.
    |
    */

    'lookup_cache' => 10000,

    'failure_samples' => 100,

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    |
    | The mapping step validates the first `rows` rows and shows `samples` values
    | of each column.
    |
    */

    'preview' => [
        'rows' => 100,
        'samples' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Right-to-left files
    |--------------------------------------------------------------------------
    |
    | Templates and failure reports in XLSX are right-to-left in these locales.
    |
    */

    'rtl_locales' => ['dv', 'ar', 'fa', 'he', 'ur'],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Uploaded files and failure reports are stored on this disk (null: the
    | app's default disk), under `directory/{uuid}/`. max_file_size is the
    | upload limit in kilobytes.
    |
    */

    'disk' => env('IMPORTS_DISK'),

    'directory' => 'imports',

    'max_file_size' => 51200,

    /*
    |--------------------------------------------------------------------------
    | The imports table and model
    |--------------------------------------------------------------------------
    |
    | user_key_type is the key type of the models that start imports (any
    | model, through the `user` morph): integer, uuid, ulid or string. Set it
    | before running the migration.
    |
    */

    'table' => 'imports',

    'user_key_type' => 'integer',

    'models' => [
        'import' => Import::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queued imports
    |--------------------------------------------------------------------------
    |
    | connection and queue null mean the app's defaults. Progress is saved (and
    | broadcast) after every chunk. The job can be replaced with a subclass of
    | ProcessImport.
    |
    */

    'queue' => [
        'connection' => env('IMPORTS_QUEUE_CONNECTION'),
        'queue' => env('IMPORTS_QUEUE'),
        'timeout' => 3600,
        'job' => ProcessImport::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    |
    | Drafts that are never run expire after draft_expire_after_hours. Finished
    | imports keep their upload and failure report for expire_after_days. Then
    | `imports:prune` deletes drafts, and the files of other imports (their
    | records stay). Signed report links in notifications last
    | signed_url_minutes, or until the files expire if that's sooner.
    |
    */

    'draft_expire_after_hours' => 24,

    'expire_after_days' => 7,

    'signed_url_minutes' => 1440,

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The user who started a queued import is notified when it completes or
    | fails, if their model uses Notifiable. Set a notification to null to turn
    | it off.
    |
    */

    'notifications' => [
        'enabled' => true,
        'channels' => ['mail'],
        'completed' => ImportCompletedNotification::class,
        'failed' => ImportFailedNotification::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The name of the signed failure report route used in notifications.
    | Route::imports() registers it as {name}.signed-report; change this if you
    | change the name.
    |
    */

    'routes' => [
        'signed_report' => 'imports.signed-report',
    ],

    /*
    |--------------------------------------------------------------------------
    | Live progress
    |--------------------------------------------------------------------------
    |
    | Progress is broadcast on private channels when enabled is true, or when
    | it's 'auto' and laravel/reverb is installed with a real broadcaster as the
    | default. Otherwise pages poll the show route every poll_interval ms.
    | guards are the guards that authorize the channels (null: the defaults).
    |
    */

    'broadcasting' => [
        'enabled' => env('IMPORTS_BROADCASTING', 'auto'),
        'channel' => 'imports',
        'guards' => null,
        'poll_interval' => 3000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | The pages the imports routes render: blade (javaabu/forms components) or
    | inertia (React pages shipped through javaabu/manifest). Requests that
    | expect JSON always get JSON. The Blade pages extend `layout` and fill
    | `layout_section`.
    |
    */

    'views' => env('IMPORTS_VIEWS', 'blade'),

    'layout' => 'layouts.app',

    'layout_section' => 'content',

    /*
    |--------------------------------------------------------------------------
    | Controllers and authorization
    |--------------------------------------------------------------------------
    |
    | Replace the controller with a subclass, or pass `controller` to
    | Route::imports(). The policy here is the package default; apps can also
    | override it with config('permissions.policies') or Gate::policy(). Turn
    | off register_permissions to register your own.
    |
    */

    'controllers' => [
        'imports' => ImportsController::class,
    ],

    'policy' => ImportPolicy::class,

    'register_permissions' => true,

];
