<?php

namespace App\Modules\Core\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Sinh file Excel template cho import.
 *
 * Layout:
 *   - Row 1: header nhãn tiếng Việt (từ $fieldLabels); cột bắt buộc gắn dấu " *" ở cuối.
 *   - Row 2: ví dụ mẫu (từ $exampleRow, italic xám — user xóa trước khi nhập data thật).
 *
 * Nhận map [field_key => 'Nhãn tiếng Việt']. Header trong file là nhãn tiếng Việt (cột bắt buộc
 * có dấu *); Import class tương ứng bỏ dấu * rồi dịch ngược về field_key trong
 * prepareForValidation (xem trait TranslatesExcelHeadings).
 */
class ImportTemplateExport extends AbstractExcelExport implements FromArray
{
    /**
     * @param  array<string, string>  $fieldLabels   [field_key => 'Nhãn tiếng Việt']
     * @param  array<string, string>  $exampleRow    [field_key => 'giá trị ví dụ']. Optional;
     *                                               empty → không có row 2 (giống behavior cũ).
     * @param  array<int, string>     $requiredKeys  Danh sách field_key bắt buộc → header gắn dấu " *".
     */
    public function __construct(
        private array $fieldLabels,
        private array $exampleRow = [],
        private array $requiredKeys = [],
    ) {}

    public function headings(): array
    {
        // Không khai báo requiredKeys → giữ header trần (backward-compat cho các module chưa gắn dấu).
        if (empty($this->requiredKeys)) {
            return array_values($this->fieldLabels);
        }

        $required = array_flip($this->requiredKeys);

        // Cột bắt buộc gắn dấu " *" ở cuối; cột không bắt buộc để trần.
        $headings = [];
        foreach ($this->fieldLabels as $key => $label) {
            $headings[] = isset($required[$key]) ? $label.' *' : $label;
        }

        return $headings;
    }

    public function array(): array
    {
        if (empty($this->exampleRow)) {
            return [];
        }

        // Trả values theo đúng order của fieldLabels — fill rỗng nếu key không có ví dụ.
        $row = [];
        foreach ($this->fieldLabels as $key => $_label) {
            $row[] = (string) ($this->exampleRow[$key] ?? '');
        }

        return [$row];
    }

    /**
     * Override customEvents để style row 2 (example) khác biệt với row 1 (header) + data thật:
     * italic + màu xám + background nhạt → user nhận biết là ví dụ và xóa trước khi nhập data thật.
     */
    protected function customEvents(AfterSheet $event): void
    {
        if (empty($this->exampleRow)) {
            return;
        }

        $sheet = $event->sheet->getDelegate();
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => [
                'italic' => true,
                'color' => ['rgb' => '6B7280'], // gray-500
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'], // gray-100
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);
    }
}
