<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * @group Core - Notification Logs (Admin)
 *
 * Xem lịch sử gửi thông báo trong phạm vi 1 module + 1 organization hiện tại.
 * Module được set qua middleware `notification.module:{key}`.
 * Organization lấy từ Spatie team context (getPermissionsTeamId()).
 */
class NotificationLogController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->scopedQuery($request)
            ->with(['user:id,name,email', 'deliveries'])
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        $limit = (int) $request->input('limit', 20);

        return $this->success($query->paginate($limit));
    }

    public function show(Request $request, int $id)
    {
        $notification = $this->scopedQuery($request)
            ->with(['user:id,name,email', 'deliveries'])
            ->where('id', $id)
            ->firstOrFail();

        return $this->success($notification);
    }

    public function stats(Request $request)
    {
        $baseQuery = $this->scopedQuery($request);
        $this->applyFilters($baseQuery, $request);

        $total = (clone $baseQuery)->count();
        $today = (clone $baseQuery)->whereDate('created_at', now()->toDateString())->count();
        $thisWeek = (clone $baseQuery)->where('created_at', '>=', now()->startOfWeek())->count();

        $byEvent = (clone $baseQuery)
            ->select('event_key', DB::raw('COUNT(*) as count'))
            ->groupBy('event_key')
            ->pluck('count', 'event_key');

        $deliveryQuery = NotificationDelivery::query()
            ->whereIn('notification_id', (clone $baseQuery)->select('id'));

        $byStatus = (clone $deliveryQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $byChannel = (clone $deliveryQuery)
            ->select('channel', DB::raw('COUNT(*) as count'))
            ->groupBy('channel')
            ->pluck('count', 'channel');

        return $this->success([
            'total' => $total,
            'today' => $today,
            'this_week' => $thisWeek,
            'by_event' => $byEvent,
            'by_status' => $byStatus,
            'by_channel' => $byChannel,
        ]);
    }

    /**
     * Base query scoped theo module (event_key IN module events) + organization hiện tại.
     */
    private function scopedQuery(Request $request)
    {
        $moduleKey = $request->attributes->get('notification_module_key');
        if (! $moduleKey) {
            throw new RuntimeException('Notification module context missing (middleware notification.module chưa set).');
        }

        $module = NotificationModuleEnum::from($moduleKey);
        $eventKeys = collect(NotificationEventEnum::cases())
            ->filter(fn ($e) => $e->module() === $module)
            ->map(fn ($e) => $e->value)
            ->values()
            ->all();

        return Notification::query()
            ->where('organization_id', $this->currentOrganizationId())
            ->whereIn('event_key', $eventKeys);
    }

    private function currentOrganizationId(): int
    {
        $teamId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        if (! $teamId) {
            throw new RuntimeException('Organization context required. Set X-Team-ID header or equivalent.');
        }

        return (int) $teamId;
    }

    /**
     * Apply filters cho notification query.
     */
    private function applyFilters($query, Request $request): void
    {
        $query->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->input('user_id')))
            ->when($request->filled('event_key'), fn ($q) => $q->where('event_key', $request->input('event_key')))
            ->when($request->filled('notifiable_type'), fn ($q) => $q->where('notifiable_type', $request->input('notifiable_type')))
            ->when($request->filled('notifiable_id'), fn ($q) => $q->where('notifiable_id', $request->input('notifiable_id')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to_date')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(fn ($q2) => $q2->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%"));
            })
            ->when($request->filled('delivery_status') || $request->filled('channel'), function ($q) use ($request) {
                $q->whereHas('deliveries', function ($dq) use ($request) {
                    $dq->when($request->filled('delivery_status'), fn ($dq2) => $dq2->where('status', $request->input('delivery_status')))
                        ->when($request->filled('channel'), fn ($dq2) => $dq2->where('channel', $request->input('channel')));
                });
            });
    }
}
