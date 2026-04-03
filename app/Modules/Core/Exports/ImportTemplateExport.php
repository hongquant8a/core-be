<?php

namespace App\Modules\Core\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportTemplateExport implements FromArray, WithHeadings
{
    public function __construct(private array $headings) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return [];
    }
}
