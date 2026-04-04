<?php

namespace App\Modules\TaskAssignment\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyReportExport implements WithMultipleSheets
{
    public function __construct(
        private string $month,
        private ?string $nextMonth = null,
    ) {
        if (! $this->nextMonth) {
            $this->nextMonth = \Carbon\Carbon::parse($this->month . '-01')->addMonth()->format('Y-m');
        }
    }

    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Bảng tổng hợp (phòng × trạng thái × loại A/B/C)
        $sheets[] = new MonthlyReportSummarySheet($this->month);

        // Sheet 2-8: Chi tiết từng phòng ban
        $departments = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        foreach ($departments as $dept) {
            $sheets[] = new MonthlyReportDepartmentSheet($this->month, $dept);
        }

        // Sheet cuối: Chương trình công tác tháng tiếp theo
        $sheets[] = new MonthlyReportNextMonthSheet($this->nextMonth);

        return $sheets;
    }
}
