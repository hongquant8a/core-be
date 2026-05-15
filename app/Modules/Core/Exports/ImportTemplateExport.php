<?php

namespace App\Modules\Core\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

/**
 * Sinh file Excel template rỗng cho import.
 *
 * Nhận map [field_key => 'Nhãn tiếng Việt']. Header trong file là nhãn tiếng Việt;
 * Import class tương ứng dịch ngược về field_key trong prepareForValidation
 * (xem trait TranslatesExcelHeadings).
 */
class ImportTemplateExport extends AbstractExcelExport implements FromArray
{
    /**
     * @param  array<string, string>  $fieldLabels  [field_key => 'Nhãn tiếng Việt']
     */
    public function __construct(private array $fieldLabels) {}

    public function headings(): array
    {
        return array_values($this->fieldLabels);
    }

    public function array(): array
    {
        return [];
    }
}
