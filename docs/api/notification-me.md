# API Thông báo cho User – Core

Notification list cho user đang đăng nhập (inbox trong app). Chỉ thao tác được notification của chính user hiện tại — không cần permission, chỉ cần auth.

**Base path:** `/api/notifications/me`

**Auth:**
- Bearer token (Sanctum) — required.
- Header `X-Organization-Id: {id}` — required. Xác định tổ chức đang làm việc (Spatie team context).

**Scoping:** mọi endpoint filter theo `user_id = auth()->id()` **AND** `organization_id = X-Organization-Id`. User không thể xem/sửa notification của người khác hoặc của org khác. Đổi org (đổi header) → thấy inbox khác, badge counter khác.

Thiếu header `X-Organization-Id` → 422 validation error từ middleware `SetPermissionsTeamId`.

---

## Notification shape

```json
{
  "id": 123,
  "user_id": 5,
  "organization_id": 1,
  "event_key": "document_issued",
  "notifiable_type": "App\\Modules\\TaskAssignment\\Models\\TaskAssignmentItem",
  "notifiable_id": 42,
  "title": "Bạn được giao công việc mới",
  "body": "Công việc: Rà soát báo cáo tháng 4",
  "context": {
    "url": "/task-assignment-items/42",
    "document_id": 10
  },
  "read_at": null,
  "created_at": "2026-04-17T02:15:30.000000Z",
  "updated_at": "2026-04-17T02:15:30.000000Z"
}
```

| Field | Mô tả |
|---|---|
| `organization_id` | Tổ chức (Spatie team) sở hữu notification. Luôn khớp với `X-Organization-Id` của request hiện tại |
| `event_key` | 1 trong 6: `document_issued`, `task_completed`, `task_confirmed`, `reminder_before`, `reminder_on`, `reminder_after` |
| `notifiable_type` / `notifiable_id` | Entity liên quan (Phase B/C đều là `TaskAssignmentItem`) |
| `title` | Tiêu đề ngắn hiển thị trong list |
| `body` | Dòng mô tả ngắn |
| `context` | JSON — thường chứa `url` (link navigate khi click), `type`, metadata khác |
| `read_at` | Null = chưa đọc; datetime = thời điểm user đọc |

---

## Endpoints

### 1. Danh sách notification của user

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/notifications/me` |

**Query params:**

| Param | Type | Mô tả |
|---|---|---|
| `read` | boolean | `true` = chỉ đã đọc; `false` = chỉ chưa đọc; bỏ trống = tất cả |
| `event_key` | string | Filter theo event cụ thể (vd `document_issued`) |
| `limit` | integer | Số record mỗi trang, mặc định 20 |
| `page` | integer | Trang, mặc định 1 |

**Response (paginated):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      { "id": 123, "event_key": "document_issued", "title": "...", "body": "...", "context": {...}, "read_at": null, ... },
      { "id": 122, "event_key": "reminder_before", "title": "...", "body": "...", ... }
    ],
    "first_page_url": "...",
    "from": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 45
  }
}
```

Order: `id DESC` (mới nhất trước).

### 2. Số notification chưa đọc (badge)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/notifications/me/unread-count` |

**Response:**
```json
{
  "success": true,
  "data": { "unread_count": 7 }
}
```

FE dùng làm badge counter trên icon chuông. Poll định kỳ (vd 30s/lần) hoặc realtime qua FCM/SSE.

### 3. Đánh dấu 1 notification đã đọc

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/notifications/me/{id}/read` |

**Response:** notification sau update (có `read_at` được set thời điểm hiện tại).

Idempotent — gọi nhiều lần không lỗi, `read_at` chỉ set lần đầu.

### 4. Đánh dấu tất cả đã đọc

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/notifications/me/read-all` |

**Response:**
```json
{
  "success": true,
  "data": { "updated": 5 },
  "message": "Đã đánh dấu tất cả là đã đọc"
}
```

`updated` = số record được set `read_at` (không tính các record đã đọc trước đó).

### 5. Xóa notification

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/notifications/me/{id}` |

Hard delete. Cascade sẽ xóa các `notification_deliveries` liên quan.

**Response:**
```json
{
  "success": true,
  "message": "Đã xóa thông báo"
}
```

**404:** nếu notification không thuộc về user hiện tại (hoặc không tồn tại).

---

## Flow gợi ý UI

### Icon chuông trên header

```
┌──────────────────────────┐
│  🔔 (7)  ← badge number │
└──────────────────────────┘
```

- Poll `GET /me/unread-count` mỗi 30s để update badge
- Click icon → dropdown hiện 10 notification mới nhất (gọi `GET /me?limit=10`)
- Mỗi item click → navigate tới `context.url` (nếu có) + gọi `PATCH /me/{id}/read`
- Footer dropdown: "Xem tất cả" → trang full list

### Trang notification list

- Paginated, filter theo `read` (tab: Tất cả / Chưa đọc / Đã đọc)
- Mỗi item có nút Đánh dấu đã đọc (nếu chưa đọc) + Xóa
- Header: nút "Đánh dấu tất cả đã đọc" (gọi `PATCH /me/read-all`)

### Click → navigate

Với mỗi `event_key`, `context.url` chỉ đến trang tương ứng:

| event_key | context.url ví dụ | Ý nghĩa |
|---|---|---|
| `document_issued` | `/task-assignment-items/42` | Mở chi tiết công việc được giao |
| `task_completed` | `/task-assignment-items/42` | Manager vào xem + confirm |
| `task_confirmed` | `/task-assignment-items/42` | Assignee xem xác nhận |
| `reminder_before/on/after` | `/task-assignment-items/42` | Xem công việc để làm/giải trình |

FE redirect user tới `context.url` khi click; đồng thời mark read.

---

## Icon / màu sắc gợi ý theo event_key

| event_key | Icon | Màu |
|---|---|---|
| `document_issued` | 📄 (document) | Xanh (info) |
| `task_completed` | ✅ (check) | Cam (warning — cần action) |
| `task_confirmed` | ✔️ (done) | Xanh lá (success) |
| `reminder_before` | ⏰ (clock) | Vàng (warning) |
| `reminder_on` | 🔔 (bell) | Cam |
| `reminder_after` | ⚠️ (warning) | Đỏ (danger) |

---

## Lưu ý

1. **Multi-org:** user làm việc với nhiều tổ chức thì phải đổi header `X-Organization-Id` → inbox + badge reset theo org đó. Không có endpoint "all orgs". FE nếu hiện tổng notification cross-org phải query từng org riêng.

2. **Realtime (optional):** nếu FE đã setup FCM service worker, notification sẽ tự đẩy push tới browser — FE nghe event từ service worker, có thể increment badge counter ngay mà không cần poll. Server đã scope push token theo user nên FE chỉ cần update badge của org đang active.

3. **Pagination:** luôn dùng pagination, không load hết. User có thể có hàng trăm notification cũ.

4. **Context structure không cố định:** mỗi event có thể thêm field vào `context`. FE nên:
   - Đọc `context.url` nếu có → navigate
   - Fallback về format text từ `title` + `body` nếu không có URL
   - Không assume field khác tồn tại
