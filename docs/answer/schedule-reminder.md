# Lịch công tác — Schedule Reminder (cấu hình nhắc lịch)

## Dữ liệu reminder trong response

Mỗi schedule có thể có 0-n reminder, trả về trong field `reminders`:

```json
{
  "reminders": [
    {
      "id": 15,
      "schedule_id": 42,
      "minutes_before": 30,
      "offset_minutes": 30,
      "channels": ["fcm", "mail"],
      "source": "CUSTOM",
      "reminder_type": "CUSTOM"
    }
  ]
}
```

| Field | Kiểu | Mô tả |
|---|---|---|
| `minutes_before` | int | Số phút nhắc trước thời điểm `date_time` của schedule |
| `offset_minutes` | int | Alias của `minutes_before` (backward-compatible) |
| `channels` | string[] | Danh sách kênh gửi thông báo |
| `source` | string | `CUSTOM` (người dùng tự chọn) hoặc `PRESET` (theo cấu hình mặc định) |
| `reminder_type` | string | Alias của `source` |

## Gửi reminder khi tạo/sửa schedule

### Request body

```json
{
  "reminders": [
    { "minutes_before": 30, "channels": ["fcm", "mail"] },
    { "minutes_before": 1440, "channels": ["zalo"] }
  ]
}
```

| Field | Bắt buộc | Ghi chú |
|---|---|---|
| `minutes_before` | Không | Số phút nhắc trước giờ diễn ra. Mặc định: `0` (nhắc ngay lúc bắt đầu) |
| `offset_minutes` | Không | Alias của `minutes_before`, FE có thể dùng thay thế |
| `channels` | Không | Mảng kênh. Mặc định: `[]`. Giá trị hợp lệ: `fcm`, `mail`, `zalo`, `zalo_zns`, `sms` |
| `source` | Không | `CUSTOM` hoặc `PRESET`. Mặc định: `CUSTOM` |
| `reminder_type` | Không | Alias của `source` |

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
    { "minutes_before": 15, "channels": ["fcm"] },
    { "minutes_before": 1440, "channels": ["fcm", "mail"] }
  ]
}
```

Giải thích:
- `minutes_before: 15` → gửi thông báo lúc 07:45 (15 phút trước 08:00)
- `minutes_before: 1440` → gửi thông báo 1 ngày trước (1440 phút = 24 giờ), lúc 08:00 ngày 09/06

## Các giá trị `minutes_before` thường dùng

| Phút | Ý nghĩa |
|---|---|
| `0` | Nhắc đúng giờ bắt đầu |
| `5` | Nhắc 5 phút trước |
| `15` | Nhắc 15 phút trước |
| `30` | Nhắc 30 phút trước |
| `60` | Nhắc 1 giờ trước |
| `120` | Nhắc 2 giờ trước |
| `1440` | Nhắc 1 ngày trước |
| `10080` | Nhắc 1 tuần trước |

## Luồng xử lý

1. FE gửi `reminders[]` trong body `POST` (tạo) hoặc `PUT|PATCH` (sửa)
2. Service xóa toàn bộ reminder cũ, tạo lại danh sách mới từ `reminders[]`
3. Cron job `ProcessRemindersCommand` quét `schedule_reminders`, tính `remind_at = schedule.date_time - minutes_before`, gửi qua các `channels` đã chọn
4. Reminder chỉ thực sự được gửi nếu schedule đang ở trạng thái `PUBLISHED`

## Lưu ý

- Khi sửa schedule (`PATCH`), nếu không gửi field `reminders` → giữ nguyên reminder cũ (không xóa)
- Khi gửi `reminders: []` (mảng rỗng) → xóa tất cả reminder
- `channels` được normalize về uppercase khi lưu DB, FE có thể gửi lowercase
- Kênh `fcm` = push notification qua Firebase Cloud Messaging
