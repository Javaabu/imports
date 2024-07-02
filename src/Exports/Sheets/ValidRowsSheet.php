<?php

namespace Javaabu\Imports\Exports\Sheets;

class ValidRowsSheet extends InvalidRowsSheet
{
    public function title(): string
    {
        return 'Valid Rows';
    }
}
