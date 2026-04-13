<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Exports\LogActivitiesExport;
use App\Modules\Core\Models\LogActivity;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogActivityService
{
    public function stats(array $filters): array
    {
        $stats = LogActivity::filter($filters)
            ->reorder()
            ->selectRaw("
                count(*) as total,
                count(case when method_type = 'GET' then 1 end) as views,
                count(case when method_type = 'POST' then 1 end) as creates,
                count(case when (method_type = 'PUT' or method_type = 'PATCH') then 1 end) as updates,
                count(case when method_type = 'DELETE' then 1 end) as deletes
            ")
            ->first();

        return [
            'total' => (int) $stats->total,
            'views' => (int) $stats->views,
            'creates' => (int) $stats->creates,
            'updates' => (int) $stats->updates,
            'deletes' => (int) $stats->deletes,
        ];
    }

    public function index(array $filters, int $limit)
    {
        return LogActivity::with('user', 'organization')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(LogActivity $logActivity): LogActivity
    {
        return $logActivity->load('user', 'organization');
    }

    public function destroy(LogActivity $logActivity): void
    {
        $logActivity->delete();
    }

    public function bulkDestroy(array $ids): int
    {
        return LogActivity::whereIn('id', $ids)->delete();
    }

    public function destroyByDate(string $fromDate, string $toDate): int
    {
        return LogActivity::whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->delete();
    }

    public function destroyAll(): int
    {
        $count = LogActivity::count();
        LogActivity::truncate();

        return $count;
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new LogActivitiesExport($filters), 'log-activities.xlsx');
    }
}
