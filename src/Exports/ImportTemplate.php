<?php

namespace Javaabu\Imports\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportTemplate implements FromArray, ShouldAutoSize, WithHeadings
{
    use Exportable;

    /**
     * @var array
     */
    protected $headings;

    /**
     * @var array
     */
    protected $dummy_data;

    /**
     * Create a new vendors export instance.
     */
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
