# Lịch công tác — Hệ thống thông báo & nhắc lịch

## Tổng quan kiến trúc

Hệ thống thông báo Scheduling có **2 kênh**, cùng chạy trong listener:

```
Kênh A: Module-level Instant (admin config)
  notification_event_configs → notification_schedules (moment = null)
  Bắn ngay khi schedule publish/update/cancel

Kênh B: Per-schedule Reminder (user config)
  schedule_reminders (moment = immediate/before/on/after)
  Tính remind_at khi publish, cron poll và fire
```

**Nguyên tắc:** Nếu cả 2 kênh cùng được cấu hình → **cả 2 cùng bắn**, không loại trừ nhau.

**⚠️ Hạn chế hiện tại:** `scheduleFor()` / `cancelPending()` được gọi **sau** block `if (empty($channels)) { return; }` trong listener. Nếu org chưa có `notification_event_configs` cho module scheduling (hoặc config bị tắt), per-schedule reminder sẽ không được tính `remind_at` → cron không fire. Cần tách `scheduleFor()` ra trước early return nếu muốn 2 kênh độc lập thực sự.

---

## Các event kích hoạt thông báo

System có 4 event thuộc module Scheduling:

| Event key | Khi nào fire? | Listener |
|---|---|---|
| `schedule_published` | Schedule chuyển DRAFT → PUBLISHED (Observer `saved`) | `SendSchedulePublishedNotifications` |
| `schedule_updated` | Schedule đã PUBLISHED + thay đổi content/date/location (Observer `saved`) | `SendScheduleUpdatedNotifications` |
| `schedule_cancelled` | Schedule chuyển PUBLISHED → DRAFT, hoặc xóa schedule đã PUBLISHED (Observer `saved` + `deleted`) | `SendScheduleCancelledNotifications` |
| `schedule_reminder` | Cron poll `schedule_reminders.remind_at <= now()` | Không có listener riêng — cron gọi trực tiếp |

---

## Luồng chi tiết

### Bước 1: User tạo/sửa schedule

FE gửi `reminders[]` trong body `POST`/`PUT`/`PATCH`.

`ScheduleService::syncReminders()` (gọi trong `store()` và `update()`):
- Xóa reminder cũ (detach)
- Tạo bản ghi `schedule_reminders` mới với `status = pending`, chưa tính `remind_at`

### Bước 2: Schedule được publish (changeStatus)

Controller gọi `ScheduleService::changeStatus()` → `$schedule->update(['status' => PUBLISHED])`.

Eloquent `saved` event kích hoạt **`ScheduleObserver::saved()`**:

```
ScheduleObserver::saved(Schedule $schedule)
├── status=DRAFT → PUBLISHED
│   └── Event::dispatch(new SchedulePublished($schedule))
├── status=PUBLISHED → PUBLISHED (key fields changed: content/date/location)
│   └── Event::dispatch(new ScheduleUpdated($schedule, $changedFields))
└── status=PUBLISHED → DRAFT (hủy ban hành)
    └── Event::dispatch(new ScheduleCancelled($schedule))
```

Observer **chỉ dispatch event**, không gọi trực tiếp scheduler. Scheduler được gọi bên trong listener.

### Bước 3: Listener xử lý event (queue `ShouldQueue`)

#### 3a. `SendSchedulePublishedNotifications::handle()`

File: [app/Services/Notification/Listeners/SendSchedulePublishedNotifications.php](app/Services/Notification/Listeners/SendSchedulePublishedNotifications.php)

```php
1. resolveChannels($orgId):
   - Query notification_event_configs WHERE module_key='scheduling' AND event_key='schedule_published'
   - Nếu config không tồn tại hoặc disabled → return []
   - Lấy notification_schedules có moment = null (instant)
   - Trả về channels từ schedule đó

2. ⚠️ Nếu channels rỗng → return sớm (không gửi instant, cũng KHÔNG schedule reminder)

3. resolveRecipients($schedule):
   - Lấy danh sách user từ schedule.recipients (qua ScheduleReminderScheduler)

4. Gửi instant notification module-level:
   dispatcher->dispatch(
       eventKey: 'schedule_published',
       recipient: $user,
       notifiable: $schedule,
       channels: $channels,  // từ module-level config
       builder: schedule_published content builder,
   )

5. Schedule per-schedule reminders:
   scheduler->scheduleFor($schedule)
   - Với mỗi schedule_reminders:
     - immediate → remind_at = now()
     - before/on/after → tính từ date_time + session time + moment + offset_minutes
     - Nếu remind_at đã qua → remind_at = now() (fire ngay lần cron tiếp theo)
     - Update remind_at + status = pending
```

#### 3b. `SendScheduleUpdatedNotifications::handle()`

```php
1. resolveChannels() → lấy channels instant cho event 'schedule_updated'
2. ⚠️ Nếu channels rỗng → return sớm (không gửi instant, cũng KHÔNG re-schedule reminder)
3. Gửi instant notification (giống published nhưng builder khác)
4. scheduler->cancelPending($schedule)  // hủy reminder cũ
5. scheduler->scheduleFor($schedule)    // tạo reminder mới dựa trên date_time mới
```

#### 3c. `SendScheduleCancelledNotifications::handle()`

```php
1. resolveChannels() → lấy channels instant cho event 'schedule_cancelled'
2. ⚠️ Nếu channels rỗng → return sớm (không gửi instant, cũng KHÔNG cancelPending)
3. Gửi instant notification
4. scheduler->cancelPending($schedule)  // hủy tất cả reminder pending
```

### Bước 4: Observer `deleted()`

Khi schedule bị xóa:
- Nếu schedule đang PUBLISHED → `Event::dispatch(new ScheduleCancelled($schedule))`
- Nếu schedule chưa PUBLISHED → `$this->scheduler->cancelPending($schedule)` (chỉ hủy reminder, không gửi notification)

### Bước 5: Cron poll & fire reminders

Command: `notifications:process-reminders` ([ProcessRemindersCommand](app/Services/Notification/Console/ProcessRemindersCommand.php))

Xử lý 3 loại reminder tuần tự:
1. `task_assignment_reminders` (status=pending, remind_at <= now)
2. `meeting_reminders` (status=pending, scheduled_at <= now)
3. `schedule_reminders` (status=pending, remind_at <= now)

Với schedule reminders (`fireScheduleReminder`):
```
1. Load schedule (eager load recipients.user)
2. Skip nếu schedule không tồn tại → status = cancelled
3. Skip nếu schedule không PUBLISHED → status = cancelled
4. Skip nếu organization_id = 0 → status = cancelled
5. Lấy channels từ reminder (array_map strtolower trim)
   ⚠️ Khác với instant: channels từ reminder, KHÔNG từ module-level config
6. Skip nếu channels rỗng → status = cancelled
7. Lặp từng recipient → NotificationDispatcher::dispatch()
8. Update reminder: status = fired, fired_at = now()
```

`NotificationDispatcher::dispatch()`:
```
1. Tạo Notification record (user_id, event_key, title, body, context)
2. Với mỗi channel → tạo NotificationDelivery (status = pending)
3. Dispatch SendDeliveryJob cho từng delivery → queue 'notifications'
```

---

## 3 tầng dữ liệu

```
Tầng 1: notification_event_configs
  - Bật/tắt notification cho module scheduling
  - 1 record per (organization, module_key, event_key)
  - Có FK tới notification_schedules

Tầng 2: notification_schedules
  - Cấu hình thời điểm + kênh cho từng event
  - moment = null → instant (bắn ngay khi event fire)
  - moment = before/on/after → reminder (cron poll)
  - Admin config qua endpoint /api/schedules/notification-config/

Tầng 3: schedule_reminders
  - User chọn khi tạo/sửa từng schedule
  - moment = immediate/before/on/after
  - channels lưu trực tiếp trên reminder
  - remind_at được tính khi schedule publish
```

---

## 4 loại moment trên schedule_reminders

| moment | Ý nghĩa | `offset_minutes` | `remind_at` |
|--------|---------|-------------------|-------------|
| `immediate` | Bắn ngay khi schedule được publish | 0 (không dùng) | `now()` |
| `before` | Nhắc **trước** thời gian sự kiện N phút | Số phút trước | `event_time - offset_minutes` |
| `on` | Nhắc **đúng** thời điểm sự kiện | 0 (không dùng) | `event_time` |
| `after` | Nhắc **sau** thời gian sự kiện N phút | Số phút sau | `event_time + offset_minutes` |

`event_time` = `schedule.date_time` date + session time (S=07:30, C=13:30, T=19:30).

---

## So sánh kênh instant vs reminder

| | Module-level Instant | Per-schedule Reminder |
|---|---|---|
| Cấu hình ở đâu | Admin panel (`notification_event_configs` + `notification_schedules`) | API body mỗi schedule (`reminders[]`) |
| Trigger | Event fire (Observer → Listener queue job) | Cron poll `remind_at <= now()` |
| Channels từ đâu | `notification_schedules.channels` (lấy bản ghi có `moment = null`) | `schedule_reminders.channels` |
| Recipients từ đâu | `schedule.recipients` (qua `ScheduleReminderScheduler::resolveRecipients`) | `schedule.recipients` (qua cron load `schedule.recipients.user`) |
| Content builder | `schedule_published` / `schedule_updated` / `schedule_cancelled` | `schedule_reminder` |
| Gửi khi nào? | Ngay khi event được dispatch (async queue) | Khi `remind_at <= now()` (poll mỗi phút) |
| Độc lập với module config? | Không — tắt config → không gửi | **Không** — nếu config tắt/thiếu, listener return sớm trước khi gọi `scheduleFor()` → reminder không có `remind_at` |

---

## Flow tổng quan (sequence)

```
FE: POST/PUT schedule (có reminders[])
  → ScheduleService::syncReminders() — tạo schedule_reminders (status=pending, chưa có remind_at)

FE: PATCH /{id}/status { status: "PUBLISHED" }
  → ScheduleService::changeStatus() — $schedule->update(['status' => PUBLISHED])
  → Eloquent saved event
  → ScheduleObserver::saved()
    → Event::dispatch(new SchedulePublished($schedule))
      → [QUEUE] SendSchedulePublishedNotifications::handle()
        ├── resolveChannels() — query notification_event_configs
        ├── ⚠️ Nếu channels rỗng → return (bỏ qua cả instant + scheduleFor)
        ├── Module-level instant: NotificationDispatcher::dispatch()
        │   └── Notification + NotificationDelivery + SendDeliveryJob
        └── Per-schedule reminders: scheduler->scheduleFor($schedule)
            └── compute remind_at, update status=pending

CRON: notifications:process-reminders (mỗi phút)
  → ScheduleReminder::where('status','pending')->where('remind_at','<=',now())
  → fireScheduleReminder()
    ├── Guard: schedule tồn tại? status=PUBLISHED? org_id != 0? channels không rỗng?
    ├── Lấy channels từ reminder (strtolower, khác với module config)
    └── NotificationDispatcher::dispatch() cho từng recipient
        └── Update reminder: status=fired, fired_at=now()
```

---

## Dữ liệu reminder trong response

```json
{
  "reminders": [
    {
      "id": 15,
      "schedule_id": 42,
      "moment": "immediate",
      "offset_minutes": 0,
      "channels": ["fcm"],
      "status": "pending",
      "fired_at": null,
      "source": "CUSTOM",
      "reminder_type": "CUSTOM",
      "minutes_before": 0
    }
  ]
}
```

| Field | Kiểu | Mô tả |
|-------|------|-------|
| `id` | int | ID reminder |
| `schedule_id` | int | ID schedule |
| `moment` | string | `immediate`, `before`, `on`, `after` |
| `offset_minutes` | int | Số phút offset (có nghĩa với `before` và `after`) |
| `channels` | string[] | Kênh gửi: `fcm`, `mail`, `zalo`, `zalo_zns`, `sms` |
| `status` | string | `pending`, `fired`, `cancelled` |
| `fired_at` | string\|null | Thời điểm fire (`H:i:s d/m/Y`) |
| `source`/`reminder_type` | string | `CUSTOM` (tự chọn) hoặc `PRESET` (theo mẫu) |
| `minutes_before` | int | **Deprecated** — dùng `offset_minutes` |

---

## Request body khi gửi reminders

```json
{
  "reminders": [
    { "moment": "immediate", "channels": ["fcm"] },
    { "moment": "before", "offset_minutes": 30, "channels": ["fcm", "mail"] },
    { "moment": "on", "channels": ["zalo"] },
    { "moment": "after", "offset_minutes": 120, "channels": ["mail"] }
  ]
}
```

| Field | Bắt buộc | Mặc định | Ghi chú |
|-------|----------|----------|---------|
| `moment` | Không | `"before"` | `immediate`, `before`, `on`, `after` |
| `offset_minutes` | Không | `0` | Chỉ có nghĩa với `before` và `after` |
| `channels` | Không | `[]` | `fcm`, `mail`, `zalo`, `zalo_zns`, `sms` |
| `source` | Không | `"CUSTOM"` | `CUSTOM` hoặc `PRESET` |

---

## So sánh với TaskAssignment

| | TaskAssignment | Scheduling |
|---|---|---|
| Bảng reminder | `task_assignment_reminders` | `schedule_reminders` |
| Cột thời điểm | `moment` (before/on/after) | `moment` (immediate/before/on/after) |
| Cột thời gian fire | `remind_at` | `remind_at` |
| Cột trạng thái | `status` (pending/fired/cancelled) | `status` (pending/fired/cancelled) |
| Xử lý | Cron poll `remind_at <= now()` | Cron poll `remind_at <= now()` |
| Kênh | Từ `notification_schedules.channels` | Lưu trực tiếp trên `schedule_reminders.channels` |
| immediate | Không có | Có (per-schedule, bắn cùng publish) |
| Module-level instant | Có (qua event + listener) | Có (qua event + listener) |
| Listener tính remind_at | Listener gọi scheduler | Listener gọi scheduler |

---

## Lưu ý

- `syncReminders()` ([ScheduleService.php:511](app/Modules/Scheduling/Services/ScheduleService.php#L511)) lưu channel **UPPERCASE** (`array_map('strtoupper', ...)`)
- `fireScheduleReminder()` ([ProcessRemindersCommand.php:202](app/Services/Notification/Console/ProcessRemindersCommand.php#L202)) đọc channel **lowercase** (`array_map(fn($c) => strtolower(trim($c)), ...)`)
- Hai case không khớp nhau — cần verify `SendDeliveryJob` expect case nào
- `immediate` chỉ có ở per-schedule (`schedule_reminders`), không có trong `reminder_presets`
- Module-level instant dùng `notification_schedules` với `moment = null` (không phải `immediate`)
- Không gửi `reminders` → giữ nguyên reminder cũ
- Gửi `reminders: []` → xóa tất cả reminder
- Tất cả listener đều implement `ShouldQueue` — xử lý async qua queue
- **⚠️ Per-schedule reminder phụ thuộc vào module config:** nếu `notification_event_configs` cho scheduling chưa được tạo hoặc bị disabled, listener return sớm → `scheduleFor()` không chạy → reminder không có `remind_at` → cron không fire. Để 2 kênh độc lập thực sự, cần move `scheduleFor()` / `cancelPending()` lên trước block `if (empty($channels)) { return; }` trong cả 3 listener.
