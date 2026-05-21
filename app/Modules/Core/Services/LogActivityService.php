<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Exports\LogActivitiesExport;
use App\Modules\Core\Models\LogActivity;
use App\Modules\Core\Support\ExportFilename;
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

        // Calculate % change for total visits (Last 7 days vs Previous 7 days)
        // If a specific from_date/to_date is passed, we could dynamically adjust,
        // but typically "so với tuần trước" implies a rolling 7-day comparison.
        $now = Carbon::now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $fourteenDaysAgo = $now->copy()->subDays(14);

        $thisWeekTotal = LogActivity::filter($filters)
            ->whereBetween('created_at', [$sevenDaysAgo, $now])
            ->count();

        $lastWeekTotal = LogActivity::filter($filters)
            ->whereBetween('created_at', [$fourteenDaysAgo, $sevenDaysAgo])
            ->count();

        $change = 0;
        if ($lastWeekTotal > 0) {
            $change = round((($thisWeekTotal - $lastWeekTotal) / $lastWeekTotal) * 100, 1);
        } elseif ($thisWeekTotal > 0) {
            $change = 100;
        }

        return [
            'total' => (int) $stats->total,
            'views' => (int) $stats->views,
            'creates' => (int) $stats->creates,
            'updates' => (int) $stats->updates,
            'deletes' => (int) $stats->deletes,
            'change' => $change,
        ];
    }

    /**
     * Timeline cho line chart. Pad mốc trống = 0.
     *
     * Filters hỗ trợ: from_date / to_date / user_id / organization_id / method_type / status_code / search.
     * Range cố định nếu có from_date+to_date; không thì tự dò từ MIN(created_at) → now.
     *
     * @return array{granularity:string,data:array<int,array<string,mixed>>}
     */
    public function timeline(string $granularity = 'month', array $filters = []): array
    {
        $granularity = $granularity === 'day' ? 'day' : 'month';
        $format = $granularity === 'day' ? '%Y-%m-%d' : '%Y-%m';

        // Range: nếu FE truyền from_date+to_date → dùng đó (bucket cố định cho chart "T1..T12 năm 2026").
        // Không thì auto từ log sớm nhất → hiện tại.
        if (! empty($filters['from_date']) && ! empty($filters['to_date'])) {
            $start = Carbon::parse($filters['from_date'])->startOf($granularity === 'day' ? 'day' : 'month');
            $end = Carbon::parse($filters['to_date'])->endOf($granularity === 'day' ? 'day' : 'month');
        } else {
            $earliest = LogActivity::filter($filters)->reorder()->min('created_at');
            if (! $earliest) {
                return ['granularity' => $granularity, 'data' => []];
            }
            $start = Carbon::parse($earliest)->startOf($granularity === 'day' ? 'day' : 'month');
            $end = Carbon::now()->endOf($granularity === 'day' ? 'day' : 'month');
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
            ->get()
            ->keyBy('period');

        $data = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $granularity === 'day' ? $cursor->format('Y-m-d') : $cursor->format('Y-m');
            $row = $rows->get($key);
            $data[] = [
                'period' => $key,
                'total' => $row ? (int) $row->total : 0,
                'views' => $row ? (int) $row->views : 0,
                'creates' => $row ? (int) $row->creates : 0,
                'updates' => $row ? (int) $row->updates : 0,
                'deletes' => $row ? (int) $row->deletes : 0,
            ];
            $granularity === 'day' ? $cursor->addDay() : $cursor->addMonth();
        }

        return [
            'granularity' => $granularity,
            'data' => $data,
        ];
    }

    /**
     * Top N người dùng hoạt động nhiều nhất theo filter.
     * Bao gồm 1 row "Khách" gộp các log có user_id = NULL (anonymous traffic).
     */
    public function topUsers(array $filters, int $limit = 5): array
    {
        $rows = LogActivity::filter($filters)
            ->reorder()
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('user:id,name,email,user_name')
            ->get();

        return $rows->map(fn ($r) => $r->user_id === null
            ? [
                'user_id' => null,
                'name' => 'Khách',
                'email' => null,
                'user_name' => null,
                'total' => (int) $r->total,
            ]
            : [
                'user_id' => (int) $r->user_id,
                'name' => $r->user?->name,
                'email' => $r->user?->email,
                'user_name' => $r->user?->user_name,
                'total' => (int) $r->total,
            ])->all();
    }

    /**
     * Top N tổ chức hoạt động nhiều nhất theo filter.
     * Bao gồm 1 row "Không xác định" gộp các log có organization_id = NULL.
     */
    public function topOrganizations(array $filters, int $limit = 5): array
    {
        $rows = LogActivity::filter($filters)
            ->reorder()
            ->select('organization_id', DB::raw('count(*) as total'))
            ->groupBy('organization_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('organization:id,name,slug')
            ->get();

        return $rows->map(fn ($r) => $r->organization_id === null
            ? [
                'organization_id' => null,
                'name' => 'Không xác định',
                'slug' => null,
                'total' => (int) $r->total,
            ]
            : [
                'organization_id' => (int) $r->organization_id,
                'name' => $r->organization?->name,
                'slug' => $r->organization?->slug,
                'total' => (int) $r->total,
            ])->all();
    }

    /**
     * Aggregate snapshot for dashboard. Single controller call → middleware logs +1 row total
     * → all four sub-results count the same dataset.
     *
     * @return array{stats:array,timeline:array,top_users:array,top_organizations:array}
     */
    public function dashboard(
        array $filters,
        int $topUsersLimit = 5,
        int $topOrganizationsLimit = 5,
        string $granularity = 'month',
    ): array {
        return [
            'stats' => $this->stats($filters),
            'timeline' => $this->timeline($granularity, $filters),
            'top_users' => $this->topUsers($filters, $topUsersLimit),
            'top_organizations' => $this->topOrganizations($filters, $topOrganizationsLimit),
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
        return Excel::download(new LogActivitiesExport($filters), ExportFilename::make('nhat-ky-hoat-dong'));
    }
}
