<?php

namespace Javaabu\Imports\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use App\Imports\Exports\Sheets\ValidRowsSheet;
use App\Imports\Exports\Sheets\InvalidRowsSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ErrorsExport implements WithMultipleSheets
{
    use Exportable;

    protected ?array $headings;

    protected ?array $invalid_rows;

    protected ?array $valid_rows;

    public function __construct(array $valid_rows, array $invalid_rows, array $headings)
    {
        $this->valid_rows = $valid_rows;
        $this->invalid_rows = $invalid_rows;
        $this->headings = $headings;
    }

    public function sheets(): array
    {
        $sheets = [];

        if ($this->invalid_rows) {
            $sheets[] = new InvalidRowsSheet($this->headings, $this->invalid_rows);
        }

        if ($this->valid_rows) {
            $sheets[] = new ValidRowsSheet($this->headings, $this->valid_rows);
        }

        return $sheets;
    }
}
