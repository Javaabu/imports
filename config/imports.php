<?php

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

];
