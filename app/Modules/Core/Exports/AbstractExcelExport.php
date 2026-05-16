<?php

namespace App\Modules\Core\Exports;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Base class chuẩn hóa style Excel export toàn dự án.
 *
 * Style mặc định:
 *  - Font: Times New Roman 11 (tone công văn nhà nước)
 *  - Header row 1: bold, fill #1F4E78 (xanh đậm), text trắng, border full, center
 *  - Data rows: border full, vertical center
 *  - Auto-size columns
 *  - Freeze pane: row 2 (header pinned)
 *
 * Subclass:
 *  - Override `headings(): array` (WithHeadings)
 *  - Override `collection()` hoặc `array()` (FromCollection / FromArray)
 *  - Có thể override `customStyles()` / `customEvents()` để mở rộng style riêng
 */
abstract class AbstractExcelExport implements WithHeadings, WithStyles, ShouldAutoSize, WithEvents, WithDefaultStyles
{
    protected const HEADER_FILL_COLOR = '1F4E78';
    protected const HEADER_TEXT_COLOR = 'FFFFFF';
    protected const FONT_NAME = 'Times New Roman';
    protected const FONT_SIZE = 11;

    /**
     * Set default font TRƯỚC khi data ghi để PhpSpreadsheet auto-size tính theo đúng font.
     * Nếu set qua AfterSheet, auto-size đã tính theo Calibri (default) → Times New Roman rộng
     * hơn ~10% → text overflow → wrapText kích hoạt → cột ngắn (status) chỉ thấy 1 dòng đầu.
     */
    public function defaultStyles(Style $defaultStyle)
    {
        return [
            'font' => [
                'name' => self::FONT_NAME,
                'size' => self::FONT_SIZE,
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row 1
            1 => [
                'font' => [
                    'name' => self::FONT_NAME,
                    'size' => self::FONT_SIZE,
                    'bold' => true,
                    'color' => ['rgb' => self::HEADER_TEXT_COLOR],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::HEADER_FILL_COLOR],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Font Times New Roman 11 cho toàn sheet (default).
                $sheet->getParent()->getDefaultStyle()->getFont()->setName(self::FONT_NAME)->setSize(self::FONT_SIZE);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
                    'font' => [
                        'name' => self::FONT_NAME,
                        'size' => self::FONT_SIZE,
                    ],
                ]);

                // Border full cho toàn vùng có data.
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '999999'],
                        ],
                    ],
                ]);

                // Vertical center cho data rows.
                if ($lastRow > 1) {
                    $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);
                }

                // Header row height + freeze.
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->freezePane('A2');

                // Hook cho subclass mở rộng (vd format số tiền cột E, format ngày cột G, ...).
                $this->customEvents($event);
            },
        ];
    }

    /**
     * Hook cho subclass override custom style sau header/border default.
     * VD: format date cột G, hyperlink cột F, number format cột E.
     */
    protected function customEvents(AfterSheet $event): void
    {
        // no-op
    }
}
