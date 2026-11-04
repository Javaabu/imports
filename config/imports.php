<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    |
    | Rows are read and saved chunk_size at a time, each chunk in one database
    | transaction. heading_row is the row (from 1) that holds the headings.
    |
    */

    'chunk_size' => 500,

    'heading_row' => 1,

];
