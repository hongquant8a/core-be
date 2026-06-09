# Per-Record Reminder — Nhắc lịch cho từng bản ghi

Tài liệu này mô tả toàn bộ kiến trúc nhắc lịch (reminder) trong hệ thống, bao gồm cả 3 module: **Scheduling** (lịch công tác), **Meeting** (cuộc họp), **TaskAssignment** (giao việc).

---

## 1. Tổng quan

Hệ thống có **2 nguồn reminder** hoạt động song song và độc lập:

| Nguồn | Ai quản lý | Dữ liệu ở đâu | Khi nào tạo |
|-------|-----------|---------------|-------------|
| **PRESET** — nhắc toàn module | Admin, qua giao diện cấu hình notification | `notification_event_configs` → `notification_schedules` | Tự động khi record được publish/issue (Observer → Scheduler) |
| **CUSTOM** — nhắc từng bản ghi | User, ngay trên form tạo/sửa record | `reminders[]` trong body API store/update | Khi user gửi POST/PUT có kèm `reminders[]` |

**Use case chính**: Admin tắt PRESET config module (đỡ tốn noti cho toàn bộ record), user tự thêm CUSTOM reminder cho từng record cần nhắc.

**Nguyên tắc**:
- PRESET và CUSTOM **không ảnh hưởng lẫn nhau** — mỗi bên có vòng đời riêng
- CUSTOM là **add-on**, không thay đổi gì luồng PRESET cũ
- Resource API **chỉ trả CUSTOM** cho FE — PRESET là nội bộ hệ thống

---

## 2. Kiến trúc dữ liệu

### 2.1. Bảng cấu hình module (PRESET)

```
notification_event_configs
├── module_key: 'scheduling' | 'meeting' | 'task_assignment'
├── event_key: 'schedule_reminder' | 'meeting_reminder_before' | 'reminder_before' | ...
└── enabled: true/false

notification_schedules
├── notification_event_config_id
├── moment: 'before' | 'on' | 'after' | 'immediate'
├── offset_minutes: int
└── channels: json ["mail","zalo","fcm"]
```

### 2.2. Bảng reminder thực tế (PRESET + CUSTOM chung bảng, phân biệt bằng `source`)

| Bảng | Module | Mốc tham chiếu |
|------|--------|----------------|
| `schedule_reminders` | Scheduling | `date_time` + `session` (S: 7h30, C: 13h30, T: 19h30) |
| `meeting_reminders` | Meeting | `start_time` |
| `task_assignment_reminders` | TaskAssignment | `end_at` (deadline) |

Cấu trúc chung của mỗi bảng reminder:

| Cột | Kiểu | Mô tả |
|-----|------|-------|
| `id` | bigint | PK |
| `{module}_id` | bigint | FK tới record cha (schedule_id, meeting_id, task_assignment_item_id) |
| `moment` | varchar | `before` / `on` / `after` / `immediate` (chỉ Scheduling) |
| `offset_minutes` | int | Phút offset từ mốc tham chiếu. 0 nếu moment=on |
| `channels` | json | Kênh gửi: `["mail","sms","zalo","zalo_zns","fcm"]`. null nếu PRESET (inherit từ config) |
| `source` | varchar | `PRESET` — hệ thống sinh; `CUSTOM` — user tạo |
| `status` | varchar | `pending` → `fired` / `cancelled` |
| `remind_at` | datetime | Thời điểm cron sẽ fire reminder này |
| `notification_schedule_id` | bigint | FK → `notification_schedules` (chỉ có ở PRESET) |
| `fired_at` | datetime | Thời điểm reminder được gửi |

### 2.3. Relationship trong Model

```
Meeting
├── reminders() → hasMany MeetingReminder

TaskAssignmentItem
├── reminders() → hasMany TaskAssignmentReminder

Schedule
├── reminders() → hasMany ScheduleReminder
```

---

## 3. API — Định dạng `reminders`

Cả 3 module dùng chung định dạng trong body store/update:

```json
{
  "reminders": [
    {
      "moment": "before",
      "offset_minutes": 30,
      "channels": ["mail", "zalo"]
    },
    {
      "moment": "on",
      "offset_minutes": 0,
      "channels": ["fcm"]
    },
    {
      "moment": "after",
      "offset_minutes": 15,
      "channels": ["zalo_zns"]
    }
  ]
}
```

### 3.1. Field reference

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `moment` | string | **Có** | `before` — nhắc trước mốc tham chiếu; `on` — nhắc đúng mốc; `after` — nhắc sau mốc. Scheduling có thêm `immediate` (nhắc ngay lập tức) |
| `offset_minutes` | int | Không | Phút offset từ mốc tham chiếu. Mặc định `0`. Với `moment=before`: `start_time - offset_minutes`. Với `moment=after`: `start_time + offset_minutes` |
| `channels` | string[] | Không | Danh sách kênh gửi: `mail`, `sms`, `zalo`, `zalo_zns`, `fcm`. Mảng rỗng = reminder vẫn được tạo nhưng không gửi noti. **Lưu ý**: giá trị được uppercase khi lưu DB |

### 3.2. Mốc tham chiếu theo module

| Module | Mốc tham chiếu | Cách tính `remind_at` |
|--------|---------------|----------------------|
| **Scheduling** | `date_time` + `session` | Session S=7:30, C=13:30, T=19:30 → dùng làm base time. `immediate` = `now()` |
| **Meeting** | `start_time` | `before`: `start_time - offset`. `on`: `start_time`. `after`: `start_time + offset` |
| **TaskAssignment** | `end_at` | `before`: `end_at - offset`. `on`: `end_at`. `after`: `end_at + offset` |

---

## 4. API — Store vs Update

### 4.1. Store (POST)

**Luôn gửi `reminders`**. Nếu không có nhu cầu nhắc, gửi mảng rỗng `[]`.

```
POST /api/schedules
POST /api/meetings
POST /api/task-assignment-items

Body:
{
  "title": "...",
  "start_time": "...",
  "reminders": [
    { "moment": "before", "offset_minutes": 30, "channels": ["mail"] }
  ]
}
```

**Hành vi**: `Service.syncReminders()` xóa toàn bộ CUSTOM cũ (nếu có) → tạo CUSTOM mới từ input với `source=CUSTOM, status=pending`.

### 4.2. Update (PUT)

| Cách gửi | Hành vi |
|----------|---------|
| **Không gửi key `reminders`** | Giữ nguyên toàn bộ reminders hiện tại (không đụng gì) |
| **Gửi `"reminders": []`** | Xóa toàn bộ CUSTOM reminders của record này. PRESET không bị ảnh hưởng |
| **Gửi `"reminders": [{...}]`** | Xóa CUSTOM cũ → tạo CUSTOM mới từ input |

```
PUT /api/schedules/{id}
PUT /api/meetings/{id}
PUT /api/task-assignment-items/{id}

// Giữ nguyên reminders (không gửi key)
Body: { "title": "new title" }

// Xóa hết CUSTOM
Body: { "title": "new title", "reminders": [] }

// Thay thế CUSTOM
Body: { "title": "new title", "reminders": [{ "moment": "on", "channels": ["fcm"] }] }
```

### 4.3. Các endpoint liên quan

| Module | Method | Endpoint | Ghi chú |
|--------|--------|----------|---------|
| Scheduling | POST | `/api/schedules` | `reminders` bắt buộc trong body |
| Scheduling | PUT | `/api/schedules/{schedule}` | `reminders` không bắt buộc |
| Meeting | POST | `/api/meetings` | `reminders` bắt buộc trong body |
| Meeting | PUT | `/api/meetings/{meeting}` | `reminders` không bắt buộc |
| TaskAssignment | POST | `/api/task-assignment-items` | `reminders` bắt buộc trong body |
| TaskAssignment | PUT | `/api/task-assignment-items/{taskAssignmentItem}` | `reminders` không bắt buộc |

---

## 5. Luồng hoạt động chi tiết

### 5.1. Toàn cảnh

```
┌─────────────────────────────────────────────────────────────────┐
│  BƯỚC 1: FE gửi reminders[] trong POST/PUT                      │
│  ─────────────────────────────────────                          │
│  Controller → Service.store/update(data, reminders)             │
│  Service.syncReminders():                                       │
│    DELETE FROM *_reminders WHERE record_id=X AND source='CUSTOM'│
│    INSERT INTO *_reminders (source=CUSTOM, status=pending)      │
│  → Reminders đã tồn tại trong DB nhưng remind_at đang NULL      │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  BƯỚC 2: Observer phát hiện record được publish/issue           │
│  ─────────────────────────────────────────                      │
│  Trigger: saved() + status chuyển draft→published               │
│          hoặc wasChanged(['start_time','end_at','status'])      │
│                                                                  │
│  Scheduler.scheduleFor():                                       │
│    a) DELETE pending PRESET cũ (WHERE source='PRESET')          │
│    b) SELECT notification_event_configs → notification_schedules│
│       → INSERT PRESET mới từ config module                      │
│    c) SELECT pending CUSTOM (WHERE source='CUSTOM')             │
│       → UPDATE remind_at = compute(moment, offset, reference)   │
│                                                                  │
│  Kết quả: Tất cả reminders (PRESET + CUSTOM) đã có remind_at    │
│           và sẵn sàng để cron xử lý                             │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  BƯỚC 3: Cron notifications:process-reminders (mỗi phút)       │
│  ──────────────────────────────────────────────                 │
│  SELECT * FROM *_reminders                                     │
│    WHERE status='pending' AND remind_at <= NOW()                │
│                                                                  │
│  Với mỗi reminder:                                              │
│    - CUSTOM: channels = reminder.channels (từ user input)       │
│    - PRESET: channels = notification_schedule.channels (config) │
│                                                                  │
│  → Dispatch notification đến từng recipient qua từng channel    │
│  → UPDATE status='fired', fired_at=NOW()                        │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2. Chi tiết từng bước

#### Bước 1: Service.syncReminders()

**File**: `MeetingService.php` / `TaskAssignmentItemService.php` / `ScheduleService.php`

```php
// Pattern chung — ví dụ MeetingService
private function syncReminders(Meeting $meeting, array $reminders): void
{
    // Chỉ xóa CUSTOM, không đụng PRESET
    $meeting->reminders()->where('source', 'CUSTOM')->delete();

    foreach ($reminders as $r) {
        $meeting->reminders()->create([
            'moment'         => $r['moment'] ?? 'before',
            'offset_minutes' => (int) ($r['offset_minutes'] ?? 0),
            'channels'       => array_map('strtoupper', (array) ($r['channels'] ?? [])),
            'source'         => 'CUSTOM',
            'status'         => 'pending',
            'remind_at'      => null,  // Sẽ được Scheduler tính
        ]);
    }
}
```

**Quan trọng**: `remind_at` để `null` ở bước này — Scheduler sẽ tính ở bước 2 khi record được publish.
Điều này đảm bảo remind_at luôn khớp với `start_time`/`end_at` thực tế của record (có thể thay đổi giữa lúc tạo và lúc publish).

#### Bước 2: Observer + Scheduler

**Trigger**: Observer `saved()` khi record chuyển trạng thái publish/issue.

| Module | Observer | Điều kiện trigger `scheduleFor()` |
|--------|----------|----------------------------------|
| Scheduling | `ScheduleObserver` | `status` chuyển từ khác → `PUBLISHED` |
| Meeting | `MeetingObserver` | `status === 'published'` AND (`start_time` hoặc `status` thay đổi) |
| TaskAssignment | `TaskAssignmentItemObserver` | Document `status === 'issued'` AND (`end_at`, `processing_status`, hoặc `deadline_type` thay đổi) |

**Hủy reminders**: Observer tự động cancel pending reminders khi:
- Record chuyển sang trạng thái kết thúc (cancelled, completed, done, hoặc unpublish)
- Record bị xóa (`deleted` event)

**Scheduler.scheduleFor()** — 3 bước con:

```
a) Xóa pending PRESET cũ
   DELETE FROM *_reminders
   WHERE record_id = X AND status = 'pending' AND source = 'PRESET'

b) Tạo PRESET mới từ notification config
   SELECT notification_event_configs JOIN notification_schedules
   WHERE module_key = 'meeting' AND enabled = true
   → INSERT vào *_reminders (source=PRESET, remind_at=đã tính)

c) Tính remind_at cho CUSTOM
   SELECT FROM *_reminders
   WHERE record_id = X AND status = 'pending' AND source = 'CUSTOM'
   → UPDATE remind_at = compute(moment, offset, reference_time)
```

**Công thức tính remind_at**:

```php
// Meeting & TaskAssignment
match ($moment) {
    'before' => $reference->copy()->subMinutes($offsetMinutes),
    'on'     => $reference->copy(),
    'after'  => $reference->copy()->addMinutes($offsetMinutes),
}

// Scheduling (có thêm 'immediate')
match ($moment) {
    'immediate' => now(),
    'before'    => $reference->copy()->subMinutes($offsetMinutes),
    'on'        => $reference->copy(),
    'after'     => $reference->copy()->addMinutes($offsetMinutes),
}
```

#### Bước 3: Cron ProcessRemindersCommand

**File**: `app/Services/Notification/Console/ProcessRemindersCommand.php`

**Command**: `sail artisan notifications:process-reminders`

**Tần suất**: Mỗi phút (cấu hình trong Laravel scheduler)

**Xử lý tuần tự 3 loại reminder**:

```
1. task_assignment_reminders — WHERE status='pending' AND remind_at <= NOW()
   → fireReminder()

2. meeting_reminders — WHERE status='pending' AND (remind_at <= NOW() OR scheduled_at <= NOW())
   → fireMeetingReminder()

3. schedule_reminders — WHERE status='pending' AND remind_at <= NOW()
   → fireScheduleReminder()
```

**Phân biệt channels theo source**:

```php
// Pattern chung trong mỗi fire*Reminder()
if ($reminder->source === 'CUSTOM' && !empty($reminder->channels)) {
    $channels = $reminder->channels;  // Từ user input
} else {
    // PRESET: lấy từ notification_schedule config
    $schedule = $reminder->notificationSchedule; // hoặc $reminder->schedule
    $channels = $schedule->channels;
}
```

**Validation trước khi fire**:
- Meeting: bỏ qua nếu meeting bị cancelled. Vẫn fire nếu đã qua `end_time` (vd: nhắc "đã kết thúc").
- TaskAssignment: bỏ qua nếu item đã done/cancelled.
- Scheduling: bỏ qua nếu schedule không còn PUBLISHED.
- Tất cả: bỏ qua nếu config không enabled hoặc khác organization.

**Recipients**:
- **Meeting**: tất cả participants + chairperson + operator (có user_id)
- **TaskAssignment**: tất cả users được gán vào item
- **Scheduling**: tất cả recipients của schedule

---

## 6. Cấu hình notification (PRESET)

### 6.1. Event keys

| Module | Event Key | Mô tả |
|--------|-----------|-------|
| Scheduling | `schedule_reminder` | 1 event duy nhất, schedules xác định mốc nhắc |
| Meeting | `meeting_reminder_before` | Nhắc trước cuộc họp |
| Meeting | `meeting_reminder_on` | Nhắc đúng giờ họp |
| Meeting | `meeting_reminder_after` | Nhắc sau cuộc họp |
| TaskAssignment | `reminder_before` | Nhắc trước deadline |
| TaskAssignment | `reminder_on` | Nhắc đúng deadline |
| TaskAssignment | `reminder_after` | Nhắc sau deadline |

### 6.2. Cách tắt/mở PRESET

1. Vào giao diện admin → Notification Config
2. Chọn module (Scheduling / Meeting / TaskAssignment)
3. Toggle `enabled` cho event key tương ứng
4. PRESET reminders cho event đó sẽ không được tạo khi record publish
5. CUSTOM reminders của user **không bị ảnh hưởng** — vẫn hoạt động bình thường

---

## 7. Response format

Resource **chỉ trả CUSTOM reminders** cho FE. PRESET là nội bộ hệ thống, không expose qua API.

### 7.1. MeetingResource

```json
{
  "id": 1,
  "title": "Họp giao ban",
  "start_time": "08:00:00 15/06/2026",
  "reminders": [
    {
      "id": 5,
      "meeting_id": 1,
      "moment": "before",
      "offset_minutes": 30,
      "channels": ["MAIL", "ZALO"],
      "status": "pending",
      "fired_at": null,
      "source": "CUSTOM",
      "reminder_type": "CUSTOM",
      "minutes_before": 30,
      "trigger": "before"
    }
  ]
}
```

### 7.2. ItemResource (TaskAssignment)

```json
{
  "id": 10,
  "name": "Báo cáo tháng",
  "end_at": "17:00:00 30/06/2026",
  "reminders": [
    {
      "id": 3,
      "task_assignment_item_id": 10,
      "moment": "before",
      "offset_minutes": 60,
      "channels": ["FCM"],
      "status": "pending",
      "fired_at": null,
      "source": "CUSTOM",
      "reminder_type": "CUSTOM",
      "minutes_before": 60,
      "trigger": "before"
    }
  ]
}
```

### 7.3. ScheduleResource

```json
{
  "id": 20,
  "content": "Họp chi bộ",
  "date_time": "2026-06-15T08:00:00+07:00",
  "reminders": [
    {
      "id": 7,
      "schedule_id": 20,
      "moment": "immediate",
      "offset_minutes": 0,
      "channels": ["MAIL"],
      "status": "fired",
      "fired_at": "08:00:00 15/06/2026",
      "source": "CUSTOM",
      "reminder_type": "CUSTOM",
      "minutes_before": 0,
      "trigger": "immediate"
    }
  ]
}
```

**Lưu ý**: Field `minutes_before` và `trigger` là deprecated — dùng `offset_minutes` và `moment` thay thế. Giữ lại để backward compat với FE cũ.

---

## 8. Cron

```bash
sail artisan notifications:process-reminders
```

Cấu hình trong `app/Console/Kernel.php`:

```php
$schedule->command('notifications:process-reminders')->everyMinute();
```

**Cơ chế**:
- Mỗi lần chạy xử lý tối đa 100 reminders mỗi loại (chunkById)
- Reminder đã fired sẽ không bị xử lý lại (`status='pending'`)
- Reminder bị cancelled (record không tồn tại, config disabled, ...) được update `status='cancelled'`

---

## 9. File tham chiếu

### 9.1. Models

| File | Vai trò |
|------|---------|
| `app/Modules/Scheduling/Models/Schedule.php` | Model chính + `reminders()` relationship |
| `app/Modules/Scheduling/Models/ScheduleReminder.php` | Reminder model (fillable, casts) |
| `app/Modules/Meeting/Models/Meeting.php` | Model chính + `reminders()` relationship |
| `app/Modules/Meeting/Models/MeetingReminder.php` | Reminder model (fillable, casts) |
| `app/Modules/TaskAssignment/Models/TaskAssignmentItem.php` | Model chính + `reminders()` relationship |
| `app/Modules/TaskAssignment/Models/TaskAssignmentReminder.php` | Reminder model (fillable, casts) |
| `app/Modules/Core/Models/NotificationEventConfig.php` | Cấu hình event theo module |
| `app/Modules/Core/Models/NotificationSchedule.php` | Mốc nhắc + kênh cho từng event |

### 9.2. Services

| File | Vai trò |
|------|---------|
| `app/Modules/Scheduling/Services/ScheduleService.php` | `syncReminders()` — xóa CUSTOM, tạo mới từ input |
| `app/Modules/Meeting/Services/MeetingService.php` | `syncReminders()` — xóa CUSTOM, tạo mới từ input |
| `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php` | `syncReminders()` — xóa CUSTOM, tạo mới từ input |

### 9.3. Observers

| File | Vai trò |
|------|---------|
| `app/Modules/Scheduling/Observers/ScheduleObserver.php` | `saved()` → `scheduleFor()` khi publish; `cancelPending()` khi unpublish/delete |
| `app/Modules/Meeting/Observers/MeetingObserver.php` | `saved()` → `scheduleFor()` khi publish + start_time/status changed; `cancelPending()` khi cancel/delete |
| `app/Modules/TaskAssignment/Observers/TaskAssignmentItemObserver.php` | `saved()` → `scheduleFor()` khi document issued + end_at/status changed; `cancelPending()` khi done/delete |

### 9.4. Schedulers

| File | Vai trò |
|------|---------|
| `app/Services/Notification/Services/ScheduleReminderScheduler.php` | Xóa pending PRESET → tạo PRESET mới → tính remind_at cho CUSTOM |
| `app/Services/Notification/Services/MeetingReminderScheduler.php` | Xóa pending PRESET → tạo PRESET mới từ 3 event config → tính remind_at cho CUSTOM |
| `app/Services/Notification/Services/ReminderScheduler.php` | Xóa pending PRESET → tạo PRESET mới từ 3 event config → tính remind_at cho CUSTOM |

### 9.5. Cron

| File | Vai trò |
|------|---------|
| `app/Services/Notification/Console/ProcessRemindersCommand.php` | Quét pending reminders → phân biệt CUSTOM/PRESET channels → dispatch notification |

### 9.6. Resources (API response)

| File | Vai trò |
|------|---------|
| `app/Modules/Scheduling/Resources/ScheduleResource.php` | Filter CUSTOM reminders → `ScheduleReminderResource` |
| `app/Modules/Scheduling/Resources/ScheduleReminderResource.php` | Format reminder cho response |
| `app/Modules/Meeting/Resources/MeetingResource.php` | Filter CUSTOM reminders → `MeetingReminderResource` |
| `app/Modules/Meeting/Resources/MeetingReminderResource.php` | Format reminder cho response |
| `app/Modules/TaskAssignment/Resources/ItemResource.php` | Filter CUSTOM reminders → `TaskAssignmentReminderResource` |
| `app/Modules/TaskAssignment/Resources/TaskAssignmentReminderResource.php` | Format reminder cho response |

### 9.7. Migrations

| File | Mô tả |
|------|-------|
| `database/migrations/2026_06_09_000001_add_per_record_reminders_to_meeting.php` | Thêm `channels`, `source`, `remind_at` vào `meeting_reminders` |
| `database/migrations/2026_06_09_000002_add_per_record_reminders_to_task_assignment.php` | Thêm `channels`, `source` vào `task_assignment_reminders` |

---

## 10. FAQ

### FE cần làm gì để dùng per-record reminder?

1. Trên form tạo record, thêm section "Nhắc lịch" với nút "Thêm mốc nhắc"
2. Mỗi mốc nhắc: chọn `moment` (before/on/after), `offset_minutes` (số phút), `channels` (checkbox mail/zalo/fcm)
3. Gửi `reminders` trong body POST/PUT như format ở mục 3
4. Khi edit, nếu không gửi key `reminders` → hệ thống giữ nguyên reminders cũ
5. Resource trả về `reminders[]` chỉ chứa CUSTOM — FE hiển thị lại trong form edit

### Làm sao phân biệt PRESET và CUSTOM?

- Resource **chỉ trả CUSTOM** — FE không thấy PRESET, không cần phân biệt
- Trong DB, cột `source` = `PRESET` hoặc `CUSTOM`
- CUSTOM luôn có `channels` không null (từ user input)
- PRESET có `notification_schedule_id` trỏ tới config

### Điều gì xảy ra nếu admin vừa bật PRESET, user vừa thêm CUSTOM?

Cả 2 cùng tồn tại và cùng fire. Cùng 1 record có thể có PRESET nhắc trước 30p qua mail + CUSTOM nhắc trước 1h qua zalo. Không conflict.

### Reminder có tự động xóa khi record bị xóa không?

Có. Observer `deleted()` gọi `cancelPending()` — tất cả pending reminders được update `status='cancelled'`. Nếu record bị xóa cứng mà không qua Eloquent (vd: delete query trực tiếp), reminders sẽ bị cancel khi cron chạy (do FK record không tồn tại).

### Nếu user sửa start_time của meeting đã publish thì sao?

Observer phát hiện `wasChanged(['start_time'])` → gọi lại `scheduleFor()`:
- PRESET: xóa pending cũ, tạo lại từ config
- CUSTOM: tính lại `remind_at` từ start_time mới

### Nếu remind_at đã qua (trong quá khứ) thì sao?

Cron sẽ bắt được ngay lần chạy tiếp theo và fire. Reminder không bị bỏ lỡ trừ khi record đã bị cancel/done trước khi cron kịp chạy.
