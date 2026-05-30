<?php

namespace App\Modules\Scheduling\Exports;

use App\Modules\Core\Models\Organization;
use App\Modules\Scheduling\Models\Schedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class WeeklySchedulePdfExporter
{
    private static array $daysMap = [
        1 => 'Thứ Hai',
        2 => 'Thứ Ba',
        3 => 'Thứ Tư',
        4 => 'Thứ Năm',
        5 => 'Thứ Sáu',
        6 => 'Thứ Bảy',
        0 => 'Chủ Nhật',
    ];

    public function generate(array $filters): string
    {
        $query = Schedule::with(['host', 'driver'])
            ->orderBy('event_date', 'asc')
            ->orderBy('session', 'asc')
            ->orderBy('sort_order', 'asc');

        // Apply filters
        if (!empty($filters['week_number'])) {
            $query->where('week_number', $filters['week_number']);
        }
        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        if (!empty($filters['module_type'])) {
            $query->where('module_type', $filters['module_type']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $schedulesList = $query->get();

        // Calculate date range of the week
        $dateFrom = '';
        $dateTo = '';
        if (!empty($filters['week_number']) && !empty($filters['year'])) {
            $carbon = Carbon::now()->setISODate($filters['year'], $filters['week_number']);
            $dateFrom = $carbon->startOfWeek()->format('d/m/Y');
            $dateTo = $carbon->endOfWeek()->format('d/m/Y');
        }

        // Format schedules
        $formattedSchedules = [];
        foreach ($schedulesList as $item) {
            $dayLabel = 'N/A';
            if ($item->event_date) {
                $dayOfWeek = $item->event_date->dayOfWeek;
                $dayName = self::$daysMap[$dayOfWeek] ?? '';
                $dateStr = $item->event_date->format('d/m/Y');
                $dayLabel = "{$dayName}\n({$dateStr})";
            }

            $sessionLabel = 'N/A';
            if ($item->session) {
                $val = is_object($item->session) ? $item->session->value : $item->session;
                if ($val === 'S') $sessionLabel = 'Sáng';
                elseif ($val === 'C') $sessionLabel = 'Chiều';
                elseif ($val === 'T') $sessionLabel = 'Tối';
            }

            $timeLabel = '';
            if ($item->start_time) {
                $timeLabel = substr($item->start_time, 0, 5);
                if ($item->end_time) {
                    $timeLabel .= ' - ' . substr($item->end_time, 0, 5);
                }
            }

            $hostName = $item->host ? $item->host->name : ($item->host_text ?? '');

            $formattedSchedules[] = [
                'day' => $dayLabel,
                'session' => $sessionLabel,
                'time' => $timeLabel,
                'content' => $item->content ?? '',
                'host' => $hostName,
                'location' => $item->location ?? '',
                'prep_unit' => $item->preparation_unit ?? '',
            ];
        }

        // Apply Rowspan computations
        $flatList = [];
        $dayGroups = [];
        foreach ($formattedSchedules as $s) {
            $day = $s['day'];
            $session = $s['session'];
            $dayGroups[$day][$session][] = $s;
        }

        foreach ($dayGroups as $day => $sessions) {
            $dayTotalRows = 0;
            foreach ($sessions as $session => $list) {
                $dayTotalRows += count($list);
            }

            $isFirstDayRow = true;
            foreach ($sessions as $session => $list) {
                $sessionTotalRows = count($list);
                $isFirstSessionRow = true;

                foreach ($list as $s) {
                    $s['day_rowspan'] = $isFirstDayRow ? $dayTotalRows : 0;
                    $s['session_rowspan'] = $isFirstSessionRow ? $sessionTotalRows : 0;

                    $flatList[] = $s;

                    $isFirstDayRow = false;
                    $isFirstSessionRow = false;
                }
            }
        }

        // Resolve current organization name
        $orgName = 'Văn Phòng';
        $orgId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        if ($orgId) {
            $org = Organization::find($orgId);
            if ($org) {
                $orgName = $org->name;
            }
        }

        $pdf = Pdf::loadView('scheduling.exports.pdf.weekly', [
            'organization_name' => $orgName,
            'week_number' => $filters['week_number'] ?? '',
            'year' => $filters['year'] ?? '',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'schedules' => $flatList,
        ]);

        $pdf->setPaper('a4', 'landscape');

        $tempPath = storage_path('app/' . uniqid('weekly_schedule_') . '.pdf');
        $pdf->save($tempPath);

        return $tempPath;
    }
}
