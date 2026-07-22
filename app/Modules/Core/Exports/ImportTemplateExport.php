<?php

namespace App\Modules\Core\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Sinh file Excel template cho import.
 *
 * Layout:
 *   - Row 1: header nhãn tiếng Việt (từ $fieldLabels); cột bắt buộc gắn dấu " *" ở cuối.
 *     Cột enum/đặc biệt có ghi chú (comment) liệt kê giá trị hợp lệ (từ $columnNotes).
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
     * @param  array<string, string>  $columnNotes   [field_key => 'ghi chú giá trị hợp lệ'] → hiện cho
     *                                               cột enum (dropdown prompt nếu ngắn, comment nếu dài).
     * @param  array<string, array>   $columnOptions [field_key => [giá trị thô]] → tạo dropdown chọn nhanh
     *                                               trên ô data (nếu tổng độ dài ≤ 255 — giới hạn Excel).
     */
    public function __construct(
        private array $fieldLabels,
        private array $exampleRow = [],
        private array $requiredKeys = [],
        private array $columnNotes = [],
        private array $columnOptions = [],
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
     * Override customEvents để:
     *  - Gắn comment liệt kê giá trị hợp lệ cho cột enum/đặc biệt (row 1 header).
     *  - Style row 2 (example) khác biệt: italic + xám + nền nhạt → user biết là ví dụ và xóa trước khi nhập.
     */
    protected function customEvents(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $keys = array_keys($this->fieldLabels);
        $handledByDropdown = [];

        // 1. Dropdown chọn nhanh (data validation LIST) + prompt hiện khi bấm ô — cho enum ngắn.
        foreach ($this->columnOptions as $field => $values) {
            $idx = array_search($field, $keys, true);
            if ($idx === false || empty($values)) {
                continue;
            }

            $csv = implode(',', $values);
            if (mb_strlen($csv) > 255) {
                continue; // Vượt giới hạn list nội tuyến của Excel → để comment (mục 2) lo.
            }

            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $dv = new DataValidation;
            $dv->setType(DataValidation::TYPE_LIST);
            $dv->setAllowBlank(true);
            $dv->setShowDropDown(true);
            $dv->setShowErrorMessage(false); // không chặn — import vẫn nhận cả nhãn tiếng Việt.
            $dv->setShowInputMessage(true);
            $dv->setPromptTitle('Giá trị hợp lệ');
            $dv->setPrompt(mb_substr((string) ($this->columnNotes[$field] ?? $csv), 0, 255));
            $dv->setFormula1('"'.$csv.'"');
            $sheet->setDataValidation($col.'2:'.$col.'1001', $dv);

            $handledByDropdown[$field] = true;
        }

        // 2. Comment ở ô header cho MỌI cột enum (liệt kê đầy đủ giá trị).
        //    - Cột có dropdown: comment để hover (bổ trợ, vì prompt đã hiện sẵn khi bấm ô).
        //    - Cột enum dài không dựng được dropdown: comment hiện sẵn (visible).
        foreach ($this->columnNotes as $field => $note) {
            $idx = array_search($field, $keys, true);
            if ($idx === false) {
                continue;
            }

            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $comment = $sheet->getComment($col.'1');
            $comment->getText()->createText($note);
            $comment->setWidth('360pt');
            $comment->setHeight('200pt');
            $comment->setVisible(! isset($handledByDropdown[$field]));
        }

        if (empty($this->exampleRow)) {
            return;
        }

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
