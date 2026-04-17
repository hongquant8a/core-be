# API Nhật ký gửi thông báo (Admin) – Core

Xem lịch sử gửi thông báo toàn hệ thống: list, detail, stats. Dùng cho audit + dashboard.

**Auth:** Bearer token (Sanctum).
**Permissions:**
- `notifications.logs.index` — xem list + stats
- `notifications.logs.show` — xem detail

Super Admin + Admin auto nhận.

---

## 1. List notifications (paginated)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/notifications/logs` |
| **Permission** | `notifications.logs.index` |

### Query filters

| Param | Type | Mô tả |
|---|---|---|
| `user_id` | int | Filter theo user nhận |
| `event_key` | string | `document_issued`/`task_completed`/`task_confirmed`/`reminder_before`/`reminder_on`/`reminder_after` |
| `notifiable_type` | string | Class name entity (vd `App\Modules\TaskAssignment\Models\TaskAssignmentItem`) |
| `notifiable_id` | int | ID entity |
| `from_date` | date | Lọc từ ngày (YYYY-MM-DD) |
| `to_date` | date | Lọc đến ngày |
| `search` | string | Match trong title/body |
| `delivery_status` | string | `pending`/`sent`/`failed`/`skipped` — có ít nhất 1 delivery với status này |
| `channel` | string | `sms`/`mail`/`zalo`/`fcm` — có ít nhất 1 delivery qua channel này |
| `limit` | int | Mỗi trang, default 20 |
| `page` | int | Trang |

### Response

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "per_page": 20,
    "total": 120,
    "data": [
      {
        "id": 456,
        "user_id": 12,
        "user": { "id": 12, "name": "Nguyễn Văn A", "email": "a@example.com" },
        "event_key": "document_issued",
        "notifiable_type": "App\\Modules\\TaskAssignment\\Models\\TaskAssignmentItem",
        "notifiable_id": 42,
        "title": "Bạn được giao công việc mới",
        "body": "Công việc: Rà soát báo cáo tháng 4",
        "context": { "url": "/task-assignment-items/42" },
        "read_at": "2026-04-17T08:30:00.000000Z",
        "deliveries": [
          { "id": 900, "channel": "sms", "status": "sent", "message_id": "1", "sent_at": "2026-04-17T08:25:00.000000Z", "error_message": null },
          { "id": 901, "channel": "mail", "status": "sent", "message_id": null, "sent_at": "2026-04-17T08:25:12.000000Z", "error_message": null },
          { "id": 902, "channel": "fcm", "status": "failed", "error_message": "InvalidRegistration" }
        ],
        "created_at": "2026-04-17T08:24:50.000000Z",
        "updated_at": "2026-04-17T08:30:00.000000Z"
      }
    ]
  }
}
```

Order: `id DESC` (mới nhất trước).

---

## 2. Detail 1 notification

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/notifications/logs/{id}` |
| **Permission** | `notifications.logs.show` |

**Response:** giống 1 item trong list, kèm user relation + deliveries.

```json
{
  "success": true,
  "data": {
    "id": 456,
    "user": { "id": 12, "name": "Nguyễn Văn A", "email": "a@example.com" },
    "event_key": "document_issued",
    "deliveries": [...]
  }
}
```

---

## 3. Stats (dashboard)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/notifications/logs/stats` |
| **Permission** | `notifications.logs.index` |

Chấp nhận **cùng filter** như list — để tính stats trong phạm vi filter (vd stats trong tháng, stats của 1 user, v.v.).

### Response

```json
{
  "success": true,
  "data": {
    "total": 1234,
    "today": 42,
    "this_week": 300,
    "by_event": {
      "document_issued": 400,
      "task_completed": 300,
      "task_confirmed": 250,
      "reminder_before": 150,
      "reminder_on": 80,
      "reminder_after": 54
    },
    "by_status": {
      "sent": 2100,
      "failed": 50,
      "pending": 10,
      "skipped": 74
    },
    "by_channel": {
      "sms": 700,
      "mail": 900,
      "zalo": 100,
      "fcm": 534
    }
  }
}
```

Lưu ý:
- `total`/`today`/`this_week` = **số notification** (parent rows).
- `by_event` = breakdown notification theo event.
- `by_status`/`by_channel` = breakdown **delivery** (child rows) — nên tổng có thể lớn hơn `total` vì 1 notification có nhiều delivery.

---

## 4. Flow UI admin

### Trang "Nhật ký thông báo"

**Section 1 — Bộ lọc:**
```
[User ▾]  [Event ▾]  [Kênh ▾]  [Trạng thái ▾]  [Từ ngày] [Đến ngày]  [Tìm kiếm...]  [Lọc]
```

**Section 2 — Stats cards** (poll theo filter):
```
┌─────────────┬─────────────┬─────────────┐
│ Tổng: 1234  │ Hôm nay: 42 │ Tuần: 300   │
└─────────────┴─────────────┴─────────────┘

By Event (bar chart)    By Channel (pie)   By Status (pie)
```

**Section 3 — Table list:**
| Thời gian | User | Event | Tiêu đề | Channels (kết quả) | |
|---|---|---|---|---|---|
| 17/04 08:25 | Nguyễn Văn A | Văn bản ban hành | Bạn được giao... | SMS ✓ Mail ✓ FCM ✗ | [Chi tiết] |
| ... | | | | | |

Click "Chi tiết" → modal/page hiện đầy đủ deliveries + error messages cho từng channel.

---

## 5. Lưu ý

1. **Stats chạy cùng filter:** FE nên call stats + list song song với cùng params để số liệu đồng bộ.
2. **by_status/by_channel tính từ deliveries**, không phải notifications → tổng 2 cái này ≠ `total`.
3. **Không có endpoint delete** cho admin — log audit bất biến. Nếu cần dọn dẹp → cron truncate theo ngày (future).
4. **Permission phân tách `index` vs `show`** để phòng hờ case chỉ cho manager thấy list tóm tắt, không xem content chi tiết.
