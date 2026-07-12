# Kế Hoạch Tái Cấu Trúc Hệ Thống Thông Báo

**Ngày lập:** 2026-06-21  
**Phạm vi:** Backend (`qlcv`) — toàn bộ notification flow, reminder tables, event triggers  
**Mục tiêu:** Loại bỏ code lặp, thống nhất cơ chế kích hoạt sự kiện, một bảng reminder thay vì 3

---

## 1. Hiện Trạng & Vấn Đề

### 1.1 Ba bảng reminder không đồng nhất

| Field | `task_assignment_reminders` | `meeting_reminders` | `schedule_reminders` |
|-------|-----------------------------|---------------------|----------------------|
| `moment` type | `varchar(10) nullable` | `string nullable` | `enum(before,on,after) nullable` |
| `status` values | `pending/fired/cancelled/active` | `pending/fired/cancelled/sent` | `active/pending/fired/cancelled` |
| `reminder_type` values | `instant/scheduled` | `manual/scheduled` | `instant/scheduled` |
| `organization_id` | ✗ (scope qua document) | ✓ | ✓ |
| `message` / `created_by` | ✗ | ✓ | ✓ |
| `scheduled_at` / `sent_at` | ✗ | ✓ | ✓ (là `scheduled_at`) |
| `$timestamps` | auto | auto | **false** (thủ công) |

**Code bị lặp y hệt nhau ở 3 nơi:**
- `computeRemindAt()` — 3 class scheduler copy/paste
- Channel resolution logic — 3 method trong `ProcessRemindersCommand`
- DELETE PRESET → CREATE PRESET → UPDATE CUSTOM — 3 scheduler classes

### 1.2 Cơ chế kích hoạt event không nhất quán

| Module | Cơ chế | Rủi ro |
|--------|--------|--------|
| **Scheduling** | Observer thuần túy | ✅ Thấp — tự động bắt mọi thay đổi |
| **Meeting** | Mix: Observer (Updated) + Service (Published, Cancelled) | ⚠️ Trung — nếu thay đổi status từ Console/Seeder, Published/Cancelled bị bỏ sót |
| **TaskAssignment** | Service trực tiếp (2 chỗ cho TaskAssigned) | ❌ Cao — phải nhớ fire tại từng Service, DocumentIssued đã bị comment out vì quên |

### 1.3 Ba ReminderScheduler gần như giống nhau

```
ReminderScheduler           → scheduleFor(TaskAssignmentItem)
MeetingReminderScheduler    → scheduleFor(Meeting)
ScheduleReminderScheduler   → scheduleFor(Schedule)
```

`ProcessRemindersCommand` phải gọi 3 method riêng:
```php
// Phải viết lại khi thêm module mới
TaskAssignmentReminder::...->chunkById(100, fn($r) => $this->fireReminder($r));
MeetingReminder::...->chunkById(100, fn($r) => $this->fireMeetingReminder($r));
ScheduleReminder::...->chunkById(100, fn($r) => $this->fireScheduleReminder($r));
```

---

## 2. Mục Tiêu Sau Tái Cấu Trúc

1. **1 bảng `reminders`** (polymorphic) thay cho 3 bảng riêng
2. **1 `ReminderScheduler`** thay cho 3 class
3. **1 `ProcessRemindersCommand::fire()`** thay cho 3 method
4. **Tất cả module dùng Observer** để kích hoạt event (không fire trong Service)
5. **Convention thống nhất:** `moment`, `status`, `reminder_type`, `source` dùng cùng giá trị

---

## 3. Thiết Kế Mới

### 3.1 Bảng `reminders` (thay thế 3 bảng cũ)

```sql
CREATE TABLE reminders (
    id                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Polymorphic: TaskAssignmentItem | TaskAssignmentDocument | Meeting | Schedule
    remindable_type           VARCHAR(255) NOT NULL,
    remindable_id             BIGINT UNSIGNED NOT NULL,

    organization_id           BIGINT UNSIGNED NULL,

    -- Loại reminder
    reminder_type             VARCHAR(20) NOT NULL DEFAULT 'scheduled',
    -- 'instant'   = bắn ngay khi model được publish/issue
    -- 'scheduled' = bắn theo remind_at
    -- 'manual'    = admin tạo thủ công (Meeting)

    source                    VARCHAR(10) NOT NULL DEFAULT 'PRESET',
    -- 'PRESET' = lấy channels từ notification_schedule
    -- 'CUSTOM' = dùng channels trong cột channels

    notification_schedule_id  BIGINT UNSIGNED NULL,

    -- Timing
    moment                    VARCHAR(10) NULL,
    -- 'before' | 'on' | 'after' | NULL (= instant)
    offset_minutes            INT NULL,
    remind_at                 DATETIME NULL,

    -- Channels
    channels                  JSON NULL,
    -- ['sms', 'mail', 'fcm', 'zalo', 'zalo_zns', 'telegram']

    -- Trạng thái (thống nhất)
    status                    VARCHAR(20) NOT NULL DEFAULT 'pending',
    -- 'pending'   = chờ fire
    -- 'active'    = instant reminder đang active
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

**Migration steps:**
1. `create_reminders_table` — tạo bảng mới
2. `migrate_reminders_data` — copy data từ 3 bảng cũ
3. `drop_old_reminder_tables` — xóa 3 bảng cũ (sau khi verify)

### 3.2 Interface `Remindable`

```php
// app/Services/Notification/Contracts/Remindable.php
namespace App\Services\Notification\Contracts;

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

    // event_keys dùng để query NotificationEventConfig (1 hoặc nhiều)
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
        return NotificationModuleEnum::TaskAssignment->value; // 'task_assignment'
    }

    public function getReminderEventKeys(): array
    {
        return [
            NotificationEventEnum::ReminderBefore->value, // 'reminder_before'
            NotificationEventEnum::ReminderOn->value,     // 'reminder_on'
            NotificationEventEnum::ReminderAfter->value,  // 'reminder_after'
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
        return NotificationModuleEnum::Meeting->value; // 'meeting'
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

        $userIds = $this->participants
            ->pluck('attendee.user_id')
            ->filter();

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
        // date_time + session (S=07:30, C=13:30, T=19:30)
        $dateStr = Carbon::parse($this->date_time)->format('Y-m-d');
        $sessionVal = $this->session instanceof SessionType
            ? $this->session->value
            : $this->session;

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
        return NotificationModuleEnum::Scheduling->value; // 'scheduling'
    }

    public function getReminderEventKeys(): array
    {
        return [NotificationEventEnum::ScheduleReminder->value]; // ['schedule_reminder']
    }

    public function getReminderEventKey(?string $moment): string
    {
        return 'schedule_reminder'; // không phân biệt before/on/after
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
// app/Services/Notification/Services/ReminderScheduler.php
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
                    $remindAt = $this->computeRemindAt(
                        $deadline,
                        $schedule->moment,
                        (int) $schedule->offset_minutes,
                    );
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
                $remindAt = $this->computeRemindAt(
                    $deadline,
                    $reminder->moment,
                    (int) $reminder->offset_minutes,
                );
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
            null     => now(), // instant
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
        NotificationService $notifier,
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

        // Validate
        if (!$remindable instanceof Remindable || !$remindable->isValidForReminder()) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
            return;
        }

        // Resolve channels
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
        // Logic dùng chung — không cần 3 method riêng
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

### 3.6 Thống nhất cơ chế kích hoạt Event — tất cả dùng Observer

> ⚠️ CẬP NHẬT (đã triển khai khác kế hoạch): Phần 3.6 và Phase 3 bên dưới mô tả phương án
> "đưa dispatch event vào Observer". Phương án cuối cùng **KHÔNG làm vậy** — theo quy ước
> Event-Driven trong `CLAUDE.md`, **event vẫn fire trong Service** (`MeetingService`,
> `ScheduleService`), còn Observer chỉ lo lập lịch reminder (`ReminderScheduler->scheduleFor()`).
> Đọc phần dưới như bối cảnh thiết kế ban đầu, không phải hiện trạng code.

#### TaskAssignment — chuyển từ Service sang Observer

```php
// TaskAssignmentItemObserver — mở rộng saved()
class TaskAssignmentItemObserver
{
    public function saved(TaskAssignmentItem $item): void
    {
        $this->scheduler->scheduleFor($item); // reminder scheduling

        // TaskAssigned: khi document issued + users thay đổi
        // (Giữ nguyên logic hiện tại — Observer fired sau Service transaction)

        // TaskConfirmed: khi processing_status → done (thay vì fire trong markDone())
        if ($item->wasChanged('processing_status')
            && $item->processing_status === TaskProgressStatusEnum::Done->value) {
            event(new TaskConfirmed($item->fresh()));
        }
    }

    // TaskCompleted: khi completion_percent đạt 100
    // Observer tốt hơn: tự động bắt cả update từ Console/Import
    public function updated(TaskAssignmentItem $item): void
    {
        if ($item->wasChanged('completion_percent') && $item->completion_percent >= 100) {
            event(new TaskCompleted($item));
        }
    }
}
```

**Lưu ý:** `TaskAssigned` vẫn giữ logic fire trong Service (`fireTaskAssignedForNewUsers`, `transfer`) vì cần biết chính xác **user nào** được giao mới — Observer không có context này.

#### Meeting — gộp Published/Cancelled vào Observer

```php
// MeetingObserver — thêm saved()
class MeetingObserver
{
    // NOTIFY_FIELDS không đổi
    private const NOTIFY_FIELDS = ['title', 'start_time', 'end_time', 'meeting_location_id', 'content'];

    public function saved(Meeting $meeting): void
    {
        $this->scheduler->scheduleFor($meeting); // reminder scheduling

        $isPublished    = $meeting->status === 'published';
        $wasPublished   = $meeting->getOriginal('status') === 'published';

        // Published (thay vì fire trong MeetingService::changeStatus)
        if ($isPublished && !$wasPublished) {
            Event::dispatch(new MeetingPublished($meeting));
        }

        // Cancelled
        if (!$isPublished && $wasPublished && $meeting->status === 'cancelled') {
            Event::dispatch(new MeetingCancelled($meeting));
        }
    }

    // MeetingUpdated giữ nguyên ở updated() — đã đúng
    public function updated(Meeting $meeting): void
    {
        $wasPublished        = $meeting->getOriginal('status') === 'published';
        $changedNotifyFields = array_filter(
            self::NOTIFY_FIELDS,
            fn($f) => $meeting->wasChanged($f),
        );

        if ($wasPublished && !empty($changedNotifyFields)) {
            Event::dispatch(new MeetingUpdated($meeting, array_values($changedNotifyFields)));
        }
    }
}
```

**Xóa khỏi MeetingService::changeStatus():**
```php
// Xóa 2 dòng này — Observer đã handle
// Event::dispatch(new MeetingPublished($meeting));   ← XÓA
// Event::dispatch(new MeetingCancelled($meeting));  ← XÓA
```

#### Scheduling — giữ nguyên (đã đúng)

`ScheduleObserver` đã đúng pattern — không cần thay đổi.

---

## 4. Danh Sách Tất Cả Nơi Kích Hoạt Event (Sau Refactor)

### TaskAssignment

| Event | Nơi kích hoạt | Cơ chế | Điều kiện |
|-------|--------------|--------|-----------|
| `TaskAssigned` | `TaskAssignmentItemService::fireTaskAssignedForNewUsers()` | Service | User mới được thêm + doc issued |
| `TaskAssigned` | `TaskAssignmentTransferService::transfer()` | Service | Chuyển giao + doc issued |
| `TaskCompleted` | `TaskAssignmentItemObserver::updated()` | **Observer** | `completion_percent >= 100` |
| `TaskConfirmed` | `TaskAssignmentItemObserver::saved()` | **Observer** | `processing_status → done` |
| `DocumentIssued` | `TaskAssignmentDocumentService::changeStatus()` | Service | draft → issued (**cần bỏ comment**) |

### Meeting

| Event | Nơi kích hoạt | Cơ chế | Điều kiện |
|-------|--------------|--------|-----------|
| `MeetingPublished` | `MeetingObserver::saved()` | **Observer** | status → published |
| `MeetingUpdated` | `MeetingObserver::updated()` | Observer | published + NOTIFY_FIELDS thay đổi |
| `MeetingCancelled` | `MeetingObserver::saved()` | **Observer** | published → cancelled |
| `MeetingParticipantResponded` | `MeetingParticipantService::respond()` | Service | Confirm/decline tham dự (broadcast) |
| 14 broadcast events | Các Service tương ứng | Service | Real-time trong phòng họp |

### Scheduling

| Event | Nơi kích hoạt | Cơ chế | Điều kiện |
|-------|--------------|--------|-----------|
| `SchedulePublished` | `ScheduleObserver::saved()` | Observer | draft → published |
| `ScheduleUpdated` | `ScheduleObserver::saved()` | Observer | published + NOTIFY_FIELDS thay đổi |
| `ScheduleCancelled` | `ScheduleObserver::saved()` | Observer | published → unpublished |
| `ScheduleCancelled` | `ScheduleObserver::deleted()` | Observer | xóa khi đang published |

---

## 5. Kế Hoạch Thực Hiện (Theo Thứ Tự)

### Phase 1 — Database (không breaking change)

```
[ ] 1.1  Migration: create_reminders_table
         → Tạo bảng reminders mới (polymorphic)

[ ] 1.2  Migration: migrate_reminders_data
         → Copy data từ task_assignment_reminders
            + meeting_reminders
            + schedule_reminders
            → reminders (map fields, normalize status/moment)

[ ] 1.3  Verify data migration (row counts, sample check)
```

### Phase 2 — Backend Core

```
[ ] 2.1  Tạo Reminder model (app/Models/Reminder.php)
         → morphTo('remindable')
         → BelongsTo notificationSchedule

[ ] 2.2  Tạo interface Remindable
         (app/Services/Notification/Contracts/Remindable.php)

[ ] 2.3  Implement Remindable trên 3 model:
         → TaskAssignmentItem
         → Meeting
         → Schedule

[ ] 2.4  Viết ReminderScheduler mới (unified)
         → scheduleFor(Model&Remindable)
         → cancelPending(Model)
         → computeRemindAt() — 1 method duy nhất

[ ] 2.5  Cập nhật ProcessRemindersCommand
         → 1 query Reminder::where(...)
         → 1 method fire(Reminder)
         → 1 method resolveChannels(Reminder)

[ ] 2.6  Cập nhật Observers để dùng ReminderScheduler mới:
         → TaskAssignmentItemObserver
         → MeetingObserver
         → ScheduleObserver
```

### Phase 3 — Thống nhất Event Triggers

```
[ ] 3.1  MeetingObserver::saved()
         → Thêm MeetingPublished + MeetingCancelled dispatch

[ ] 3.2  MeetingService::changeStatus()
         → Xóa Event::dispatch(MeetingPublished) và MeetingCancelled
         → Chỉ giữ business logic (validate, update status)

[ ] 3.3  TaskAssignmentItemObserver::saved() + updated()
         → TaskConfirmed → Observer thay vì ItemService::markDone()
         → TaskCompleted → Observer thay vì ReportService::store()

[ ] 3.4  TaskAssignmentDocumentService::changeStatus()
         → Bỏ comment DocumentIssued event
         → Đảm bảo listener SendDocumentIssuedNotifications hoạt động

[ ] 3.5  Test toàn bộ event flow:
         → Publish meeting → MeetingPublished fired
         → Cancel meeting → MeetingCancelled fired
         → Update meeting title → MeetingUpdated fired
         → Complete task → TaskCompleted fired
         → Confirm task → TaskConfirmed fired
         → Issue document → DocumentIssued fired
```

### Phase 4 — Cleanup

```
[ ] 4.1  Xóa 3 Scheduler class cũ:
         → ReminderScheduler (task_assignment version)
         → MeetingReminderScheduler
         → ScheduleReminderScheduler

[ ] 4.2  Xóa code tương ứng trong ProcessRemindersCommand
         → fireReminder(), fireMeetingReminder(), fireScheduleReminder()
         → 3 query chunk riêng biệt

[ ] 4.3  Migration: drop_old_reminder_tables
         → DROP TABLE task_assignment_reminders
         → DROP TABLE meeting_reminders
         → DROP TABLE schedule_reminders

[ ] 4.4  Cập nhật Frontend services/stores nếu có endpoint liên quan
         (kiểm tra qlcv-fe có dùng endpoint reminder nào không)
```

---

## 6. Các Điểm Cần Lưu Ý Khi Triển Khai

### 6.1 TaskAssigned vẫn fire trong Service — lý do hợp lý

Observer không có context "user nào vừa được giao mới". Service cần fire `TaskAssigned($item, $specificUser)` sau khi xác định được user mới. Giữ nguyên cơ chế này.

### 6.2 Guest reminder (Meeting) — giữ sendToGuest() trong Command

Logic gửi raw email/SMS/Zalo cho guest (không có User account) là Meeting-specific. Giữ trong `ProcessRemindersCommand::sendToGuest()`, gọi qua `resolveGuestReminderRecipients()`.

### 6.3 Data migration — normalize values

| Field | Old values | Normalized |
|-------|-----------|------------|
| `status` (meeting) | `sent` | → `fired` |
| `reminder_type` (meeting) | `manual` | → `manual` (giữ nguyên) |
| `moment` (schedule) | `immediate` | → `null` (= instant) |
| `status` order | khác nhau | → `pending/active/fired/cancelled` |

### 6.4 Backward compatibility Frontend

Kiểm tra các endpoint sau trước khi xóa bảng cũ:
- `GET /api/task-assignment/notification/logs`
- `GET /api/meeting/notification/logs`
- `GET /api/scheduling/notification/logs`

Các endpoint log đọc từ `notification_deliveries`, không đọc trực tiếp từ reminder tables → an toàn.

### 6.5 `scheduled_at` và `sent_at` của meeting_reminders

Hai field này chỉ dùng cho manual reminder flow (admin gửi thủ công). Trong bảng mới, loại bỏ hoàn toàn vì:
- `remind_at` = thời điểm fire (thay thế cả `scheduled_at`)
- `sent_at` = không cần, đã có `notification_deliveries.sent_at` để audit

---

## 7. Tóm Tắt Thay Đổi File

### Files mới tạo
```
app/Services/Notification/Contracts/Remindable.php
app/Models/Reminder.php
app/Services/Notification/Services/ReminderScheduler.php  ← unified (thay 3 cái cũ)
database/migrations/YYYY_MM_DD_create_reminders_table.php
database/migrations/YYYY_MM_DD_migrate_reminders_data.php
database/migrations/YYYY_MM_DD_drop_old_reminder_tables.php
```

### Files sửa đổi
```
app/Modules/TaskAssignment/Models/TaskAssignmentItem.php     ← implement Remindable
app/Modules/Meeting/Models/Meeting.php                       ← implement Remindable
app/Modules/Scheduling/Models/Schedule.php                   ← implement Remindable
app/Modules/Meeting/Observers/MeetingObserver.php            ← thêm saved() Published/Cancelled
app/Modules/Meeting/Services/MeetingService.php              ← xóa Event::dispatch()
app/Modules/TaskAssignment/Observers/TaskAssignmentItemObserver.php ← thêm TaskCompleted/Confirmed
app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php   ← xóa event(TaskConfirmed)
app/Modules/TaskAssignment/Services/TaskAssignmentReportService.php ← xóa event(TaskCompleted)
app/Modules/TaskAssignment/Services/TaskAssignmentDocumentService.php ← bỏ comment DocumentIssued
app/Services/Notification/Console/ProcessRemindersCommand.php ← unified fire()
app/Providers/NotificationServiceProvider.php                ← update scheduler binding
```

### Files xóa
```
app/Services/Notification/Services/MeetingReminderScheduler.php
app/Services/Notification/Services/ScheduleReminderScheduler.php
app/Modules/TaskAssignment/Models/TaskAssignmentReminder.php
app/Modules/Meeting/Models/MeetingReminder.php
app/Modules/Scheduling/Models/ScheduleReminder.php
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
| Event trigger nhất quán | Không (Service + Observer mix) | Có (Observer là primary) |
| `DocumentIssued` | Commented out, không hoạt động | Hoạt động |
