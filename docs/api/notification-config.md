# API Cấu hình Notification (Admin) – Core

Quản lý cấu hình sự kiện + lịch nhắc cho từng module. **Module được bind vào URL path của module**, FE hoàn toàn không cần biết `module_key`.

**Auth:** Bearer token (Sanctum).

**Permissions:**
- `notifications.event-configs.index` / `.update`
- `notifications.schedules.index` / `.store` / `.update` / `.destroy`

Super Admin + Admin auto nhận.

---

## Pattern: Module-scoped URL

Mỗi module có notification sẽ có URL prefix riêng cho config. Backend middleware `notification.module:{key}` tự gán `module_key` vào request — controller chia sẻ cho tất cả module.

### Hiện có

| Module | URL prefix |
|---|---|
| Giao việc (TaskAssignment) | `/api/task-assignment/notification-config` |

Khi thêm module mới (vd News, Inventory), BE tạo route file + đăng ký URL prefix riêng. FE **không cần sửa gì** nếu chỉ hiển thị config nội bộ module — URL đã biết sẵn.

---

## Endpoints

### A. Endpoints chung (không scope module)

#### A.1. Test notification

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/notifications/test` |
| **Permission** | `notifications.test` |

Xem file [notification.md](notification.md).

#### A.2. Registry overview (optional)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/notifications/modules` |
| **Permission** | `notifications.event-configs.index` |

Liệt kê các module + events. Dùng cho **dashboard tổng quan** của admin nếu muốn xem toàn cục. FE các màn module không cần endpoint này.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "key": "task_assignment",
      "label": "Giao việc",
      "events": [
        { "key": "document_issued", "label": "Văn bản ban hành" },
        { "key": "task_completed",  "label": "Công việc báo cáo hoàn thành" },
        { "key": "task_confirmed",  "label": "Công việc được xác nhận" },
        { "key": "reminder_before", "label": "Nhắc trước hạn" },
        { "key": "reminder_on",     "label": "Nhắc đến hạn" },
        { "key": "reminder_after",  "label": "Nhắc quá hạn" }
      ]
    }
  ]
}
```

#### A.3. Update/Delete schedule theo ID

Sau khi tạo schedule qua module, update/delete dùng endpoint chung (id unique toàn bảng):

| Method | Path | Permission |
|---|---|---|
| PUT | `/api/notifications/schedules/{id}` | `notifications.schedules.update` |
| DELETE | `/api/notifications/schedules/{id}` | `notifications.schedules.destroy` |

---

### B. Endpoints module TaskAssignment

FE trong trang config của module TaskAssignment gọi 4 endpoint sau:

#### B.1. Danh sách event configs

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment/notification-config/event-configs` |
| **Permission** | `notifications.event-configs.index` |

**Response:** mảng 6 event config của module, order theo `event_key`.

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "module_key": "task_assignment",
      "event_key": "document_issued",
      "enabled": false,
      "channels": [],
      "created_at": "...",
      "updated_at": "..."
    },
    ...
  ]
}
```

#### B.2. Cập nhật event config

| | |
|---|---|
| **Method** | PUT |
| **Path** | `/api/task-assignment/notification-config/event-configs/{eventKey}` |
| **Permission** | `notifications.event-configs.update` |

**Path param `{eventKey}`:** một trong `document_issued`, `task_completed`, `task_confirmed`, `reminder_before`, `reminder_on`, `reminder_after`.

**Body:**
```json
{
  "enabled": true,
  "channels": ["sms", "mail", "fcm"]
}
```

| Field | Type | Required | Validation |
|---|---|---|---|
| `enabled` | boolean | ✅ | |
| `channels` | array | | Mỗi phần tử `sms`/`mail`/`zalo`/`fcm` |

#### B.3. Danh sách schedules của module

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment/notification-config/schedules` |
| **Permission** | `notifications.schedules.index` |

**Response:** mảng schedules thuộc module.

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "module_key": "task_assignment",
      "moment": "before",
      "offset_minutes": 1440,
      "channels": ["mail"],
      "enabled": true,
      "label": "Nhắc trước 1 ngày",
      "sort_order": 1,
      "created_at": "...",
      "updated_at": "..."
    },
    { "moment": "before", "offset_minutes": 120, "label": "Nhắc trước 2 giờ", "...": "..." },
    { "moment": "on", "offset_minutes": null, "label": "Đến hạn", "...": "..." },
    { "moment": "after", "offset_minutes": 1440, "label": "Trễ 1 ngày", "...": "..." }
  ]
}
```

Order: `sort_order` ASC → `id` ASC.

#### B.4. Tạo schedule mới trong module

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment/notification-config/schedules` |
| **Permission** | `notifications.schedules.store` |

Body **không chứa** `module_key` — BE tự gán.

```json
{
  "moment": "before",
  "offset_minutes": 180,
  "channels": ["sms", "mail"],
  "enabled": true,
  "label": "Nhắc trước 3 giờ",
  "sort_order": 5
}
```

| Field | Type | Required | Validation |
|---|---|---|---|
| `moment` | string | ✅ | `before` / `on` / `after` |
| `offset_minutes` | integer | | `>= 0`. Null khi `moment=on` |
| `channels` | array | ✅ | ≥ 1 phần tử |
| `enabled` | boolean | | Default `true` |
| `label` | string | ✅ | ≤ 255 ký tự |
| `sort_order` | integer | | |

**Response 201:** schedule vừa tạo.

---

## Flow UI

### Trang "Cấu hình thông báo" **trong module TaskAssignment**

- Hardcode/bind URL `/api/task-assignment/notification-config/*` trong component config page của module (FE module đã biết mình là TaskAssignment qua router/folder).
- 2 section:
  - **Sự kiện:** GET `/event-configs` → render bảng ma trận → PUT từng row khi save.
  - **Lịch nhắc:** GET `/schedules` → bảng CRUD → POST/PUT/DELETE.

### Mở rộng module mới

Khi thêm module News:
1. BE thêm case `NotificationModuleEnum::News = 'news'`
2. BE map events mới vào module
3. BE tạo `app/Modules/News/Routes/notification_config.php` (copy pattern TaskAssignment)
4. BE đăng ký prefix `/api/news/notification-config` trong `api.php`
5. FE module News gọi URL mới → xong

**FE không bao giờ thấy `module_key`** trong URL/body.

---

## Lưu ý quan trọng

1. **Channel toggle phụ thuộc Settings:** notification gửi cần channel bật trong Settings (`sms_enabled`, etc). Nếu channel ở event config nhưng channel bị tắt → gửi fail với `"<Channel> is disabled"`.

2. **Reminder fire logic:** khi reminder tới giờ, intersect `reminder_{moment}` event config channels ∩ `notification_schedules.channels`. Nếu event disabled → fallback schedule channels. Nếu intersect rỗng → cancel.

3. **Schedule update/delete dùng endpoint chung `/notifications/schedules/{id}`** (id unique toàn bảng, không cần module trong path).
