<?php

namespace App\Modules\TaskAssignment\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyReportExport implements WithMultipleSheets
{
    public function __construct(private string $month) {}

    public function sheets(): array
    {
        $sheets = [new MonthlyReportSummarySheet($this->month)];
        $usedTitles = ['Tổng hợp'];

        $departments = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::where('status', 'active')
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
