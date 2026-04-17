<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Notification;
use Illuminate\Http\Request;

/**
 * @group Core - My Notifications
 *
 * User-facing notification list (chỉ thao tác notifications của chính user đang đăng nhập).
 * Không cần permission gì khác ngoài auth:sanctum.
 */
class MyNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Notification::where('user_id', $user->id)
            ->orderByDesc('id');

        if ($request->filled('read')) {
            $read = filter_var($request->input('read'), FILTER_VALIDATE_BOOLEAN);
            $read ? $query->whereNotNull('read_at') : $query->whereNull('read_at');
        }
        if ($request->filled('event_key')) {
            $query->where('event_key', $request->input('event_key'));
        }

        $limit = (int) $request->input('limit', 20);

        return $this->success($query->paginate($limit));
    }

    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return $this->success(['unread_count' => $count]);
    }

    public function markAsRead(Request $request, int $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return $this->success($notification->fresh());
    }

    public function markAllAsRead(Request $request)
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success(['updated' => $updated], 'Đã đánh dấu tất cả là đã đọc');
    }

    public function destroy(Request $request, int $id)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        return $this->success(null, 'Đã xóa thông báo');
    }
}
