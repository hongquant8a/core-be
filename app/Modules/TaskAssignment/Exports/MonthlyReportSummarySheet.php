<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemType;
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
 * Sheet tổng hợp: ma trận Phòng ban × Trạng thái × Loại công việc (A/B/C).
 * Đây là bảng chính phục vụ cuộc họp giao ban đầu tháng.
 */
class MonthlyReportSummarySheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    private array $itemTypes;

    private array $statusLabels;

    /** Row index nơi bảng dữ liệu bắt đầu (header row 1). */
    private int $tableStartRow = 6;

    public function __construct(private string $month)
    {
        $this->itemTypes = TaskAssignmentItemType::where('status', 'active')
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();

        $this->statusLabels = [
            'done' => 'Đã hoàn thành',
            'in_progress' => 'Đang thực hiện',
            'todo' => 'Chưa bắt đầu',
            'overdue_actual' => 'Quá hạn',
            'paused' => 'Tạm dừng',
            'cancelled' => 'Hủy/Không thực hiện',
        ];
    }

    public function title(): string
    {
        return 'Tổng hợp';
    }

    public function array(): array
    {
        $monthStart = Carbon::parse($this->month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $departments = TaskAssignmentDepartment::where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        $typeIds = array_keys($this->itemTypes);
        $typeLetters = [];
        foreach ($typeIds as $i => $id) {
            $typeLetters[$id] = chr(65 + $i);
        }

        $rows = [];

        // Header khu vực — 4 dòng trước bảng
        $rows[] = ['BAN TUYÊN GIÁO THÀNH ỦY'];                                        // Row 1
        $rows[] = ['BẢNG TỔNG HỢP CÔNG TÁC THÁNG ' . $monthStart->format('m/Y')];     // Row 2
        $rows[] = ['(Từ ' . $monthStart->format('d/m/Y') . ' đến ' . $monthEnd->format('d/m/Y') . ')']; // Row 3
        $rows[] = [];                                                                    // Row 4 blank

        // Row 5: Header group 1
        $header1 = ['Các phòng chuyên môn', 'Tổng số công việc'];
        $header1[] = 'Công việc trong tháng';
        foreach ($typeIds as $idx => $id) {
            if ($idx > 0) {
                $header1[] = '';
            }
        }
        foreach (array_keys($this->statusLabels) as $status) {
            $header1[] = $this->statusLabels[$status];
            for ($i = 1; $i < count($typeIds); $i++) {
                $header1[] = '';
            }
        }
        $rows[] = $header1;

        // Row 6: Header group 2 (type letters)
        $header2 = ['', ''];
        foreach ($typeIds as $id) {
            $header2[] = $typeLetters[$id] . '. ' . $this->itemTypes[$id];
        }
        foreach (array_keys($this->statusLabels) as $status) {
            foreach ($typeIds as $id) {
                $header2[] = $typeLetters[$id];
            }
        }
        $rows[] = $header2;

        // Data rows
        $grandTotal = array_fill_keys(array_merge(['total'], $typeIds, $this->flatStatusTypeKeys($typeIds)), 0);

        foreach ($departments as $dept) {
            $items = TaskAssignmentItem::whereHas('departments', fn ($q) => $q->where('department_id', $dept->id))
                ->where('created_at', '<=', $monthEnd)
                ->get();

            $row = [$dept->name];
            $total = $items->count();
            $row[] = $total;
            $grandTotal['total'] += $total;

            foreach ($typeIds as $typeId) {
                $count = $items->where('task_assignment_item_type_id', $typeId)->count();
                $row[] = $count;
                $grandTotal[$typeId] = ($grandTotal[$typeId] ?? 0) + $count;
            }

            $done = TaskProgressStatusEnum::Done->value;
            $cancelled = TaskProgressStatusEnum::Cancelled->value;
            $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

            foreach (array_keys($this->statusLabels) as $statusKey) {
                foreach ($typeIds as $typeId) {
                    $typeItems = $items->where('task_assignment_item_type_id', $typeId);

                    $count = match ($statusKey) {
                        'done' => $typeItems->where('processing_status', $done)->count(),
                        'in_progress' => $typeItems->where('processing_status', TaskProgressStatusEnum::InProgress->value)->count(),
                        'todo' => $typeItems->where('processing_status', TaskProgressStatusEnum::Todo->value)->count(),
                        'overdue_actual' => $typeItems->where('deadline_type', $hasDeadline)
                            ->filter(fn ($i) => $i->end_at && Carbon::parse($i->end_at)->isPast() && ! in_array($i->processing_status, [$done, $cancelled]))
                            ->count(),
                        'paused' => $typeItems->where('processing_status', TaskProgressStatusEnum::Paused->value)->count(),
                        'cancelled' => $typeItems->where('processing_status', $cancelled)->count(),
                    };

                    $row[] = $count;
                    $key = "{$statusKey}_{$typeId}";
                    $grandTotal[$key] = ($grandTotal[$key] ?? 0) + $count;
                }
            }

            $rows[] = $row;
        }

        // Totals row
        $totalsRow = ['TỔNG', $grandTotal['total']];
        foreach ($typeIds as $typeId) {
            $totalsRow[] = $grandTotal[$typeId] ?? 0;
        }
        foreach (array_keys($this->statusLabels) as $statusKey) {
            foreach ($typeIds as $typeId) {
                $totalsRow[] = $grandTotal[$statusKey . '_' . $typeId] ?? 0;
            }
        }
        $rows[] = $totalsRow;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Merge title rows
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");

        // Table range (from header row 5)
        $tableRange = "A5:{$lastCol}{$lastRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Department name column left-aligned
        $dataStart = 7; // first data row
        $sheet->getStyle("A{$dataStart}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Row heights
        $sheet->getRowDimension(5)->setRowHeight(30);
        $sheet->getRowDimension(6)->setRowHeight(25);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '002060']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font' => ['italic' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            5 => [
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            ],
            6 => [
                'font' => ['bold' => true, 'size' => 9],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E4F0']],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']],
            ],
        ];
    }

    private function flatStatusTypeKeys(array $typeIds): array
    {
        $keys = [];
        foreach (array_keys($this->statusLabels) as $status) {
            foreach ($typeIds as $typeId) {
                $keys[] = "{$status}_{$typeId}";
            }
        }

        return $keys;
    }
}
