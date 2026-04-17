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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet chi tiết 1 phòng ban: 3 cột song song (Đang giao | Hoàn thành | Trễ hạn)
 * + 3 cột để trống (Đề xuất / Xin ý kiến / Ghi chú) cho user tự điền sau khi export.
 */
class MonthlyReportDepartmentSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private string $month,
        private TaskAssignmentDepartment $department,
    ) {}

    public function title(): string
    {
        return mb_substr($this->department->code, 0, 31);
    }

    public function array(): array
    {
        $monthStart = Carbon::parse($this->month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $now = Carbon::now();

        $items = TaskAssignmentItem::whereHas(
            'users',
            fn ($q) => $q->where('task_assignment_item_user.department_id', $this->department->id)
        )
            ->where('created_at', '<=', $monthEnd)
            ->orderBy('end_at')
            ->get();

        $inFlight = [];
        $done = [];
        $overdue = [];
        foreach ($items as $item) {
            $bucket = MonthlyReportSummarySheet::classify($item, $now);
            if ($bucket === 'in_flight') {
                $inFlight[] = $item->name;
            } elseif ($bucket === 'done') {
                $done[] = $item->name;
            } elseif ($bucket === 'overdue') {
                $overdue[] = $item->name;
            }
        }

        $rows = [];

        $rows[] = [
            'TỔNG HỢP NHIỆM VỤ TRÊN PHẦN MỀM GIAO VIỆC CỦA CÁC PHÒNG, ĐƠN VỊ đến '
                . $monthEnd->format('j') . ' tháng ' . $monthEnd->format('n'),
        ];

        $rows[] = [
            $this->department->code,
            'NHIỆM VỤ ĐANG GIAO',
            'NHIỆM VỤ HOÀN THÀNH',
            'NHIỆM VỤ TRỄ HẠN',
            'ĐỀ XUẤT, KIẾN NGHỊ',
            'XIN Ý KIẾN BÁO CÁO LÃNH ĐẠO UBND TUẦN ĐẾN',
            'GHI CHÚ',
        ];

        $rows[] = [
            'Tổng hợp',
            count($inFlight),
            count($done),
            count($overdue),
            '',
            '',
            '',
        ];

        $maxLen = max(count($inFlight), count($done), count($overdue));
        for ($i = 0; $i < $maxLen; $i++) {
            $rows[] = [
                $i + 1,
                $inFlight[$i] ?? '',
                $done[$i] ?? '',
                $overdue[$i] ?? '',
                '',
                '',
                '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->mergeCells('A1:G1');

        if ($lastRow >= 2) {
            $sheet->getStyle("A2:G{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);
        }

        if ($lastRow >= 3) {
            $sheet->getStyle("A3:A{$lastRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B3:D3')->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(30);
        $sheet->getColumnDimension('G')->setWidth(15);

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
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            3 => [
                'font' => ['bold' => true],
            ],
        ];
    }
}
