<?php

namespace Javaabu\Imports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ImportTemplate implements FromArray, ShouldAutoSize, WithHeadings
{
    use Exportable;

    protected ?array $headings;

    protected ?array $dummy_data;

    public function __construct(array $headings, array $dummy_data)
    {
        $this->headings = $headings;
        $this->dummy_data = $dummy_data;
    }

    public function array(): array
    {
        return $this->dummy_data;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
