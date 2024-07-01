<?php

namespace Javaabu\Imports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ImportTemplate implements FromArray, WithHeadings, ShouldAutoSize
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
     *
     * @param array $headings
     * @param array $dummy_data
     */
    public function __construct(array $headings, array $dummy_data)
    {
        $this->headings = $headings;
        $this->dummy_data = $dummy_data;
    }


    /**
     * @return array
     */
    public function array(): array
    {
        return $this->dummy_data;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return $this->headings;
    }
}
