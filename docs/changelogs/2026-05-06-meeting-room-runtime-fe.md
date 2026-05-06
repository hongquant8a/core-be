# 2026-05-06 — Meeting Room Runtime (FE)

Tổng hợp các thay đổi BE module phòng họp không giấy trong sprint này. **FE cần update theo các point dưới**.

## 🔴 BREAKING

### 1. Bulk-delete đổi sang HTTP `DELETE` (toàn hệ thống)
27 endpoint `*/bulk-delete` đổi từ `POST` → `DELETE`. Body giữ nguyên `{ ids: [...] }`.

```js
// Cũ
api.post('/meetings/bulk-delete', { ids })
// Mới
api.delete('/meetings/bulk-delete', { data: { ids } })  // axios cần `data`
```

Áp dụng cho: meetings, meeting-agendas, meeting-documents, meeting-participants, meeting-vote-topics/responses, meeting-attendances, meeting-attendees, meeting-attendee-groups, meeting-types/locations/document-types, meeting-conclusions, meeting-personal-notes, meeting/notification-config/logs, task-assignment/notification-config/logs.

### 2. Meeting Document — bỏ `view_count`, thêm endpoint download
- Field `view_count` của `meeting_documents` đã drop (không track view per-doc nữa).
- Field `download_count` giữ — tăng qua endpoint trung gian:
    - `GET /api/meeting-documents/{id}/download` (auth, cần token)
    - `GET /api/meeting-documents/public/{id}/download` (public, không cần token)
- BE redirect 302 + `Cache-Control: no-store`.
- FE đổi nút "⬇ Tải" trỏ tới endpoint download thay vì `file_url` trực tiếp:
```html
<a :href="`/api/meeting-documents/public/${doc.id}/download`" download>
```
- Nút "👁 Xem" vẫn dùng `file_url` (mở tab mới, không count).

### 3. Meeting Document — bỏ `status` field
`status` (draft/published) gỡ hoàn toàn. Tài liệu chỉ dùng `is_public` để hiện/ẩn ở trang công khai.
- Bỏ filter `?status=` trong list
- Bỏ cột status ở UI
- Bỏ 2 endpoint `PATCH /{id}/status` + `PATCH /bulk-status` (không còn tồn tại — sẽ 404)
- CASL: gỡ ability `meeting-documents.changeStatus` + `bulkUpdateStatus`

### 4. Meeting Personal Note — auto-derive participant + ownership scope
- `POST /api/meeting-personal-notes` **không gửi `meeting_participant_id`** nữa. BE auto-derive từ `auth()->id()`.
```js
// Cũ
api.post('/meeting-personal-notes', { meeting_id, meeting_participant_id, content })
// Mới
api.post('/meeting-personal-notes', { meeting_id, content })
```
- index/show/update/destroy/bulkDestroy/reorder: **chỉ trả/sửa note của chính user**. Cố truy cập note người khác → 404.

### 5. Meeting Discussion Registration — auto-derive + ownership + bỏ bulkDestroy
- `POST /api/meeting-discussion-registrations` **không gửi `meeting_participant_id`** nữa. BE auto-derive.
- update/destroy: chỉ owner đại biểu sửa/xóa được đăng ký của mình.
- ❌ **Endpoint `DELETE /api/meeting-discussion-registrations/bulk-delete` ĐÃ XÓA** — không có bulk delete cho đăng ký thảo luận/chất vấn.

## 🟢 NEW ENDPOINT

### 6. `/api/meetings/public/stats` — Stats công khai
Citizen-facing, không cần auth. Trả phase derived từ `start_time/end_time`:
```json
{ "total": 27, "upcoming": 5, "in_progress": 1, "finished": 21 }
```

### 7. `/api/meetings/public` — auth-optional union (trang chung)
Không phải chỉ guest. Có token → trả thêm meeting user là chair/operator/participant. Trang FE chung dùng endpoint này:
- Guest → chỉ public meetings
- Auth → public meetings + meetings user tham gia
- Admin scope (mọi meeting org) → trang riêng `/api/meetings`

`/api/meetings/public/{id}` cũng auth-optional theo cùng quy tắc.

### 8. Auto count view meeting
Mỗi `GET /api/meetings/(public/){id}` 2xx tăng `meeting.view_count` + log `meeting_views`. **Không dedupe** — F5 spam vẫn tăng.

### 9. `/api/user` (`/me`) trả thêm 2 field
```json
{
  "user": {...},
  "current_organization_id": 1,             // mới
  "available_organizations": [...],          // mới
  "roles": [...],
  "permissions": [...],
  "abilities": [...]
}
```
FE F5 dùng để sync `localStorage.orgId` + render org switcher mà không cần endpoint riêng.

## 🟡 BEHAVIOR CHANGE

### 10. Visibility documents theo participation
Trên cả `/api/meetings/{id}` (auth) + `/api/meetings/public/{id}`:
- User là chair/operator/participant của meeting → preload **tất cả** docs
- User khác (kể cả admin tổ chức không tham gia) → preload **chỉ** docs `is_public=true`

Áp dụng cho cả `/api/meeting-documents` + `/api/meeting-documents/public` list endpoints.

### 11. `is_public` AND logic giữ nguyên
Citizen thấy doc khi **CẢ 2** `meeting.is_public` + `document.is_public` = true. Doc nội bộ trong public meeting → vẫn ẩn với citizen.

### 12. Meeting publish (`PATCH /{id}/status` → published)
- Tạo invitations cho cả chủ trì + thư ký (FK trên meeting), không chỉ participants.
- Notification dispatch cho 4 channel: mail, sms, **zalo (mới)**, fcm.

## 🛠 INFRASTRUCTURE

### 13. Auth API trả 401 JSON thay vì redirect login
Browser hit URL auth không có token → trước trả 500 `RouteNotFoundException`, giờ trả:
```json
{ "success": false, "message": "Unauthenticated.", "code": "UNAUTHENTICATED" }
```
HTTP 401. FE handle qua axios interceptor.

## 📚 DOC

- [docs/api/meeting-room-fe.md](../api/meeting-room-fe.md) — API tham chiếu cho FE phòng họp (organize theo screen/tab)
- [docs/answer/meeting-runtime-design.md](../answer/meeting-runtime-design.md) — Design doc tổng thể (tab visibility, role mapping, real-time strategy)
- [docs/api/meeting-runtime.md](../api/meeting-runtime.md) — API spec đầy đủ (organize theo resource)

## ⚠️ Sprint 1 sắp tới (chưa ship)

Chưa apply:
- `meeting.role:operator|chair` middleware
- Endpoints `PATCH /meeting-discussion-registrations/{id}/start` + `/complete`
- Endpoints attendance `/checkin` + `/approve` + `/reject`
- Endpoints meeting `/lock-attendance` + `/delegate-operator`
- `MeetingResource.current_user_role` field

FE tạm bỏ Tab 7 (Điều hành) hoặc mock UI cho đến khi Sprint 1 ship.
