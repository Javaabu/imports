<?php

namespace Javaabu\Imports\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class InvalidRowsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    use Exportable;

    protected ?array $headings;

    protected ?array $data;

    public function __construct(array $headings, array $data)
    {
        $this->headings = $headings;
        $this->data = $data;
    }

    public function array(): array
    {
        return [$this->data];
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return 'Invalid Rows';
    }
}
