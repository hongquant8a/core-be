# Notification Events + Reminders — Design Spec

**Ngày:** 2026-04-16
**Phạm vi:** Mở rộng hệ thống notification hiện tại — thêm event-triggered notifications cho TaskAssignment + scheduled reminders theo deadline.

**Tiền đề:** Infrastructure notification đã có (spec cũ: `2026-04-15-notification-service-sms-design.md`). Gồm `NotificationService` singleton + 4 channel (`sms`, `mail`, `zalo`, `fcm`) đã implement. Spec này xây layer event/reminder ở trên.

## 1. Events (6 types)

### Event-triggered (3)

| Event key | Trigger | Recipients | Qty |
|-----------|---------|-----------|-----|
| `document_issued` | `TaskAssignmentDocument.status` chuyển sang `issued` (tạo mới với status issued HOẶC change từ draft→issued) | Assignees của từng item trong document | 1 notification / (user × item × channel) |
| `task_completed` | `TaskAssignmentItem.processing_status` chuyển sang `reported` (assignee submit chờ duyệt) | Manager (`item.assigned_by`) | 1 notification / (item × channel) |
| `task_confirmed` | `TaskAssignmentItem.processing_status` chuyển sang `done` (manager confirm) | Assignees của item | 1 notification / (user × item × channel) |

### Scheduled reminders (3)

| Event key | Moment | Trigger | Recipients |
|-----------|--------|---------|-----------|
| `reminder_before` | before | `now >= item.end_at - offset` | Assignees chưa hoàn thành |
| `reminder_on` | on | `now >= item.end_at` | Assignees chưa hoàn thành |
| `reminder_after` | after | `now >= item.end_at + offset` (only 1 lần) | Assignees chưa hoàn thành |

## 2. Design decisions (từ brainstorm)

1. **1 user có N task trong 1 document** → gửi N notification riêng (không gộp). Mỗi notification ref đến 1 task cụ thể.
2. **Manager / Người quản lý** = `item.assigned_by` (người tạo/giao task).
3. **Recipient thiếu phone/email/fcm_token** → notification service tự log warning (không silent skip), không throw.
4. **Idempotency**: document status nhảy qua lại `issued → draft → issued` → mỗi lần chuyển sang `issued` đều gửi lại (không dedupe).
5. **Cancel reminders khi task done trước hạn** → Khi item status → `done`, cancel tất cả reminder `pending` của item đó.
6. **Nhắc trễ hạn chỉ 1 lần** (không lặp).
7. **Content template hard-code** qua Blade + PHP class — không để admin edit qua UI.
8. **Scope**: full 6 events × 4 channels = 24 combo content builders.

## 3. Vị trí mã nguồn

```
app/Services/Notification/                                 (đã có)
├── Events/                                                (MỚI)
│   ├── DocumentIssued.php                                 -- Laravel event
│   ├── TaskCompleted.php
│   └── TaskConfirmed.php
├── Listeners/                                             (MỚI)
│   ├── SendDocumentIssuedNotifications.php                -- queued
│   ├── SendTaskCompletedNotifications.php                 -- queued
│   └── SendTaskConfirmedNotifications.php                 -- queued
├── Jobs/                                                  (MỚI)
│   ├── ProcessDueRemindersJob.php                         -- dispatched by scheduler
│   └── SendScheduledReminderJob.php                       -- 1 per reminder
├── ContentBuilders/                                       (MỚI)
│   ├── DocumentIssuedContentBuilder.php                   -- toSms/toMail/toZalo/toFcm
│   ├── TaskCompletedContentBuilder.php
│   ├── TaskConfirmedContentBuilder.php
│   └── ReminderContentBuilder.php                         -- handles all 3 reminder types, param by moment
├── Services/                                              (MỚI)
│   ├── NotificationEventDispatcher.php                    -- high-level: load config → build → send
│   └── ReminderScheduler.php                              -- create reminder records when item saved
└── Enums/                                                 (MỚI)
    ├── NotificationEventEnum.php
    └── NotificationMomentEnum.php

app/Modules/TaskAssignment/Models/
└── TaskAssignmentReminder.php                             -- EXTEND (thêm 'moment', 'cancelled' status)

app/Modules/Core/                                          -- API + models cho config
├── Models/
│   ├── NotificationEventConfig.php                        (MỚI)
│   └── NotificationSchedule.php                           (MỚI)
├── NotificationConfigController.php                       (MỚI)
├── Requests/
│   ├── UpdateNotificationEventConfigRequest.php
│   ├── StoreNotificationScheduleRequest.php
│   └── UpdateNotificationScheduleRequest.php
└── Routes/notification.php                                -- EXTEND

resources/views/notifications/                             (MỚI)
├── document_issued/email.blade.php
├── task_completed/email.blade.php
├── task_confirmed/email.blade.php
├── reminder_before/email.blade.php
├── reminder_on/email.blade.php
└── reminder_after/email.blade.php
```

## 4. Database schema

### Migration 0: `create_notifications_table` (POLYMORPHIC, for in-app list)

Mỗi lần 1 event/reminder fire tới 1 user → 1 record. User xem list notification qua API.

```sql
notifications
├─ id
├─ user_id                              -- recipient FK
├─ event_key VARCHAR(50)                -- 'document_issued', 'task_completed', 'reminder_before', ...
├─ notifiable_type VARCHAR(255)         -- polymorphic: App\Modules\TaskAssignment\Models\TaskAssignmentItem
├─ notifiable_id BIGINT UNSIGNED
├─ title VARCHAR(255)
├─ body TEXT                            -- short description, rendered for in-app display
├─ context JSON                         -- extra data for frontend (vd url, action)
├─ read_at DATETIME NULL                -- null = unread
├─ timestamps
├─ INDEX (user_id, read_at)
├─ INDEX (notifiable_type, notifiable_id)
```

### Migration 0b: `create_notification_deliveries_table` (log của mỗi lượt push)

Mỗi channel attempt → 1 record. FK tới `notifications` (parent). Cho phép audit "notification X đã đẩy qua bao nhiêu channel, thành công/fail thế nào".

```sql
notification_deliveries
├─ id
├─ notification_id                      -- FK notifications, cascade delete
├─ channel VARCHAR(20)                  -- sms/mail/zalo/fcm
├─ status ENUM('pending','sent','failed','skipped') DEFAULT 'pending'
├─ message_id VARCHAR(255) NULL         -- ID từ provider
├─ error_message TEXT NULL
├─ sent_at DATETIME NULL
├─ timestamps
├─ INDEX (notification_id)
├─ INDEX (status, created_at)
```

`status = skipped` khi recipient thiếu field (vd muốn gửi sms nhưng user không có phone).

### Migration 1: `create_notification_event_configs_table`

```sql
notification_event_configs
├─ id
├─ event_key VARCHAR(50) UNIQUE       -- 'document_issued', 'task_completed', ...
├─ enabled BOOLEAN DEFAULT 0
├─ channels JSON                       -- ['sms','mail','zalo','fcm']
├─ timestamps
```

Seed: 6 rows, 1 per event, enabled=0 default, channels=[].

### Migration 2: `create_notification_schedules_table`

```sql
notification_schedules
├─ id
├─ moment ENUM('before','on','after')
├─ offset_minutes INT UNSIGNED NULL    -- NULL khi moment=on
├─ channels JSON
├─ enabled BOOLEAN DEFAULT 1
├─ label VARCHAR(255)                   -- admin đặt: "Nhắc trước 1 ngày"
├─ sort_order INT DEFAULT 0
├─ timestamps
├─ INDEX (moment, enabled)
```

Seed mặc định vài rule phổ biến:
- before 1 ngày (1440 min), channels=[mail]
- before 2 giờ (120 min), channels=[sms, fcm]
- on, channels=[sms, mail, fcm]
- after 1 ngày (1440 min), channels=[mail]

### Migration 3: RESTRUCTURE `task_assignment_reminders`

Bảng hiện tại pre-expanded theo (item × user × channel). Với thiết kế mới (notifications/deliveries riêng), reminder chỉ cần giữ **lịch hẹn ở item-level**. Fire time sẽ resolve users + channels từ config.

**Drop & recreate** (bảng chưa có dữ liệu production — kiểm tra trước khi drop):

```sql
DROP TABLE task_assignment_reminders;

CREATE TABLE task_assignment_reminders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_assignment_item_id BIGINT UNSIGNED NOT NULL,
  notification_schedule_id BIGINT UNSIGNED NOT NULL,
  moment ENUM('before','on','after') NOT NULL,
  remind_at DATETIME NOT NULL,
  status ENUM('pending','fired','cancelled') DEFAULT 'pending',
  fired_at DATETIME NULL,
  created_at DATETIME,
  updated_at DATETIME,
  FOREIGN KEY (task_assignment_item_id) REFERENCES task_assignment_items(id) ON DELETE CASCADE,
  FOREIGN KEY (notification_schedule_id) REFERENCES notification_schedules(id) ON DELETE CASCADE,
  INDEX (status, remind_at)
);
```

Khi reminder `fired`:
- Load item → get assignees (`item.users`)
- For each assignee × for each channel in `schedule.channels`:
  - Create notification record (event_key = `reminder_before/on/after`)
  - Create delivery record per channel
  - Dispatch queued job
- Mark reminder.status = `fired`, fired_at = now.

## 5. Event dispatch — integration points

### 5.1. Document status → issued

Modify `TaskAssignmentDocumentService::changeStatus()` ([app/Modules/TaskAssignment/Services/TaskAssignmentDocumentService.php:186-212]):

```php
// Sau DB transaction, trước return
if ($newStatus === TaskAssignmentDocumentStatusEnum::Issued->value && $oldStatus !== $newStatus) {
    event(new DocumentIssued($document));
}
```

Cũng trigger khi create document với status=issued ngay từ đầu (trong `store()` method).

### 5.2. Task submitted (chờ confirm)

Modify `TaskAssignmentItemService::updateProgress()`:

```php
if ($newStatus === TaskProgressStatusEnum::Reported->value && $oldStatus !== $newStatus) {
    event(new TaskCompleted($item));
}
```

### 5.3. Task confirmed done

Modify `TaskAssignmentItemService::confirmDone()`:

```php
// After item->save()
event(new TaskConfirmed($item));
```

### 5.4. Reminders created/updated/cancelled

- **Item created OR document issued** → `ReminderScheduler::scheduleFor($item)` — create reminder records per enabled schedule × enabled channel × assignee.
- **Item updated** (end_at change) → `ReminderScheduler::reschedule($item)` — delete pending, create lại.
- **Item status → done** → `TaskAssignmentReminder::where('item_id', $item->id)->where('status', 'pending')->update(['status' => 'cancelled'])`.

## 6. Notification creation flow (shared cho events + reminders)

Service `NotificationDispatcher` chịu trách nhiệm từ (event/reminder) → tạo notification + deliveries + dispatch job.

```php
class NotificationDispatcher
{
    public function dispatch(
        string $eventKey,            // 'document_issued', 'reminder_before', ...
        User $recipient,
        Model $notifiable,           // TaskAssignmentItem hoặc model khác (polymorphic)
        array $channels,             // ['sms','mail',...]
        ContentBuilder $builder,     // builder riêng cho event
        array $builderArgs = [],
    ): Notification {
        // 1. Create notification record (parent)
        $notification = Notification::create([
            'user_id' => $recipient->id,
            'event_key' => $eventKey,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'title' => $builder->title($recipient, ...$builderArgs),
            'body' => $builder->shortBody($recipient, ...$builderArgs),
            'context' => $builder->inAppContext($recipient, ...$builderArgs),
        ]);

        // 2. Create delivery records + dispatch jobs
        foreach ($channels as $channelKey) {
            $delivery = NotificationDelivery::create([
                'notification_id' => $notification->id,
                'channel' => $channelKey,
                'status' => 'pending',
            ]);
            SendDeliveryJob::dispatch($delivery->id, $builderArgs)->onQueue('notifications');
        }

        return $notification;
    }
}
```

## 6b. Listeners (queued)

```php
class SendDocumentIssuedNotifications implements ShouldQueue
{
    public function handle(DocumentIssued $event): void
    {
        $config = NotificationEventConfig::where('event_key', 'document_issued')->first();
        if (! $config?->enabled) return;

        $items = $event->document->items()->with('users')->get();
        $builder = app(DocumentIssuedContentBuilder::class);
        $dispatcher = app(NotificationDispatcher::class);

        foreach ($items as $item) {
            foreach ($item->users as $user) {
                $dispatcher->dispatch(
                    eventKey: 'document_issued',
                    recipient: $user,
                    notifiable: $item,
                    channels: $config->channels,
                    builder: $builder,
                    builderArgs: [$item, $event->document],
                );
            }
        }
    }
}
```

## 6c. SendDeliveryJob (mỗi delivery = 1 job)

```php
class SendDeliveryJob implements ShouldQueue
{
    public function __construct(public int $deliveryId, public array $builderArgs) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::with('notification.user')->find($this->deliveryId);
        if (! $delivery || $delivery->status !== 'pending') return;

        $notification = $delivery->notification;
        $recipient = $notification->user;
        $builder = ContentBuilderRegistry::for($notification->event_key);

        $payload = $builder->build(
            channelKey: $delivery->channel,
            recipient: $recipient,
            ...$this->builderArgs,
        );

        if ($payload === null) {
            $delivery->update(['status' => 'skipped', 'error_message' => 'Recipient missing field for channel']);
            return;
        }

        $results = app(NotificationService::class)->send($payload);
        $result = $results[0];

        $delivery->update([
            'status' => $result->success ? 'sent' : 'failed',
            'message_id' => $result->messageId,
            'error_message' => $result->error,
            'sent_at' => $result->success ? now() : null,
        ]);
    }
}
```

Tương tự cho `SendTaskCompletedNotifications` (recipient = `item.assignedBy`) và `SendTaskConfirmedNotifications` (recipients = `item.users`).

## 7. ContentBuilder API

```php
interface ContentBuilder
{
    /**
     * Build NotificationPayload for given channel. Return null to skip.
     */
    public function build(string $channelKey, User $recipient, ...$args): ?NotificationPayload;
}
```

Mỗi builder cài 4 method `toSms`, `toMail`, `toZalo`, `toFcm` — dispatch từ `build()` theo `channelKey`.

**Ví dụ `DocumentIssuedContentBuilder::toMail()`:**

```php
protected function toMail(User $recipient, TaskAssignmentItem $item, TaskAssignmentDocument $doc): NotificationPayload
{
    $html = view('notifications.document_issued.email', compact('recipient', 'item', 'doc'))->render();
    return new NotificationPayload(
        channels: ['mail'],
        recipient: new Recipient(email: $recipient->email, name: $recipient->name),
        content: $html,
        subject: "Văn bản giao việc mới: {$doc->name}",
        context: ['document_id' => $doc->id, 'item_id' => $item->id],
    );
}
```

**`toSms()`:**
```php
return new NotificationPayload(
    channels: ['sms'],
    recipient: new Recipient(phone: $recipient->phone),
    content: "Ban duoc giao cong viec moi: {$item->title}. Han: {$item->end_at->format('d/m/Y H:i')}. Tran trong !",
    context: [...],
);
```

**`toZalo()`** — map sang template_data của Zalo OA template đã đăng ký:
```php
return new NotificationPayload(
    channels: ['zalo'],
    recipient: new Recipient(phone: $recipient->phone),
    content: '',
    context: [
        'task_title' => $item->title,
        'document' => $doc->name,
        'deadline' => $item->end_at->format('d/m/Y H:i'),
        'customer_name' => $recipient->name,
    ],
);
```

**`toFcm()`** — Web push:
```php
return new NotificationPayload(
    channels: ['fcm'],
    recipient: new Recipient(fcmToken: $recipient->fcm_token),
    content: "Bạn được giao: {$item->title}",
    subject: "Công việc mới",
    context: ['url' => "/tasks/{$item->id}"],
);
```

Nếu recipient thiếu field (vd không có phone cho SMS) → builder return null, listener skip silently.

## 8. Scheduler (reminder processing)

### Cron: every minute

`routes/console.php` hoặc `bootstrap/app.php`:
```php
Schedule::command('notifications:process-reminders')->everyMinute()->withoutOverlapping();
```

### Artisan command: `notifications:process-reminders`

```php
TaskAssignmentReminder::where('status', 'pending')
    ->where('remind_at', '<=', now())
    ->chunk(100, function ($reminders) {
        foreach ($reminders as $reminder) {
            SendScheduledReminderJob::dispatch($reminder)->onQueue('notifications');
        }
    });
```

### `SendScheduledReminderJob`

```php
public function handle(): void
{
    $reminder = $this->reminder->fresh();
    if ($reminder->status !== 'pending') return;   // cancelled/sent in meantime

    $item = $reminder->item()->with('users')->first();
    if (! $item || $item->processing_status === 'done') {
        $reminder->update(['status' => 'cancelled']);
        return;
    }

    $builder = app(ReminderContentBuilder::class);
    $payload = $builder->build($reminder->channel, $reminder->recipient, $item, $reminder->moment);
    if ($payload === null) {
        $reminder->update(['status' => 'failed', 'error_message' => 'No recipient field']);
        return;
    }

    $results = app(NotificationService::class)->send($payload);
    $ok = $results[0]->success ?? false;
    $reminder->update([
        'status' => $ok ? 'sent' : 'failed',
        'sent_at' => $ok ? now() : null,
        'error_message' => $ok ? null : ($results[0]->error ?? 'Unknown error'),
    ]);
}
```

## 9. API endpoints

### Admin config

| Method | Path | Permission | Mô tả |
|--------|------|------------|-------|
| GET | `/api/notifications/event-configs` | `notifications.event-configs.index` | List 6 event configs |
| PUT | `/api/notifications/event-configs/{event_key}` | `notifications.event-configs.update` | Update enabled + channels |
| GET | `/api/notifications/schedules` | `notifications.schedules.index` | List all reminder schedules |
| POST | `/api/notifications/schedules` | `notifications.schedules.store` | Create |
| PUT | `/api/notifications/schedules/{id}` | `notifications.schedules.update` | Update |
| DELETE | `/api/notifications/schedules/{id}` | `notifications.schedules.destroy` | Delete |

### User-facing (notification list)

| Method | Path | Permission | Mô tả |
|--------|------|------------|-------|
| GET | `/api/notifications/me` | (none — authenticated only) | List notifications của user hiện tại (filter: `read`, pagination) |
| GET | `/api/notifications/me/unread-count` | (none) | Đếm notification chưa đọc |
| PATCH | `/api/notifications/me/{id}/read` | (none) | Mark 1 notification là đã đọc |
| PATCH | `/api/notifications/me/read-all` | (none) | Mark all đã đọc |
| DELETE | `/api/notifications/me/{id}` | (none) | Xóa 1 notification khỏi list (soft delete hoặc hard delete — phải không? MVP: hard) |

### Admin audit (optional — xem delivery logs)

| Method | Path | Permission | Mô tả |
|--------|------|------------|-------|
| GET | `/api/notifications/{id}/deliveries` | `notifications.audit.view` | Xem list delivery của 1 notification (audit push logs) |

Body cho update event config:
```json
{ "enabled": true, "channels": ["sms", "mail"] }
```

Body cho schedule store/update:
```json
{
  "moment": "before",
  "offset_minutes": 1440,
  "channels": ["sms", "mail"],
  "enabled": true,
  "label": "Nhắc trước 1 ngày",
  "sort_order": 1
}
```

Permissions auto-assigned cho Super Admin qua `PermissionSeeder`.

## 10. Error handling & edge cases

| Scenario | Behavior |
|----------|----------|
| User thiếu phone/email/fcm_token | ContentBuilder return null → listener skip, NotificationService log warning |
| Event config disabled | Listener early return, no send |
| Channel disabled global (`*_enabled=0`) | Channel tự return fail — Listener ghi vào log |
| Item đã done trước khi reminder fire | `SendScheduledReminderJob` check status, mark cancelled |
| Deadline thay đổi sau khi reminder đã create | `ReminderScheduler::reschedule` xóa pending, tạo lại |
| Document revert `issued → draft` | Không cancel reminders (design decision: reminders bám theo item, không bám theo document status) |
| Queue worker crash giữa chừng | Reminder vẫn `pending`, sẽ retry lần scheduler chạy tiếp theo |

## 11. Testing strategy

### Unit tests

- `NotificationEventEnumTest`, `NotificationMomentEnumTest`
- `DocumentIssuedContentBuilderTest` — test toMail/toSms/toZalo/toFcm với mock data
- `TaskCompletedContentBuilderTest`, `TaskConfirmedContentBuilderTest`, `ReminderContentBuilderTest`
- `ReminderSchedulerTest` — verify rằng khi gọi `scheduleFor($item)` nó tạo đúng số reminder records
- `NotificationConfigControllerTest` — CRUD API

### Feature tests

- `DocumentIssuedEventTest` — assert event fired khi change status, listener dispatched
- `TaskCompletedEventTest`, `TaskConfirmedEventTest` tương tự
- `ProcessRemindersCommandTest` — fake time, insert reminders, run command, assert jobs dispatched
- `ReminderCancellationTest` — tạo reminder, đánh item done, assert pending cancelled

### Integration (manual qua `/api/notifications/test`)

Test endpoint đã có từ spec cũ — verify 4 channel gửi được.

## 12. Dependencies

- Laravel queue: `database` driver (đã có). Cần worker: `php artisan queue:work --queue=notifications,default`.
- Laravel scheduler: `php artisan schedule:run` mỗi phút qua cron (production) hoặc `php artisan schedule:work` (dev).
- PHPUnit tests chạy với `QUEUE_CONNECTION=sync`.

## 13. Acceptance criteria

- [ ] 6 event configs seed vào DB, admin CRUD được qua API.
- [ ] CRUD reminder schedules qua API.
- [ ] Document change status → issued → 3 channel được gửi (nếu enabled) đến mọi assignee.
- [ ] Task → reported → manager nhận notification.
- [ ] Task → done → assignees nhận confirm notification, pending reminders bị cancel.
- [ ] Reminder scheduler: tạo item với deadline + schedule rule → records trong `task_assignment_reminders` đúng số.
- [ ] Khi time tới `remind_at`, cronjob fire → notification được gửi.
- [ ] Recipient thiếu field → skip silently + log warning.
- [ ] Full test suite pass (target ~100+ tests).

## 14. Out of scope (để sau)

- UI admin (FE tự build theo API).
- In-app notification (`system` channel) — model có sẵn enum nhưng chưa implement.
- Per-module config UI ở trang danh sách document (ở lần nâng cấp sau).
- Template editor WYSIWYG cho admin.
- Đa ngôn ngữ.
- Per-user preference (user chọn muốn nhận channel nào).
- Multi-device FCM (1 user N browser/device).

## 15. Permissions bổ sung (PermissionSeeder)

Thêm vào `$PERMISSIONS`:
```php
'notifications.event-configs' => ['index', 'update'],
'notifications.schedules' => ['index', 'store', 'update', 'destroy'],
```

Thêm labels:
```php
'notifications.event-configs' => 'Cấu hình sự kiện thông báo',
'notifications.schedules' => 'Cấu hình lịch nhắc',
```
