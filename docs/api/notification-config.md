# API Cấu hình Notification (Admin) – Core

> Cập nhật lần cuối: 15:10:25 15/07/2026 — bổ sung event `task_assigned` (module task_assignment thực tế có 7 event, không phải 6) và channel `zalo_zns` (đã có trong validation nhưng thiếu trong doc gốc).

Cấu hình notification **theo từng module**. Schedule là child của Event — channels nằm ở schedule. Event chỉ có toggle `enabled`.

**Auth:** Bearer token (Sanctum).
**Permissions:** `notifications.event-configs.{index,update}`, `notifications.schedules.{index,store,update,destroy}`. Super Admin + Admin auto nhận.

---

## Model hierarchy

```
NotificationEventConfig (1 per event per module)
  ├── enabled (bool)
  └── schedules[] (child)
       ├── moment: before|on|after | null
       ├── offset_minutes: int | null
       ├── channels: ['sms','mail','zalo','zalo_zns','fcm']  (telegram chưa mở qua UI cấu hình này)
       └── label, sort_order
```

### 2 loại event

| Loại | Event keys | Cấu trúc schedule |
|---|---|---|
| **Non-reminder** (fire tức thì khi trigger) | `task_assigned`, `document_issued`, `task_completed`, `task_confirmed` | **1 schedule duy nhất** với `moment=null`, `offset=null` (instant). FE chỉ edit channels. |
| **Reminder** (fire theo lịch deadline) | `reminder_before`, `reminder_on`, `reminder_after` | **N schedule** với `moment` + `offset_minutes` + channels. FE CRUD được. |

> ⚠️ `document_issued` hiện có config nhưng listener xử lý gửi tin đang bị comment (dead code) — bật `enabled=true` cho event này chưa có tác dụng gửi thông báo thực tế.

### Resolve channels khi fire

- Non-reminder: listener load event_config → lấy channels từ schedule instant duy nhất.
- Reminder: cron process reminder row → reminder.schedule → channels.
- Nếu event.enabled=false hoặc schedule.channels=[] → không gửi.

---

## Module registry

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/notifications/modules` |
| **Permission** | `notifications.event-configs.index` |

```json
{
  "success": true,
  "data": [
    {
      "key": "task_assignment",
      "label": "Giao việc",
      "events": [
        { "key": "task_assigned",    "label": "Được giao việc mới", "is_reminder": false },
        { "key": "document_issued",  "label": "Văn bản được ban hành", "is_reminder": false },
        { "key": "task_completed",   "label": "Công việc báo cáo hoàn thành", "is_reminder": false },
        { "key": "task_confirmed",   "label": "Công việc được xác nhận", "is_reminder": false },
        { "key": "reminder_before",  "label": "Nhắc trước hạn", "is_reminder": true },
        { "key": "reminder_on",      "label": "Nhắc đến hạn", "is_reminder": true },
        { "key": "reminder_after",   "label": "Nhắc quá hạn", "is_reminder": true }
      ]
    }
  ]
}
```

`is_reminder` giúp FE biết event cần render UI CRUD schedules (reminder) hay chỉ edit channels inline (non-reminder).

---

## Event configs (module-scoped)

### List

`GET /api/task-assignment/notification-config/event-configs`

Response — kèm schedules eager-load:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "module_key": "task_assignment",
      "event_key": "document_issued",
      "enabled": false,
      "schedules": [
        {
          "id": 9,
          "notification_event_config_id": 1,
          "moment": null,
          "offset_minutes": null,
          "channels": [],
          "label": "Gửi tức thì",
          "sort_order": 0
        }
      ],
      "created_at": "...",
      "updated_at": "..."
    },
    {
      "id": 4,
      "event_key": "reminder_before",
      "enabled": false,
      "schedules": [
        { "id": 12, "moment": "before", "offset_minutes": 1440, "channels": [], "label": "Nhắc trước 1 ngày" },
        { "id": 13, "moment": "before", "offset_minutes": 120, "channels": [], "label": "Nhắc trước 2 giờ" }
      ]
    }
  ]
}
```

### Update (toggle enabled)

`PUT /api/task-assignment/notification-config/event-configs/{eventKey}`

Body:
```json
{ "enabled": true }
```

Chỉ 1 field `enabled`. Channels/schedules quản lý qua endpoint schedules.

---

## Schedules (nested under event)

### List schedules của 1 event

`GET /api/task-assignment/notification-config/event-configs/{eventKey}/schedules`

### Create schedule trong event

`POST /api/task-assignment/notification-config/event-configs/{eventKey}/schedules`

**Non-reminder event:** chỉ cần `label` + `channels` (BE tự force `moment=null`, `offset_minutes=null`). Thường không cần tạo thêm — đã có sẵn 1 schedule instant khi seed.
```json
{ "label": "Gửi tức thì (override)", "channels": ["sms","mail"] }
```

**Reminder event:** cần `moment` + `offset_minutes` + `channels`.
```json
{
  "moment": "before",
  "offset_minutes": 180,
  "channels": ["sms","mail"],
  "label": "Nhắc trước 3 giờ",
  "sort_order": 5
}
```

| Field | Type | Required | Note |
|---|---|---|---|
| `moment` | string | | `before`/`on`/`after` (chỉ cho reminder; non-reminder bị BE reset null) |
| `offset_minutes` | integer | | `>= 0` (chỉ dùng với `before`/`after`) |
| `channels` | array | | `sms`/`mail`/`zalo`/`zalo_zns`/`fcm` (validation: `in:sms,mail,zalo,zalo_zns,fcm`; `telegram` chưa được cho phép qua endpoint này) |
| `label` | string | ✅ | ≤ 255 ký tự |
| `sort_order` | integer | | |

### Update schedule

`PUT /api/notifications/schedules/{id}` (endpoint chung, id unique toàn bảng)

Body partial:
```json
{ "channels": ["sms","mail","fcm"] }
```

### Delete schedule

`DELETE /api/notifications/schedules/{id}`

---

## Flow UI (trong module TaskAssignment)

### Section 1: "Sự kiện kích hoạt"

Render các event rows. UI khác nhau theo `is_reminder`:

**Non-reminder event** (vd Văn bản được ban hành):
```
Văn bản được ban hành    [Toggle enabled]    [Channels: ☐SMS ☐Email ☐Zalo ☐Zalo ZNS ☐FCM]
```
Channel checkboxes edit **inline** — save → `PUT /schedules/{instant_schedule_id}` với body `{ channels: [...] }`.

**Reminder event** (vd Nhắc trước hạn):
```
Nhắc trước hạn           [Toggle enabled]    [Cấu hình lịch →]
```
Không có channel checkbox inline. Click "Cấu hình lịch" → mở Section 2 cho event này.

### Section 2: "Lịch nhắc" (CRUD cho reminder event đang chọn)

Bảng N schedules:
| Label | Moment | Offset | Channels | Actions |
|---|---|---|---|---|
| Nhắc trước 1 ngày | Trước hạn | 1440 phút | [mail] | [Edit][Del] |
| Nhắc trước 2 giờ | Trước hạn | 120 phút | [sms, fcm] | [Edit][Del] |
| [+ Thêm lịch] | | | | |

CRUD endpoints đã nêu ở trên.

---

## Lưu ý

1. Non-reminder event được seed với 1 schedule instant `channels=[]`. Admin chỉ cần bật `enabled` + chọn channels → notification hoạt động.
2. Xóa event_config → cascade xóa schedules (FK cascadeOnDelete).
3. Nếu delete schedule duy nhất của non-reminder event → không có channel để gửi → notification không fire. Admin phải tạo lại schedule nếu xóa nhầm.
4. Channel toggle trong Settings (`sms_enabled`, etc.) vẫn là gate toàn cục — dù schedule có channels, channel disabled trong Settings sẽ fail gửi.
