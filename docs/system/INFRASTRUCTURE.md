# Infrastructure — QLCV Backend

> Ngày tạo: 00:00:00 28/06/2026  
> Cập nhật lần cuối: 00:00:00 28/06/2026

Queue, Redis, Horizon, Reverb, deployment. Tham chiếu cho dev cần hiểu cơ sở hạ tầng hoặc debug queue/realtime.

---

## Redis — 3 connection riêng biệt

Driver: `predis/predis` (không cài phpredis extension).

| Connection env | Mục đích | Ghi chú |
|---|---|---|
| `REDIS_QUEUE_CONNECTION` | Queue driver (Horizon) | Không flush chung với cache |
| `REDIS_CACHE_CONNECTION` | Application cache | Flush độc lập khi cần |
| `REDIS_BROADCAST_CONNECTION` | Reverb pub-sub | Tách riêng để không xung đột |

**Lock:** Dùng `Cache::lock()` qua Redis driver — không tự implement lock tay.
```php
$lock = Cache::lock('zalo-oa-token-refresh', 10);
if ($lock->get()) {
    // refresh token
    $lock->release();
}
```

---

## Queue — Phân tầng theo mức độ ưu tiên

| Queue name | Dùng cho | Timeout | Retry |
|---|---|---|---|
| `urgent` | OTP, cảnh báo an toàn | Ngắn | Thấp |
| `notifications` | Zalo ZNS/OA, FCM, SMS, Email | Trung bình | Cao |
| `exports` | Export Word/Excel/PDF | Dài (5-10 phút) | Trung bình |
| `ai` | Gemini API, OCR | Dài | Thấp (tránh tốn token) |
| `sync` | n8n, webhook ngoài | Trung bình | Trung bình, có backoff |
| `default` | Việc nhẹ, không phân loại | Mặc định | Mặc định |

**Không dồn mọi job vào `default`.**

### Job convention
```php
class GuiZNSJob implements ShouldQueue
{
    public int $tries   = 3;
    public int $backoff = 60; // giây

    public function __construct(
        private readonly int $organizationId,  // KHÔNG dùng auth() trong job
        private readonly int $notificationId,
    ) {}

    public function queue(): string { return 'notifications'; }
}
```

**Job thất bại:** ghi `failed_jobs` + Listener nghe `JobFailed` → cảnh báo qua kênh nội bộ.

---

## Horizon — Quản lý Queue Worker

Config: `config/horizon.php`

### Supervisor per queue tier

```php
'environments' => [
    'production' => [
        'supervisor-urgent' => [
            'queue'      => ['urgent'],
            'balance'    => 'false',   // KHÔNG balance — luôn có worker rảnh
            'processes'  => 2,
        ],
        'supervisor-notifications' => [
            'queue'      => ['notifications'],
            'balance'    => 'auto',
            'maxProcesses' => 10,
        ],
        'supervisor-exports' => [
            'queue'   => ['exports'],
            'balance' => 'auto',
            'timeout' => 600,
        ],
        'supervisor-ai' => [
            'queue'   => ['ai'],
            'balance' => 'auto',
            'timeout' => 300,
            'tries'   => 1,            // retry thấp cho AI (tránh tốn token)
        ],
        'supervisor-default' => [
            'queue'   => ['default', 'sync'],
            'balance' => 'auto',
        ],
    ],
],
```

### Metrics
`horizon:snapshot` chạy mỗi 5 phút qua Schedule — **bắt buộc** để có biểu đồ trong Horizon UI.

```php
// routes/console.php
Schedule::command('horizon:snapshot')->everyFiveMinutes();
```

---

## Schedule (Cron)

Đăng ký trong `routes/console.php` (Laravel 11+). **Không sửa `Kernel.php`.**

```php
// routes/console.php
Schedule::command('notifications:process-reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();   // nếu multi-server

Schedule::command('horizon:snapshot')->everyFiveMinutes();
```

**Command pattern:**
- Command chỉ làm nhiệm vụ "kích hoạt" + loop qua từng org
- Việc nặng → dispatch Job vào queue
- Dùng `withoutGlobalScope` khi query cross-tenant

```php
// Ví dụ Command cross-tenant
Organization::withoutGlobalScopes()->active()->each(function ($org) {
    setPermissionsTeamId($org->id);
    ProcessOrgReminderJob::dispatch($org->id)->onQueue('notifications');
});
```

---

## Reverb — WebSocket Realtime

### Khi dùng
UI cần update realtime nhiều client cùng lúc: meeting presence, xếp hàng QR check-in, thông báo tức thì.

### Channel convention

| Loại | Pattern | Dùng cho |
|---|---|---|
| Private | `private-org.{org_id}.user.{user_id}` | Thông báo cá nhân |
| Presence | `presence-org.{org_id}.meeting.{meeting_id}` | Phòng họp, ai đang online |

### Authorization
```php
// routes/channels.php
Broadcast::channel('org.{orgId}.user.{userId}', function (User $user, int $orgId, int $userId) {
    return $user->id === $userId && $user->belongsToOrganization($orgId);
});
```

### Broadcast Event
```php
class MeetingParticipantJoined implements ShouldBroadcastAfterCommit
{
    public function broadcastOn(): array
    {
        return [new PresenceChannel("org.{$this->orgId}.meeting.{$this->meetingId}")];
    }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId, 'type' => 'joined']; // chỉ ID + type
        // client tự gọi API lấy full data — tránh leak dữ liệu nhạy cảm
    }
}
```

---

## Notification Engine

`app/Services/Notification/` — service xuyên module, không phải module nghiệp vụ.

### Luồng gửi notification
```
Module fire event / gọi NotificationDispatcher
  ↓
NotificationDispatcher tạo notification + N deliveries (1/channel)
  ↓
Push SendDeliveryJob → queue: notifications
  ↓
Horizon worker → channel sender (Zalo/FCM/Email/SMS)
  ↓
Update delivery.status = sent / failed / skipped
```

### Reminder flow (khác Instant)
```
Module set remind_at → lưu TaskAssignmentReminder
  ↓
Cron notifications:process-reminders (everyMinute)
  ↓
Quét row có remind_at <= now() → fire SendDeliveryJob
```

---

## Deployment

Server: `danatecsvr01`

```bash
# Deploy thủ công
git pull origin main
sail artisan migrate --force
sail artisan config:cache
sail artisan route:cache
sail artisan view:cache
sail artisan scribe:generate
sail artisan horizon:terminate  # Horizon tự restart sau khi terminate
```

**Lưu ý:** Sau deploy phải `horizon:terminate` để Horizon worker reload code mới. Horizon Supervisor (systemd/supervisord) sẽ tự start lại.
