<?php

namespace App\Modules\TaskAssignment\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyReportExport implements WithMultipleSheets
{
    /**
     * @param  array<int>|null  $departmentIds  null = mọi phòng ban (viewAll);
     *                                          mảng = giới hạn theo phạm vi người dùng.
     */
    public function __construct(private string $month, private ?array $departmentIds = null) {}

    public function sheets(): array
    {
        $sheets = [new MonthlyReportSummarySheet($this->month, $this->departmentIds)];
        $usedTitles = ['Tổng hợp'];

        $departments = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::where('status', 'active')
            ->when($this->departmentIds !== null, fn ($q) => $q->whereIn('id', $this->departmentIds))
            ->orderBy('sort_order')
            ->get();

        foreach ($departments as $dept) {
            $sheet = new MonthlyReportDepartmentSheet($this->month, $dept);
            $title = $sheet->title();

            // Dedup: đảm bảo tên sheet không trùng
            if (in_array($title, $usedTitles, true)) {
                $suffix = 2;
                while (in_array("{$title} ({$suffix})", $usedTitles, true)) {
                    $suffix++;
                }
                $title = "{$title} ({$suffix})";
                $sheet->setTitle($title);
            }
            $usedTitles[] = $title;

            $sheets[] = $sheet;
        }

        return $sheets;
    }
}
