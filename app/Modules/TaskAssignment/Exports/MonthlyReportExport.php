<?php

namespace App\Modules\TaskAssignment\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyReportExport implements WithMultipleSheets
{
    public function __construct(private string $month) {}

    public function sheets(): array
    {
        $sheets = [new MonthlyReportSummarySheet($this->month)];

        $departments = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        foreach ($departments as $dept) {
            $sheets[] = new MonthlyReportDepartmentSheet($this->month, $dept);
        }

        return $sheets;
    }
}
