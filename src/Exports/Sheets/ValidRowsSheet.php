<?php

namespace App\Imports\Exports\Sheets;

class ValidRowsSheet extends InvalidRowsSheet
{
    /**
     * @return string
     */
    public function title(): string
    {
        return 'Valid Rows';
    }
}
