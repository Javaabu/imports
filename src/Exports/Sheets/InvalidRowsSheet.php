<?php

namespace App\Imports\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class InvalidRowsSheet implements WithHeadings, ShouldAutoSize, FromArray, WithTitle
{
    use Exportable;

    /**
     * @var array
     */
    protected $headings;

    /**
     * @var array
     */
    protected $data;

    /**
     * Create a new vendors export instance.
     *
     * @param array $headings
     * @param array $data
     */
    public function __construct(array $headings, array $data)
    {
        $this->headings = $headings;
        $this->data = $data;
    }


    /**
     * @return array
     */
    public function array(): array
    {
        return [$this->data];
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Invalid Rows';
    }
}
