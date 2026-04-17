# Changelog FE — Notification Config refactor

**Ngày:** 2026-04-17
**Branch:** `feature/notification-events-reminders`
**Đối tượng:** FE team

Thay đổi cấu trúc cấu hình notification: **Schedule giờ là child của Event Config**. API endpoints + UI logic thay đổi. **Breaking** với bản cũ.

---

## 1. Khái niệm thay đổi

### Trước

- Event có channels + enabled
- Schedule (lịch nhắc) flat, có channels + enabled riêng
- Reminder logic: intersect channels event ∩ channels schedule (confusing)

### Sau

```
Event (enabled toggle, không còn channels)
  └── Schedule[] (channels ở đây)
       ├── Non-reminder: 1 schedule instant (moment=null, offset=null)
       └── Reminder: N schedule (moment + offset_minutes + channels)
```

- **Non-reminder event** (Văn bản được ban hành, Công việc báo cáo hoàn thành, Công việc được xác nhận): có **1 schedule instant** — FE edit channels inline trên row event.
- **Reminder event** (Nhắc trước hạn, Nhắc đến hạn, Nhắc quá hạn): có **N schedule** — FE render section riêng CRUD.

---

## 2. API changes

### 2.1. Module registry — thêm `is_reminder`

`GET /api/notifications/modules`

```diff
{
  "key": "document_issued",
  "label": "Văn bản được ban hành",
+ "is_reminder": false
}
```

FE dùng `is_reminder` để phân nhánh UI (channel inline vs CRUD schedules).

### 2.2. Label đổi

- `document_issued`: `"Văn bản ban hành"` → **`"Văn bản được ban hành"`**

### 2.3. Event config list — kèm schedules eager-load

`GET /api/task-assignment/notification-config/event-configs`

**Trước:**
```json
{ "id": 1, "event_key": "document_issued", "enabled": false, "channels": [] }
```

**Sau:**
```json
{
  "id": 1,
  "event_key": "document_issued",
  "enabled": false,
  "schedules": [
    { "id": 9, "moment": null, "offset_minutes": null, "channels": [], "label": "Gửi tức thì", "sort_order": 0 }
  ]
}
```

Event không còn `channels`. Lấy channels qua `schedules[0].channels` (non-reminder) hoặc iterate schedules (reminder).

### 2.4. Event config update — body đơn giản hóa

`PUT /api/task-assignment/notification-config/event-configs/{eventKey}`

**Trước:**
```json
{ "enabled": true, "channels": ["sms","mail"] }
```

**Sau:**
```json
{ "enabled": true }
```

Chỉ còn `enabled`. **Channels không còn ở đây** — muốn đổi channels → gọi `PUT /schedules/{id}`.

### 2.5. Schedules endpoints — nested under event

**Trước:**
- `GET /api/task-assignment/notification-config/schedules` (flat, tất cả schedule của module)
- `POST /api/task-assignment/notification-config/schedules` (body chứa `moment`)

**Sau:**
- `GET /api/task-assignment/notification-config/event-configs/{eventKey}/schedules` — list theo event
- `POST /api/task-assignment/notification-config/event-configs/{eventKey}/schedules` — tạo trong event
- `PUT /api/notifications/schedules/{id}` — update (không đổi)
- `DELETE /api/notifications/schedules/{id}` — delete (không đổi)

### 2.6. Schedule body

**Trước:**
```json
{
  "moment": "before",
  "offset_minutes": 1440,
  "channels": ["mail"],
  "enabled": true,
  "label": "Nhắc trước 1 ngày",
  "sort_order": 1
}
```

**Sau:**
```json
{
  "moment": "before",
  "offset_minutes": 1440,
  "channels": ["mail"],
  "label": "Nhắc trước 1 ngày",
  "sort_order": 1
}
```

- Bỏ `enabled` (không có — xóa schedule nếu không muốn nhắc).
- `moment` + `offset_minutes` có thể nullable (cho non-reminder event là null).
- `channels` giờ ở đây (thay vì event).

---

## 3. UI impact — 2 loại event render khác nhau

### Non-reminder event (is_reminder=false)

```
┌─────────────────────────────────────────────────────────────────┐
│ Văn bản được ban hành          [● Toggle]                      │
│ document_issued                                                 │
│                                                                 │
│   Kênh gửi: ☑ SMS  ☑ Email  ☐ Zalo  ☑ FCM                      │
└─────────────────────────────────────────────────────────────────┘
```

- Hiển thị toggle `enabled` + checkboxes channel ngay trên row.
- Toggle → `PUT /event-configs/{eventKey} { enabled: ... }`.
- Channels → `PUT /schedules/{schedules[0].id} { channels: [...] }`.

### Reminder event (is_reminder=true)

```
┌─────────────────────────────────────────────────────────────────┐
│ Nhắc trước hạn                 [● Toggle]   [Cấu hình lịch →] │
│ reminder_before                                                 │
└─────────────────────────────────────────────────────────────────┘
```

- Chỉ toggle `enabled` + button mở section cấu hình lịch.
- **Không có** checkbox channel inline.
- Click "Cấu hình lịch" → hiển thị bảng schedules riêng:

```
┌───────────────────────────────────────────────────────────────────┐
│ Lịch nhắc trước hạn                          [+ Thêm lịch]      │
├────────────────────┬──────────┬────────┬─────────────┬────────────┤
│ Nhãn               │ Thời điểm│ Khoảng │ Kênh gửi    │ Hành động │
├────────────────────┼──────────┼────────┼─────────────┼────────────┤
│ Nhắc trước 1 ngày  │ Trước hạn│ 1 ngày │ [Email]     │ Edit / Del │
│ Nhắc trước 2 giờ   │ Trước hạn│ 2 giờ  │ [SMS, FCM]  │ Edit / Del │
└────────────────────┴──────────┴────────┴─────────────┴────────────┘
```

- List: `GET /event-configs/reminder_before/schedules`
- Add: `POST /event-configs/reminder_before/schedules { moment, offset_minutes, channels, label, sort_order }`
- Edit: `PUT /schedules/{id}`
- Delete: `DELETE /schedules/{id}`

---

## 4. Migration FE (từ bản cũ)

1. **Đổi list event response mapping:** đọc `schedules[]` thay vì `channels`.
2. **Đổi `PUT /event-configs` payload:** bỏ `channels`.
3. **Đổi UI event row:**
   - Nếu `is_reminder=false`: render channel checkboxes (bind vào `schedules[0].channels`).
   - Nếu `is_reminder=true`: render button "Cấu hình lịch" thay cho checkboxes.
4. **Tách section Lịch nhắc:** không còn global — mỗi reminder event có bảng schedules riêng.
5. **Bỏ toggle `enabled` trên mỗi schedule row** — xóa schedule nếu không muốn.
6. **Rename label** ở mọi chỗ hiển thị: `"Văn bản ban hành"` → `"Văn bản được ban hành"` (hoặc lấy từ API).

---

## 5. Checklist FE

- [ ] Gọi `/modules` → đọc `is_reminder` để quyết định UI
- [ ] List event: đọc `schedules[]` thay vì `channels`
- [ ] Update event: payload chỉ còn `{ enabled }`
- [ ] Row non-reminder: channel checkboxes bind `schedules[0]` → gọi `PUT /schedules/{id}`
- [ ] Row reminder: button mở bảng schedules
- [ ] Bảng schedules reminder: CRUD qua nested endpoint
- [ ] Bỏ toggle `enabled` trên schedule row
- [ ] Cập nhật label `document_issued`

---

## 6. Ví dụ flow hoàn chỉnh

### Admin bật notification SMS + Email cho "Văn bản được ban hành"

1. `PUT /api/task-assignment/notification-config/event-configs/document_issued` body `{ "enabled": true }`
2. `PUT /api/notifications/schedules/9` (id schedule instant) body `{ "channels": ["sms", "mail"] }`

### Admin tạo rule "Nhắc trước 3 giờ" cho reminder_before

1. Đảm bảo `PUT /event-configs/reminder_before { enabled: true }` đã được bật
2. `POST /api/task-assignment/notification-config/event-configs/reminder_before/schedules` body:
   ```json
   {
     "moment": "before",
     "offset_minutes": 180,
     "channels": ["sms", "fcm"],
     "label": "Nhắc trước 3 giờ",
     "sort_order": 3
   }
   ```

---

## 7. Tham khảo

- API docs: [docs/api/notification-config.md](../api/notification-config.md)
- Guide cho module mới: [docs/guides/notification-new-module-integration.md](../guides/notification-new-module-integration.md)
