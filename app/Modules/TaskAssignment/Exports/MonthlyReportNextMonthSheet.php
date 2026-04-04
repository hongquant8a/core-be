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
 * Sheet chương trình công tác tháng tiếp theo.
 * Gồm: CV mới tạo cho tháng tới + CV tồn từ tháng trước chưa xong.
 */
class MonthlyReportNextMonthSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private string $nextMonth) {}

    public function title(): string
    {
        return 'CT tháng ' . Carbon::parse($this->nextMonth . '-01')->format('m-Y');
    }

    public function array(): array
    {
        $nextStart = Carbon::parse($this->nextMonth . '-01')->startOfMonth();
        $nextEnd = $nextStart->copy()->endOfMonth();

        $items = TaskAssignmentItem::with(['document', 'itemType', 'departments'])
            ->where(function ($q) use ($nextStart, $nextEnd) {
                $q->whereBetween('start_at', [$nextStart, $nextEnd])
                    ->orWhereBetween('end_at', [$nextStart, $nextEnd])
                    ->orWhere(function ($q2) use ($nextStart) {
                        $q2->whereNotIn('processing_status', ['done', 'cancelled'])
                            ->where('created_at', '<', $nextStart);
                    });
            })
            ->whereNotIn('processing_status', ['done', 'cancelled'])
            ->orderBy('start_at')
            ->get();

        $newCount = $items->filter(fn ($i) => $i->created_at >= $nextStart)->count();
        $carryCount = $items->count() - $newCount;

        $rows = [];

        // Header khu vực
        $rows[] = ['BAN TUYÊN GIÁO THÀNH ỦY'];                                                          // Row 1
        $rows[] = ['CHƯƠNG TRÌNH CÔNG TÁC THÁNG ' . $nextStart->format('m/Y')];                          // Row 2
        $rows[] = ["Tổng số: {$items->count()} công việc (mới: {$newCount}, chuyển tiếp: {$carryCount})"]; // Row 3
        $rows[] = [];                                                                                      // Row 4 blank

        // Row 5: Table header
        $rows[] = [
            'STT',
            'Tên công việc',
            'Văn bản giao việc',
            'Loại công việc',
            'Phòng ban thực hiện',
            'Bắt đầu',
            'Kết thúc',
            'Ưu tiên',
            'Tiến độ (%)',
            'Ghi chú',
        ];

        foreach ($items as $i => $item) {
            $deptNames = $item->departments->pluck('name')->join(', ');
            $isCarryOver = $item->created_at < $nextStart;

            $rows[] = [
                $i + 1,
                $item->name,
                $item->document?->name ?? '',
                $item->itemType?->name ?? '',
                $deptNames,
                $item->start_at?->format('d/m/Y'),
                $item->end_at?->format('d/m/Y'),
                $this->priorityLabel($item->priority),
                $item->completion_percent,
                $isCarryOver ? 'Chuyển tiếp' : 'Mới',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Merge header rows
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");

        // Table border + alignment
        if ($lastRow >= 5) {
            $sheet->getStyle("A5:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);
        }

        // Highlight carry-over rows
        for ($row = 6; $row <= $lastRow; $row++) {
            $note = $sheet->getCell("J{$row}")->getValue();
            if ($note === 'Chuyển tiếp') {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF2CC');
            }
        }

        // Column B wider
        $sheet->getColumnDimension('B')->setWidth(45);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '002060']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 13],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font' => ['italic' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            5 => [
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'Thấp',
            'medium' => 'Trung bình',
            'high' => 'Cao',
            'urgent' => 'Khẩn cấp',
            default => $priority,
        };
    }
}
