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
     * Keyword (lower-case, mb_strtolower) — cột có header chứa BẤT KỲ keyword nào sẽ được
     * căn giữa cả header + data rows. Match contains, không phải exact.
     *
     * Nguyên tắc chọn keyword: cột có data fixed-length (enum / boolean / số / date / datetime)
     * nên căn giữa; cột text dài (tên, nội dung, mô tả) giữ left-align.
     *
     * Subclass override `centerAlignedHeaderKeywords()` để thêm/đổi list cho riêng export đó.
     */
    protected const DEFAULT_CENTER_KEYWORDS = [
        'stt',
        'trạng thái',
        'thời gian',     // Thời gian bắt đầu, Thời gian đăng ký, ...
        'ngày',          // Ngày tạo, Ngày cập nhật, Ngày sinh, ...
        'giờ',           // Giờ điểm danh, ...
        'lượt',          // Lượt xem, Lượt tải, ...
        'công khai',     // boolean Có/Không
        'đính kèm',      // boolean Có/Không
        'phương thức',   // enum
        'loại',          // Loại cuộc họp, Loại tài liệu, ...
        'vai trò',       // enum role
        'đã ',           // Đã điểm danh, Đã xác nhận, ... (boolean Rồi/Chưa)
        'xác nhận',      // Xác nhận tham gia, ...
        'đồng ý',        // count cột phiếu biểu quyết: "Đồng ý", "Không đồng ý"
        'ý kiến',        // "Ý kiến khác" (vote summary)
        'số ',           // Số phiếu, Số lượng, Số người, ...
        'tổng',          // Tổng cộng, Tổng số, ...
    ];

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

                // Căn giữa cột theo keyword header (STT / Trạng thái / Thời gian + subclass override).
                $this->applyCenterAlignmentByHeaderKeywords($sheet, $lastColumn, $lastRow);

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

    /**
     * Override để thêm/đổi keyword căn giữa. Trả về mảng string lower-case.
     */
    protected function centerAlignedHeaderKeywords(): array
    {
        return self::DEFAULT_CENTER_KEYWORDS;
    }

    /**
     * Scan row 1 headers, match keyword (contains, case/diacritic-insensitive)
     * → căn giữa toàn column (header + data rows).
     */
    private function applyCenterAlignmentByHeaderKeywords(Worksheet $sheet, string $lastColumn, int $lastRow): void
    {
        $keywords = $this->centerAlignedHeaderKeywords();
        if (empty($keywords)) {
            return;
        }

        foreach ($this->iterateColumns('A', $lastColumn) as $col) {
            $header = (string) $sheet->getCell($col.'1')->getValue();
            $headerLower = mb_strtolower(trim($header));
            foreach ($keywords as $kw) {
                if ($headerLower !== '' && mb_strpos($headerLower, $kw) !== false) {
                    $sheet->getStyle("{$col}1:{$col}{$lastRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    break;
                }
            }
        }
    }

    /**
     * Generator yield column letters A..lastColumn (handle vượt Z: AA, AB...).
     */
    private function iterateColumns(string $start, string $end): \Generator
    {
        $current = $start;
        while (true) {
            yield $current;
            if ($current === $end) {
                break;
            }
            $current++;
        }
    }
}
