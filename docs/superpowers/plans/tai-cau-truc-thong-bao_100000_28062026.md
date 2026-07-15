# Kế Hoạch Tái Cấu Trúc Hệ Thống Thông Báo

> Ngày tạo: 10:00:00 28/06/2026  
> Cập nhật lần cuối: 15:10:25 15/07/2026

## 0. Trạng thái triển khai (15/07/2026)

Kế hoạch này **đã triển khai xong Phase 1-3** (migration `2026_06_22_214321_create_reminders_table`,
`2026_06_22_214321_migrate_reminders_data`, `2026_06_22_214322_drop_old_reminder_tables` đều đã chạy;
3 bảng/3 scheduler/3 model reminder cũ đã bị xóa). Thiết kế `Remindable` interface, `Reminder` model,
`ReminderScheduler`, `ProcessRemindersCommand` hợp nhất khớp gần như 100% với mô tả trong file này.

**2 khác biệt so với kế hoạch gốc, đã cập nhật lại ở mục 3.1/1.3/4/7 bên dưới:**
1. **Namespace thực tế khác** — không dùng `app/Modules/Notification/...` như đề xuất ban đầu, mà giữ
   `app/Services/Notification/Contracts/Remindable.php`, `app/Services/Notification/Services/ReminderScheduler.php`,
   `app/Services/Notification/Console/ProcessRemindersCommand.php`, và model đặt tại `app/Models/Reminder.php`
   (không nằm trong `app/Modules/`).
2. **Mục 4 "Danh Sách Nơi Kích Hoạt Event" đã lỗi thời ngay khi viết** — plan giả định giữ nguyên Meeting/Scheduling
   fire qua Observer, nhưng thực tế (cùng đợt refactor hoặc ngay sau đó) toàn bộ event Meeting + Scheduling đã
   chuyển sang fire từ Service, theo đúng Fix 1 của
   [notification-flow-audit_110152_28062026.md](../../answer/notification-flow-audit_110152_28062026.md).
   Observer (`MeetingObserver`, `ScheduleObserver`) hiện chỉ còn lo scheduling/cancel reminder.
3. `ProcessRemindersCommand` thực tế có thêm 1 query riêng cho **instant CUSTOM reminder** (per-record,
   `reminder_type='instant'`, `remind_at IS NULL`) ngoài query "pending + remind_at <= now()" mô tả ở mục 3.5 —
   liên quan pattern mô tả trong `per-record-instant-notification-channels.txt`.

**Phạm vi:** Backend (`qlcv`) — toàn bộ notification flow, reminder tables  
**Mục tiêu:** Loại bỏ code lặp, thống nhất cơ chế scheduler và xử lý reminder, một bảng reminder thay vì 3

> **Lưu ý tuân thủ CLAUDE.md:** Kế hoạch này KHÔNG thay đổi nơi fire Event. Theo quy tắc EDA:
> - **Observer = data integrity (mức model)** — không dùng Observer để fire business Event hoặc gửi Notification
> - **Event từ Service = business meaning** — giữ nguyên tại Service để kiểm soát rõ KHI NÀO fire
> - Việc chuyển event sang Observer (MeetingPublished, TaskConfirmed, TaskCompleted) vi phạm nguyên tắc này

---

## 1. Hiện Trạng & Vấn Đề

### 1.1 Ba bảng reminder không đồng nhất

| Field | `task_assignment_reminders` | `meeting_reminders` | `schedule_reminders` |
|-------|-----------------------------|---------------------|----------------------|
| `moment` type | `varchar(10) nullable` | `string nullable` | `enum(before,on,after) nullable` |
| `status` values | `pending/fired/cancelled/active` | `pending/fired/cancelled/sent` | `active/pending/fired/cancelled` |
| `reminder_type` values | `instant/scheduled` | `manual/scheduled` | `instant/scheduled` |
| `organization_id` | ✗ (scope qua document) | ✓ | ✗ |
| `message` / `created_by` | ✗ | ✓ | ✗ |
| `scheduled_at` / `sent_at` | ✗ | ✓ | ✗ |
| `$timestamps` | auto | auto | **false** (thủ công) |

**Code bị lặp y hệt nhau ở 3 nơi:**
- `computeRemindAt()` — 3 class scheduler copy/paste
- Channel resolution logic — 3 method trong `ProcessRemindersCommand`
- DELETE PRESET → CREATE PRESET → UPDATE CUSTOM — 3 scheduler classes

### 1.2 Ba ReminderScheduler gần như giống nhau

```
TaskAssignmentReminderScheduler  → scheduleFor(TaskAssignmentItem)
MeetingReminderScheduler         → scheduleFor(Meeting)
ScheduleReminderScheduler        → scheduleFor(Schedule)
```

`ProcessRemindersCommand` phải gọi 3 method riêng:
```php
TaskAssignmentReminder::...->chunkById(100, fn($r) => $this->fireReminder($r));
MeetingReminder::...->chunkById(100,   fn($r) => $this->fireMeetingReminder($r));
ScheduleReminder::...->chunkById(100,  fn($r) => $this->fireScheduleReminder($r));
```

### 1.3 Cơ chế kích hoạt Event (cập nhật 15/07/2026 — đã chuyển 100% sang Service)

| Module | Cơ chế hiện tại | Đánh giá |
|--------|-----------------|----------|
| **TaskAssignment** | Service fire TaskAssigned, TaskConfirmed, TaskCompleted, DocumentIssued | ✅ Đúng — business event từ Service |
| **Meeting** | Service fire MeetingPublished, MeetingUpdated, MeetingCancelled (`MeetingService.php`) | ✅ Đúng — `MeetingUpdated` đã chuyển từ Observer sang Service sau khi plan này viết |
| **Scheduling** | Service fire SchedulePublished, ScheduleUpdated, ScheduleCancelled (`ScheduleService.php`) | ✅ Đúng — đã chuyển từ Observer sang Service, theo Fix 1 của audit doc |

---

## 2. Mục Tiêu Sau Tái Cấu Trúc

1. **1 bảng `reminders`** (polymorphic) thay cho 3 bảng riêng
2. **1 `ReminderScheduler`** thay cho 3 class
3. **1 `ProcessRemindersCommand::fire()`** thay cho 3 method
4. **Tất cả module implement `Remindable` interface** để scheduler và command làm việc đồng nhất
5. **Giữ nguyên toàn bộ cơ chế fire Event** — không thay đổi

---

## 3. Thiết Kế Mới

### 3.1 Bảng `reminders` (thay thế 3 bảng cũ)

```sql
CREATE TABLE reminders (
    id                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Polymorphic: TaskAssignmentItem | Meeting | Schedule
    remindable_type           VARCHAR(255) NOT NULL,
    remindable_id             BIGINT UNSIGNED NOT NULL,

    organization_id           BIGINT UNSIGNED NULL,

    reminder_type             VARCHAR(20) NOT NULL DEFAULT 'scheduled',
    -- 'scheduled' = bắn theo remind_at
    -- 'manual'    = admin tạo thủ công (chỉ Meeting dùng)

    source                    VARCHAR(10) NOT NULL DEFAULT 'PRESET',
    -- 'PRESET' = lấy channels từ notification_schedule
    -- 'CUSTOM' = dùng channels trong cột channels

    notification_schedule_id  BIGINT UNSIGNED NULL,

    -- Timing
    moment                    VARCHAR(10) NULL,   -- 'before' | 'on' | 'after' | NULL
    offset_minutes            INT NULL,
    remind_at                 DATETIME NULL,

    -- Channels
    channels                  JSON NULL,          -- ['sms', 'mail', 'fcm', 'zalo', 'zalo_zns', 'telegram']

    -- Trạng thái (thống nhất)
    status                    VARCHAR(20) NOT NULL DEFAULT 'pending',
    -- 'pending'   = chờ fire
    -- 'fired'     = đã fire
    -- 'cancelled' = bị hủy
    fired_at                  DATETIME NULL,

    -- Chỉ Meeting manual reminder dùng
    message                   TEXT NULL,
    created_by                BIGINT UNSIGNED NULL,

    created_at                DATETIME NULL,
    updated_at                DATETIME NULL,

    INDEX idx_remindable (remindable_type, remindable_id),
    INDEX idx_status_remind_at (status, remind_at),
    INDEX idx_org_status (organization_id, status),

    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (notification_schedule_id) REFERENCES notification_schedules(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

**Lưu ý thiết kế:**
- `scheduled_at` và `sent_at` của `meeting_reminders` bị loại bỏ: `remind_at` thay thế `scheduled_at`; audit delivery đã có `notification_deliveries.sent_at`
- `message` và `created_by` giữ lại (nullable) cho manual reminder của Meeting — không tạo bảng riêng

**Migration steps:**
1. `create_reminders_table` — tạo bảng mới
2. `migrate_reminders_data` — copy data từ 3 bảng cũ (normalize status/moment)
3. `drop_old_reminder_tables` — xóa 3 bảng cũ (sau khi verify)

### 3.2 Interface `Remindable`

```php
// app/Modules/Notification/Contracts/Remindable.php
namespace App\Modules\Notification\Contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

interface Remindable
{
    // Mốc thời gian để tính remind_at (end_at / start_time / date_time+session)
    public function getReminderDeadline(): ?Carbon;

    // Organization context
    public function getReminderOrganizationId(): int;

    // module_key dùng để query NotificationEventConfig
    public function getReminderModuleKey(): string;

    // event_keys dùng để query NotificationEventConfig
    public function getReminderEventKeys(): array;

    // event_key truyền vào NotificationDispatcher khi fire
    public function getReminderEventKey(?string $moment): string;

    // User nhận reminder
    public function resolveReminderRecipients(): Collection;

    // Guest nhận reminder (chỉ Meeting có guest)
    public function resolveGuestReminderRecipients(): Collection;

    // Kiểm tra model còn hợp lệ để fire reminder không
    public function isValidForReminder(): bool;

    // Morph relation
    public function reminders(): MorphMany;
}
```

### 3.3 Implement `Remindable` trên từng Model

```php
// TaskAssignmentItem
class TaskAssignmentItem extends Model implements Remindable
{
    public function getReminderDeadline(): ?Carbon
    {
        return $this->end_at;
    }

    public function getReminderOrganizationId(): int
    {
        return (int) ($this->document?->organization_id ?? 0);
    }

    public function getReminderModuleKey(): string
    {
        return NotificationModuleEnum::TaskAssignment->value;
    }

    public function getReminderEventKeys(): array
    {
        return [
            NotificationEventEnum::ReminderBefore->value,
            NotificationEventEnum::ReminderOn->value,
            NotificationEventEnum::ReminderAfter->value,
        ];
    }

    public function getReminderEventKey(?string $moment): string
    {
        return "reminder_{$moment}";
    }

    public function resolveReminderRecipients(): Collection
    {
        return $this->users;
    }

    public function resolveGuestReminderRecipients(): Collection
    {
        return collect();
    }

    public function isValidForReminder(): bool
    {
        $this->loadMissing('document');

        return !in_array($this->processing_status, ['done', 'cancelled'], true)
            && ($this->document?->status ?? null) === TaskAssignmentDocumentStatusEnum::Issued->value;
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}

// Meeting
class Meeting extends Model implements Remindable
{
    public function getReminderDeadline(): ?Carbon
    {
        return $this->start_time;
    }

    public function getReminderOrganizationId(): int
    {
        return (int) $this->organization_id;
    }

    public function getReminderModuleKey(): string
    {
        return NotificationModuleEnum::Meeting->value;
    }

    public function getReminderEventKeys(): array
    {
        return [
            NotificationEventEnum::MeetingReminderBefore->value,
            NotificationEventEnum::MeetingReminderOn->value,
            NotificationEventEnum::MeetingReminderAfter->value,
        ];
    }

    public function getReminderEventKey(?string $moment): string
    {
        return "meeting_reminder_{$moment}";
    }

    public function resolveReminderRecipients(): Collection
    {
        $this->loadMissing(['participants.attendee', 'chairperson', 'operator']);

        $userIds = $this->participants->pluck('attendee.user_id')->filter();

        foreach ([$this->chairperson?->user_id, $this->operator?->user_id] as $id) {
            if ($id) $userIds->push($id);
        }

        return User::whereIn('id', $userIds->unique())->get();
    }

    public function resolveGuestReminderRecipients(): Collection
    {
        return $this->guests ?? collect();
    }

    public function isValidForReminder(): bool
    {
        return $this->status !== 'cancelled';
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}

// Schedule
class Schedule extends Model implements Remindable
{
    public function getReminderDeadline(): ?Carbon
    {
        $dateStr    = Carbon::parse($this->date_time)->format('Y-m-d');
        $sessionVal = $this->session instanceof SessionType ? $this->session->value : $this->session;

        $timeStr = match ($sessionVal) {
            'S'     => '07:30:00',
            'C'     => '13:30:00',
            'T'     => '19:30:00',
            default => '08:00:00',
        };

        return Carbon::parse("{$dateStr} {$timeStr}");
    }

    public function getReminderOrganizationId(): int
    {
        return (int) $this->organization_id;
    }

    public function getReminderModuleKey(): string
    {
        return NotificationModuleEnum::Scheduling->value;
    }

    public function getReminderEventKeys(): array
    {
        return [NotificationEventEnum::ScheduleReminder->value];
    }

    public function getReminderEventKey(?string $moment): string
    {
        return 'schedule_reminder';
    }

    public function resolveReminderRecipients(): Collection
    {
        $this->loadMissing(['recipients.user', 'host', 'driver']);

        $users = $this->recipients->map->user->filter();

        if ($this->host) $users->push($this->host);
        if ($this->driver) $users->push($this->driver);

        return $users->unique('id')->values();
    }

    public function resolveGuestReminderRecipients(): Collection
    {
        return collect();
    }

    public function isValidForReminder(): bool
    {
        $statusVal = $this->status instanceof ScheduleStatus
            ? $this->status->value
            : (int) $this->status;

        return $statusVal === ScheduleStatus::PUBLISHED->value;
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}
```

### 3.4 ReminderScheduler thống nhất

```php
// app/Modules/Notification/Services/ReminderScheduler.php
class ReminderScheduler
{
    public function scheduleFor(Model&Remindable $remindable): void
    {
        if (!$remindable->isValidForReminder()) {
            $this->cancelPending($remindable);
            return;
        }

        $deadline       = $remindable->getReminderDeadline();
        $organizationId = $remindable->getReminderOrganizationId();

        if (!$deadline || !$organizationId) return;

        // 1. Xóa PRESET cũ, giữ CUSTOM
        $remindable->reminders()
            ->where('status', 'pending')
            ->where('source', 'PRESET')
            ->delete();

        // 2. Tạo PRESET mới từ config
        NotificationEventConfig::with('schedules')
            ->where('module_key', $remindable->getReminderModuleKey())
            ->where('organization_id', $organizationId)
            ->whereIn('event_key', $remindable->getReminderEventKeys())
            ->get()
            ->each(function (NotificationEventConfig $config) use ($remindable, $deadline, $organizationId) {
                foreach ($config->schedules as $schedule) {
                    $remindAt = $this->computeRemindAt($deadline, $schedule->moment, (int) $schedule->offset_minutes);
                    if (!$remindAt) continue;

                    Reminder::create([
                        'remindable_type'          => get_class($remindable),
                        'remindable_id'            => $remindable->getKey(),
                        'organization_id'          => $organizationId,
                        'reminder_type'            => 'scheduled',
                        'source'                   => 'PRESET',
                        'notification_schedule_id' => $schedule->id,
                        'moment'                   => $schedule->moment,
                        'offset_minutes'           => (int) $schedule->offset_minutes,
                        'remind_at'                => $remindAt,
                        'status'                   => 'pending',
                    ]);
                }
            });

        // 3. Cập nhật remind_at cho CUSTOM
        $remindable->reminders()
            ->where('status', 'pending')
            ->where('source', 'CUSTOM')
            ->get()
            ->each(function (Reminder $reminder) use ($deadline) {
                $remindAt = $this->computeRemindAt($deadline, $reminder->moment, (int) $reminder->offset_minutes);
                if ($remindAt) $reminder->update(['remind_at' => $remindAt]);
            });
    }

    public function cancelPending(Model $remindable): void
    {
        $remindable->reminders()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    public function computeRemindAt(?Carbon $deadline, ?string $moment, int $offsetMinutes = 0): ?Carbon
    {
        if (!$deadline) return null;

        return match ($moment) {
            'before' => $offsetMinutes ? $deadline->copy()->subMinutes($offsetMinutes) : null,
            'on'     => $deadline->copy(),
            'after'  => $offsetMinutes ? $deadline->copy()->addMinutes($offsetMinutes) : null,
            null     => now(),
            default  => null,
        };
    }
}
```

### 3.5 ProcessRemindersCommand thống nhất

```php
class ProcessRemindersCommand extends Command
{
    protected $signature   = 'notifications:process-reminders';
    protected $description = 'Fire pending reminders across all modules';

    public function handle(
        NotificationDispatcher $dispatcher,
        ContentBuilderRegistry $registry,
        NotificationService    $notifier,
    ): int {
        // 1 query duy nhất thay vì 3 đoạn riêng
        Reminder::with(['remindable', 'notificationSchedule.eventConfig'])
            ->where('status', 'pending')
            ->where('remind_at', '<=', now())
            ->chunkById(100, function (Collection $reminders) use ($dispatcher, $registry, $notifier) {
                foreach ($reminders as $reminder) {
                    $this->fire($reminder, $dispatcher, $registry, $notifier);
                }
            });

        return self::SUCCESS;
    }

    private function fire(Reminder $reminder, ...): void
    {
        $remindable = $reminder->remindable;

        if (!$remindable instanceof Remindable || !$remindable->isValidForReminder()) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
            return;
        }

        $channels = $this->resolveChannels($reminder);
        if (empty($channels)) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
            return;
        }

        $organizationId = $remindable->getReminderOrganizationId();
        $eventKey       = $remindable->getReminderEventKey($reminder->moment);
        $builder        = $registry->for($eventKey);

        // Gửi cho User recipients
        foreach ($remindable->resolveReminderRecipients() as $user) {
            $dispatcher->dispatch(
                eventKey:       $eventKey,
                recipient:      $user,
                notifiable:     $remindable,
                channels:       $channels,
                builder:        $builder,
                organizationId: $organizationId,
            );
        }

        // Gửi cho Guest (chỉ Meeting)
        foreach ($remindable->resolveGuestReminderRecipients() as $guest) {
            $this->sendToGuest($guest, $remindable, $channels, $reminder->moment, $notifier);
        }

        $reminder->update(['status' => 'fired', 'fired_at' => now()]);
    }

    private function resolveChannels(Reminder $reminder): array
    {
        if ($reminder->source === 'CUSTOM' && !empty($reminder->channels)) {
            return array_map(fn($c) => strtolower(trim($c)), $reminder->channels);
        }

        $schedule = $reminder->notificationSchedule;
        $config   = $schedule?->eventConfig;

        if (!$config || !$config->enabled || empty($schedule->channels)) {
            return [];
        }

        return array_map(fn($c) => strtolower(trim($c)), $schedule->channels);
    }
}
```

---

## 4. Danh Sách Nơi Kích Hoạt Event (cập nhật 15/07/2026 — thực tế khác giả định ban đầu)

> Bảng gốc dưới đây giả định "không thay đổi" và mô tả Meeting/Scheduling vẫn dùng Observer. Thực tế đã
> chuyển 100% sang Service (cùng lúc hoặc ngay sau khi plan này viết), khớp với Fix 1 của audit doc. Giữ
> bảng cập nhật này làm nguồn đúng hiện tại; TaskAssignment không đổi.

### TaskAssignment

| Event | Nơi kích hoạt | Cơ chế | Điều kiện |
|-------|--------------|--------|-----------|
| `TaskAssigned` | `TaskAssignmentItemService::fireTaskAssignedForNewUsers()` | Service | User mới được thêm + doc issued |
| `TaskAssigned` | `TaskAssignmentTransferService::transfer()` | Service | Chuyển giao + doc issued |
| `TaskCompleted` | `TaskAssignmentItemService` | Service | `completion_percent >= 100` (không phải `TaskAssignmentReportService` như bản gốc ghi) |
| `TaskConfirmed` | `TaskAssignmentItemService::markDone()` | Service | status → done |
| `DocumentIssued` | `TaskAssignmentDocumentService::changeStatus()` | Service | draft → issued ⚠️ event fire nhưng **listener `SendDocumentIssuedNotifications` đang bị comment (dead code)** — hiện không gửi thông báo nào |

### Meeting

| Event | Nơi kích hoạt | Cơ chế | Điều kiện |
|-------|--------------|--------|-----------|
| `MeetingPublished` | `MeetingService.php` | Service | status → published |
| `MeetingUpdated` | `MeetingService.php` | **Service** (đã chuyển khỏi Observer) | published + NOTIFY_FIELDS thay đổi |
| `MeetingCancelled` | `MeetingService.php` | Service | published → cancelled |

`MeetingObserver` hiện chỉ còn lo scheduling/cancel reminder (data integrity), không fire event nào.

### Scheduling

| Event | Nơi kích hoạt | Cơ chế | Điều kiện |
|-------|--------------|--------|-----------|
| `SchedulePublished` | `ScheduleService.php` | **Service** (đã chuyển khỏi Observer) | draft → published |
| `ScheduleUpdated` | `ScheduleService.php` | **Service** | published + NOTIFY_FIELDS thay đổi |
| `ScheduleCancelled` | `ScheduleService.php` | **Service** | published → unpublished / xóa khi đang published |

`ScheduleObserver` hiện chỉ còn lo scheduling/cancel reminder, không fire event nào.

---

## 5. Kế Hoạch Thực Hiện (Theo Thứ Tự)

### Phase 1 — Code Abstraction (không breaking change, không đụng database)

```
[ ] 1.1  Tạo interface Remindable
         app/Modules/Notification/Contracts/Remindable.php

[ ] 1.2  Tạo Reminder model (app/Modules/Notification/Models/Reminder.php)
         — morphTo('remindable')
         — belongsTo notificationSchedule

[ ] 1.3  Implement Remindable trên 3 model:
         — TaskAssignmentItem
         — Meeting
         — Schedule

[ ] 1.4  Viết ReminderScheduler mới (unified)
         app/Modules/Notification/Services/ReminderScheduler.php
         — scheduleFor(Model&Remindable)
         — cancelPending(Model)
         — computeRemindAt() — 1 method duy nhất

[ ] 1.5  Cập nhật Observers dùng ReminderScheduler mới:
         — TaskAssignmentItemObserver (thay TaskAssignmentReminderScheduler)
         — MeetingObserver (thay MeetingReminderScheduler)
         — ScheduleObserver (thay ScheduleReminderScheduler)

[ ] 1.6  Cập nhật ProcessRemindersCommand
         — 1 query Reminder::where(...)
         — 1 method fire(Reminder)
         — 1 method resolveChannels(Reminder)
         Verify: chạy artisan notifications:process-reminders, kiểm tra log không lỗi
```

### Phase 2 — Database Migration

```
[ ] 2.1  Migration: create_reminders_table
         Verify: sail artisan migrate, bảng tồn tại đúng cấu trúc

[ ] 2.2  Migration: migrate_reminders_data
         — Copy từ task_assignment_reminders
         — Copy từ meeting_reminders (normalize: status 'sent' → 'fired')
         — Copy từ schedule_reminders (normalize: moment 'immediate' → null)
         Verify: row count khớp, sample check 5-10 records mỗi loại

[ ] 2.3  Chuyển Phase 1 code sang dùng bảng reminders mới
         (Reminder model trỏ vào bảng mới thay vì 3 bảng cũ)
         Verify: tạo TaskAssignmentItem mới → reminder được tạo đúng bảng mới

[ ] 2.4  Migration: drop_old_reminder_tables
         DROP TABLE task_assignment_reminders
         DROP TABLE meeting_reminders
         DROP TABLE schedule_reminders
         Chạy sau khi đã verify Phase 2.3 ổn định
```

### Phase 3 — Cleanup

```
[ ] 3.1  Xóa 3 Scheduler class cũ:
         — TaskAssignmentReminderScheduler
         — MeetingReminderScheduler
         — ScheduleReminderScheduler

[ ] 3.2  Xóa 3 Model class cũ:
         — TaskAssignmentReminder
         — MeetingReminder
         — ScheduleReminder

[ ] 3.3  Cập nhật NotificationServiceProvider:
         — Binding ReminderScheduler mới
         — Xóa binding 3 scheduler cũ

[ ] 3.4  Kiểm tra Frontend endpoints (không đọc từ reminder tables trực tiếp):
         GET /api/task-assignment/notification/logs  → đọc notification_deliveries ✓ an toàn
         GET /api/meeting/notification/logs          → đọc notification_deliveries ✓ an toàn
         GET /api/scheduling/notification/logs       → đọc notification_deliveries ✓ an toàn
```

---

## 6. Các Điểm Cần Lưu Ý Khi Triển Khai

### 6.1 TaskAssigned vẫn fire trong Service — lý do hợp lệ

Observer không có context "user nào vừa được giao mới". Service cần fire `TaskAssigned($item, $specificUser)` sau khi xác định được user mới. Giữ nguyên cơ chế này.

### 6.2 Guest reminder (Meeting) — giữ sendToGuest() trong Command

Logic gửi raw email/SMS/Zalo cho guest (không có User account) là Meeting-specific. Giữ trong `ProcessRemindersCommand::sendToGuest()`, gọi qua `resolveGuestReminderRecipients()`.

### 6.3 Data migration — normalize values

| Field | Old values | Normalized |
|-------|-----------|------------|
| `status` (meeting) | `sent` | → `fired` |
| `reminder_type` (meeting) | `manual` | → `manual` (giữ nguyên) |
| `moment` (schedule) | `immediate` | → `null` (= instant) |
| `status` order | khác nhau | → `pending/fired/cancelled` |

### 6.4 isValidForReminder() cần eager load

Khi Observer gọi `ReminderScheduler::scheduleFor($item)`, method `isValidForReminder()` của `TaskAssignmentItem` cần `$this->document`. Đảm bảo load relationship trước khi gọi:

```php
// Trong Observer:
public function saved(TaskAssignmentItem $item): void
{
    $item->loadMissing('document');
    $this->scheduler->scheduleFor($item);
}
```

### 6.5 ScheduleObserver cần verify đăng ký

Theo audit hiện tại, `ScheduleObserver` không xuất hiện trong `NotificationServiceProvider`. Cần kiểm tra observer này có được đăng ký đúng chỗ không trước khi tiến hành Phase 1.5.

---

## 7. Tóm Tắt Thay Đổi File (cập nhật 15/07/2026 — namespace thực tế)

> Bản gốc đề xuất namespace `app/Modules/Notification/...`. Implementation thực tế KHÔNG dùng
> `app/Modules/Notification/` mà giữ trong `app/Services/Notification/` (đã có sẵn) + model đặt ở
> `app/Models/` (không nằm trong `app/Modules/`). Danh sách dưới đã sửa theo path thật.

### Files mới tạo (path thật)
```
app/Services/Notification/Contracts/Remindable.php
app/Models/Reminder.php
app/Services/Notification/Services/ReminderScheduler.php   ← thay 3 cái cũ
database/migrations/2026_06_22_214321_create_reminders_table.php
database/migrations/2026_06_22_214321_migrate_reminders_data.php
database/migrations/2026_06_22_214322_drop_old_reminder_tables.php
```

### Files sửa đổi
```
app/Modules/TaskAssignment/Models/TaskAssignmentItem.php      ← implement Remindable
app/Modules/Meeting/Models/Meeting.php                        ← implement Remindable
app/Modules/Scheduling/Models/Schedule.php                    ← implement Remindable
app/Modules/TaskAssignment/Observers/TaskAssignmentItemObserver.php  ← dùng ReminderScheduler mới
app/Modules/Meeting/Observers/MeetingObserver.php             ← dùng ReminderScheduler mới
app/Modules/Scheduling/Observers/ScheduleObserver.php         ← dùng ReminderScheduler mới
app/Services/Notification/Console/ProcessRemindersCommand.php ← unified fire()
app/Providers/NotificationServiceProvider.php                 ← update scheduler binding
```

### Files xóa (Phase 3)
```
app/Modules/TaskAssignment/Services/TaskAssignmentReminderScheduler.php
app/Modules/Meeting/Services/MeetingReminderScheduler.php
app/Modules/Scheduling/Services/ScheduleReminderScheduler.php
app/Modules/TaskAssignment/Models/TaskAssignmentReminder.php
app/Modules/Meeting/Models/MeetingReminder.php
app/Modules/Scheduling/Models/ScheduleReminder.php
```

### Files KHÔNG thay đổi (giữ nguyên cơ chế event fire từ Service)
```
app/Modules/Meeting/Services/MeetingService.php               ← fire MeetingPublished/Updated/Cancelled
app/Modules/Scheduling/Services/ScheduleService.php            ← fire SchedulePublished/Updated/Cancelled
app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php    ← fire TaskConfirmed/TaskCompleted
app/Modules/TaskAssignment/Services/TaskAssignmentDocumentService.php ← fire DocumentIssued
```

---

## 8. Lợi Ích Sau Refactor

| Tiêu chí | Trước | Sau |
|----------|-------|-----|
| Số bảng reminder | 3 | 1 |
| Số Scheduler class | 3 (~giống nhau) | 1 |
| `computeRemindAt()` | copy/paste 3 chỗ | 1 method |
| Channel resolution | 3 method | 1 method |
| `ProcessRemindersCommand` | 3 fire methods + 3 queries | 1 fire() + 1 query |
| Thêm module mới | Tạo bảng + scheduler + command code | Implement interface Remindable |
| Tuân thủ CLAUDE.md EDA | Event trigger không nhất quán (mix Observer+Service) | ✅ Nhất quán — Service fire business event, Observer chỉ data integrity |
