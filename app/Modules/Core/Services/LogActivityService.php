<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Exports\LogActivitiesExport;
use App\Modules\Core\Models\LogActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

    /**
     * Timeline cho line chart dashboard: tự chọn grain day/month theo độ dài range.
     *
     * Rule: diff ≤ 62 ngày → day, ngược lại → month.
     * Không có from_date/to_date → mặc định 12 tháng gần nhất (grain month).
     *
     * @return array{granularity:string,data:array<int,array<string,mixed>>}
     */
    public function timeline(array $filters): array
    {
        $granularity = $this->resolveGranularity($filters);
        $format = $granularity === 'day' ? '%Y-%m-%d' : '%Y-%m';

        if (empty($filters['from_date']) && empty($filters['to_date'])) {
            $filters['from_date'] = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $filters['to_date'] = Carbon::now()->endOfMonth()->toDateString();
        }

        $rows = LogActivity::filter($filters)
            ->reorder()
            ->selectRaw("
                DATE_FORMAT(created_at, ?) as period,
                count(*) as total,
                count(case when method_type = 'GET' then 1 end) as views,
                count(case when method_type = 'POST' then 1 end) as creates,
                count(case when (method_type = 'PUT' or method_type = 'PATCH') then 1 end) as updates,
                count(case when method_type = 'DELETE' then 1 end) as deletes
            ", [$format])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'granularity' => $granularity,
            'data' => $rows->map(fn ($r) => [
                'period' => $r->period,
                'total' => (int) $r->total,
                'views' => (int) $r->views,
                'creates' => (int) $r->creates,
                'updates' => (int) $r->updates,
                'deletes' => (int) $r->deletes,
            ])->all(),
        ];
    }

    /**
     * Chọn grain dựa trên range. Thiếu date → month.
     */
    private function resolveGranularity(array $filters): string
    {
        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;

        if (! $from || ! $to) {
            return 'month';
        }

        try {
            $diffDays = Carbon::parse($from)->diffInDays(Carbon::parse($to));
        } catch (\Throwable) {
            return 'month';
        }

        return $diffDays <= 62 ? 'day' : 'month';
    }

    /**
     * Top N người dùng hoạt động nhiều nhất theo filter.
     */
    public function topUsers(array $filters, int $limit = 5): array
    {
        $rows = LogActivity::filter($filters)
            ->reorder()
            ->select('user_id', DB::raw('count(*) as total'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('user:id,name,email,user_name')
            ->get();

        return $rows->map(fn ($r) => [
            'user_id' => (int) $r->user_id,
            'name' => $r->user?->name,
            'email' => $r->user?->email,
            'user_name' => $r->user?->user_name,
            'total' => (int) $r->total,
        ])->all();
    }

    /**
     * Top N tổ chức hoạt động nhiều nhất theo filter.
     */
    public function topOrganizations(array $filters, int $limit = 5): array
    {
        $rows = LogActivity::filter($filters)
            ->reorder()
            ->select('organization_id', DB::raw('count(*) as total'))
            ->whereNotNull('organization_id')
            ->groupBy('organization_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('organization:id,name,slug')
            ->get();

        return $rows->map(fn ($r) => [
            'organization_id' => (int) $r->organization_id,
            'name' => $r->organization?->name,
            'slug' => $r->organization?->slug,
            'total' => (int) $r->total,
        ])->all();
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
