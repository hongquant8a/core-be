<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
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
 * Sheet chi tiết công việc của 1 phòng ban trong tháng.
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

        $items = TaskAssignmentItem::with(['document', 'itemType', 'users', 'reports'])
            ->whereHas('users', fn ($q) => $q->where('task_assignment_item_user.department_id', $this->department->id))
            ->where('created_at', '<=', $monthEnd)
            ->orderByRaw("FIELD(processing_status, 'in_progress', 'todo', 'done', 'overdue', 'paused', 'cancelled')")
            ->orderBy('end_at')
            ->get();

        $doneCount = $items->where('processing_status', 'done')->count();
        $ipCount = $items->where('processing_status', 'in_progress')->count();
        $todoCount = $items->where('processing_status', 'todo')->count();

        $rows = [];

        // Header khu vực
        $rows[] = ['BAN TUYÊN GIÁO THÀNH ỦY'];                                                  // Row 1
        $rows[] = ['BÁO CÁO CÔNG TÁC THÁNG ' . $monthStart->format('m/Y')];                     // Row 2
        $rows[] = [mb_strtoupper($this->department->name, 'UTF-8')];                              // Row 3
        $rows[] = ["Tổng: {$items->count()} | Hoàn thành: {$doneCount} | Đang làm: {$ipCount} | Chưa bắt đầu: {$todoCount}"]; // Row 4
        $rows[] = [];                                                                              // Row 5 blank

        // Row 6: Table header
        $rows[] = [
            'STT',
            'Tên công việc',
            'Văn bản giao việc',
            'Loại công việc',
            'Bắt đầu',
            'Kết thúc',
            'Trạng thái',
            'Hoàn thành (%)',
            'Ưu tiên',
            'Người thực hiện',
            'Số VB báo cáo',
            'Ngày hoàn thành',
        ];

        foreach ($items as $i => $item) {
            $status = TaskProgressStatusEnum::tryFrom($item->processing_status);
            $users = $item->users->pluck('name')->join(', ');
            $latestReport = $item->reports->sortByDesc('completed_at')->first();

            $rows[] = [
                $i + 1,
                $item->name,
                $item->document?->name ?? '',
                $item->itemType?->name ?? '',
                $item->start_at?->format('d/m/Y'),
                $item->end_at?->format('d/m/Y'),
                $status?->label() ?? $item->processing_status,
                $item->completion_percent,
                $this->priorityLabel($item->priority),
                $users,
                $latestReport?->report_document_number ?? '',
                $item->completed_at?->format('d/m/Y'),
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
        $sheet->mergeCells("A4:{$lastCol}4");

        // Table border + alignment
        if ($lastRow >= 6) {
            $sheet->getStyle("A6:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);
        }

        // Color rows by status
        for ($row = 7; $row <= $lastRow; $row++) {
            $statusCell = $sheet->getCell("G{$row}")->getValue();
            $color = match ($statusCell) {
                'Hoàn thành' => 'C6EFCE',
                'Đang thực hiện' => 'BDD7EE',
                'Chưa bắt đầu' => 'FFF2CC',
                'Quá hạn' => 'FFC7CE',
                'Tạm dừng' => 'E0E0E0',
                'Đã hủy' => 'D9D9D9',
                default => null,
            };

            if ($color) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($color);
            }
        }

        // Column B (tên công việc) wider
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
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'C00000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            4 => [
                'font' => ['italic' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            6 => [
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
