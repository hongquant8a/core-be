<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet tổng hợp: bảng 7 cột theo mẫu UBND (STT / Đơn vị / Tổng / Đang giao / Hoàn thành / Trễ hạn / Ghi chú).
 */
class MonthlyReportSummarySheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    /**
     * Classify an item into one of 4 buckets for the monthly report.
     *
     * Order matters: overdue is checked first so an in_progress task past its
     * deadline falls into 'overdue', not 'in_flight'. Done is terminal —
     * a completed task stays 'done' even if its deadline passed.
     *
     * @param  object  $item  Must expose processing_status, deadline_type, end_at (Carbon|string|null).
     * @return 'in_flight'|'done'|'overdue'|'other'
     */
    public static function classify(object $item, Carbon $now): string
    {
        if ($item->processing_status === 'done') {
            return 'done';
        }

        $isOverdue = $item->deadline_type === 'has_deadline'
            && $item->end_at
            && Carbon::parse($item->end_at)->lt($now)
            && ! in_array($item->processing_status, ['done', 'cancelled'], true);

        if ($isOverdue) {
            return 'overdue';
        }

        if (in_array($item->processing_status, ['todo', 'in_progress'], true)) {
            return 'in_flight';
        }

        return 'other';
    }

    public function __construct(private string $month) {}

    public function title(): string
    {
        return 'Tổng hợp';
    }

    public function array(): array
    {
        $monthStart = Carbon::parse($this->month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $now = Carbon::now();

        $departments = TaskAssignmentDepartment::where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        $rows = [];

        $rows[] = [
            'TỔNG HỢP NHIỆM VỤ TRÊN PHẦN MỀM GIAO VIỆC CỦA CÁC PHÒNG, ĐƠN VỊ đến '
                . $monthEnd->format('j') . ' tháng ' . $monthEnd->format('n'),
        ];

        $rows[] = [
            'STT',
            'ĐƠN VỊ',
            'TỔNG NHIỆM VỤ ĐÃ GIAO',
            'NHIỆM VỤ ĐANG GIAO',
            'NHIỆM VỤ HOÀN THÀNH',
            'NHIỆM VỤ TRỄ HẠN',
            'GHI CHÚ',
        ];

        $totals = ['total' => 0, 'in_flight' => 0, 'done' => 0, 'overdue' => 0];

        foreach ($departments as $i => $dept) {
            $items = TaskAssignmentItem::whereHas(
                'users',
                fn ($q) => $q->where('task_assignment_item_user.department_id', $dept->id)
            )
                ->where('created_at', '<=', $monthEnd)
                ->get();

            $counts = ['in_flight' => 0, 'done' => 0, 'overdue' => 0, 'other' => 0];
            foreach ($items as $item) {
                $counts[self::classify($item, $now)]++;
            }

            $total = $items->count();
            $rows[] = [
                $this->toRoman($i + 1),
                $dept->name,
                $total,
                $counts['in_flight'],
                $counts['done'],
                $counts['overdue'],
                '',
            ];

            $totals['total'] += $total;
            $totals['in_flight'] += $counts['in_flight'];
            $totals['done'] += $counts['done'];
            $totals['overdue'] += $counts['overdue'];
        }

        $rows[] = [
            '',
            'TỔNG CỘNG',
            $totals['total'],
            $totals['in_flight'],
            $totals['done'],
            $totals['overdue'],
            '',
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->mergeCells('A1:G1');

        $sheet->getStyle("A2:G{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        if ($lastRow >= 3) {
            $sheet->getStyle("B3:B{$lastRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(50);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            $lastRow => [
                'font' => ['bold' => true],
            ],
        ];
    }

    private function toRoman(int $n): string
    {
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        $out = '';
        foreach ($map as $value => $symbol) {
            while ($n >= $value) {
                $out .= $symbol;
                $n -= $value;
            }
        }

        return $out;
    }
}
