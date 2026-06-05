# Lịch công tác — Schedule Reminder (cấu hình nhắc lịch)

## Mô hình 3 tầng nhắc lịch

```
[Tầng 1: Hệ thống] → Mở/Khóa kênh (Zalo, SMS, Email, FCM, ...)
       ↓
[Tầng 2: Module] → Cấu hình template nhắc cho toàn Module (notification_schedules)
       ↓
[Tầng 3: Từng Schedule] → Bản ghi schedule_reminders, compute remind_at khi publish
```

Pattern đồng bộ với TaskAssignment: reminder được tính `remind_at` khi publish, cron `ProcessRemindersCommand` poll và fire.

**Nguyên tắc:** Nếu cả tầng 2 (module) và tầng 3 (per-schedule) cùng được cấu hình → **cả 2 cùng bắn**, không loại trừ nhau.

## 4 loại thời điểm nhắc (moment)

| moment | Ý nghĩa | `offset_minutes` | Có trong preset? |
|--------|---------|-------------------|-------------------|
| `immediate` | Bắn **ngay khi schedule được duyệt** (publish) | Không dùng | Không |
| `before` | Nhắc **trước** thời gian sự kiện N phút | Số phút trước | Có |
| `on` | Nhắc **đúng** thời điểm sự kiện | Không dùng | Có |
| `after` | Nhắc **sau** thời gian sự kiện N phút | Số phút sau | Có |

- `immediate` chỉ dùng ở tầng per-schedule — khi publish, `remind_at = now()`, cron bắn ngay
- Module-level instant notification (qua `schedule_published` event) và per-schedule `immediate` reminder cùng bắn nếu cả 2 được cấu hình

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
| `source` | string | `CUSTOM` (tự chọn) hoặc `PRESET` (theo mẫu) |
| `reminder_type` | string | Alias của `source` |
| `minutes_before` | int | **Deprecated** — dùng `offset_minutes` |

## Gửi reminder khi tạo/sửa schedule

### Request body

```json
{
  "reminders": [
    { "moment": "immediate", "channels": ["fcm"] },
    { "moment": "before", "offset_minutes": 30, "channels": ["fcm"] },
    { "moment": "on", "channels": ["zalo"] },
    { "moment": "after", "offset_minutes": 60, "channels": ["mail"] }
  ]
}
```

| Field | Bắt buộc | Mặc định | Ghi chú |
|-------|----------|----------|---------|
| `moment` | Không | `"before"` | `immediate`, `before`, `on`, `after` |
| `offset_minutes` | Không | `0` | Chỉ có nghĩa với `before` và `after` |
| `channels` | Không | `[]` | `fcm`, `mail`, `zalo`, `zalo_zns`, `sms` |
| `source` | Không | `"CUSTOM"` | `CUSTOM` hoặc `PRESET` |

### Ví dụ

```json
// POST /api/schedules
{
  "module_type": "EXECUTIVE",
  "content": "Họp giao ban",
  "date_time": "2026-06-10T08:00:00",
  "session": "S",
  "location": "Phòng họp 1",
  "reminders": [
    { "moment": "immediate", "channels": ["fcm"] },
    { "moment": "before", "offset_minutes": 30, "channels": ["fcm", "mail"] },
    { "moment": "on", "channels": ["zalo"] },
    { "moment": "after", "offset_minutes": 120, "channels": ["mail"] }
  ]
}
```

- `immediate` → bắn ngay khi schedule được duyệt (cùng lúc với module-level event nếu có)
- `before + 30` → gửi lúc 07:30 (30 phút trước 08:00)
- `on` → gửi đúng 08:00
- `after + 120` → gửi lúc 10:00 (2 giờ sau khi bắt đầu)

## Luồng xử lý

1. FE gửi `reminders[]` trong body `POST`/`PUT`/`PATCH`
2. `ScheduleService::syncReminders()` xóa reminder cũ, tạo mới
3. Khi schedule được publish (Observer `saved`):
   - Event `SchedulePublished` → gửi thông báo tức thời theo module-level config
   - `ScheduleReminderScheduler::scheduleFor()` tính `remind_at`:
     - `immediate` → `remind_at = now()`
     - `before`/`on`/`after` → tính từ `date_time` + `moment` + `offset_minutes`
   - Set `status = pending`
4. Khi schedule bị cancel → `cancelPending()` set `status = cancelled`
5. Khi schedule được update → `cancelPending()` + `scheduleFor()` (re-schedule)
6. Cron `notifications:process-reminders` poll `remind_at <= now()` và fire qua `NotificationDispatcher`
7. Sau khi fire → `status = fired`, `fired_at = now()`

## So sánh với TaskAssignment

| | TaskAssignment | Scheduling |
|---|---|---|
| Bảng reminder | `task_assignment_reminders` | `schedule_reminders` |
| Cột thời điểm | `moment` (before/on/after) | `moment` (immediate/before/on/after) |
| Cột thời gian fire | `remind_at` | `remind_at` |
| Cột trạng thái | `status` (pending/fired/cancelled) | `status` (pending/fired/cancelled) |
| Xử lý | Cron poll `remind_at <= now()` | Cron poll `remind_at <= now()` |
| Kênh | Từ `notification_schedules` | Lưu trực tiếp trên reminder |
| immediate | Không có | Có (per-schedule, bắn cùng publish) |

## Lưu ý

- `channels` được normalize về UPPERCASE khi lưu DB, FE có thể gửi lowercase
- Kênh `fcm` = push notification qua Firebase Cloud Messaging
- Không gửi `reminders` → giữ nguyên reminder cũ
- Gửi `reminders: []` → xóa tất cả reminder
- `preset` không có `immediate` — immediate chỉ tồn tại ở per-schedule và module-level event config
