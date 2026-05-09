# Meeting in-meeting control policies — changelog FE

**Ngày:** 2026-05-09
**Đối tượng:** FE team

Module Meeting refactor permission cho **in-meeting control APIs** từ Spatie role-based sang **Laravel Policy** (chair/operator của CHÍNH meeting đó). Catalog/CRUD/dashboard giữ Spatie. URL + request/response shape **không đổi** — chỉ rule check thay đổi.

---

## 1. Endpoints chuyển sang Policy gate

| Endpoint | Trước | Sau |
|---|---|---|
| `PATCH /meetings/{id}/end-early` | permission `meetings.endEarly` | Chair hoặc Operator |
| `PATCH /meetings/{id}/lock-attendance` | permission `meetings.lockAttendance` | Chair hoặc Operator |
| `PATCH /meetings/{id}/unlock-attendance` | permission `meetings.unlockAttendance` | Chair hoặc Operator |
| `PATCH /meetings/{id}/highlight-agenda` | permission `meetings.highlightAgenda` | Chair hoặc Operator |
| `PATCH /meetings/{id}/highlight-discussion` | permission `meetings.highlightDiscussion` | Chair hoặc Operator |
| `PATCH /meeting-vote-topics/{id}/open` | permission `meeting-vote-topics.update` | Chair hoặc Operator |
| `PATCH /meeting-vote-topics/{id}/close` | permission `meeting-vote-topics.update` | Chair hoặc Operator |

→ Tất cả check **chair hoặc operator của CHÍNH meeting đó**. User có role meeting nói chung (qua Spatie) **không đủ** — phải đúng vai trò của meeting cụ thể.

→ **Super Admin bypass** mọi policy (vẫn vào được).

## 2. Endpoint giữ nguyên Spatie role

| Endpoint | Permission |
|---|---|
| `GET /meetings/{id}/qr-token` | `meetings.showQrCode` (giữ — admin role được xem QR cho mọi meeting) |
| `POST /meeting-vote-responses` | `meeting-vote-responses.store` (đại biểu submit phiếu — không liên quan policy điều hành) |
| `POST /meeting-attendances/checkin` | `meeting-attendances.checkin` (self-checkin) |
| Tất cả CRUD/list/stats/export Meeting + sub-resources | giữ Spatie role-based |

## 3. Permission đã bỏ khỏi seeder

5 permission key sau **không còn tồn tại** trong DB:

- `meetings.endEarly`
- `meetings.lockAttendance`
- `meetings.unlockAttendance`
- `meetings.highlightAgenda`
- `meetings.highlightDiscussion`

→ FE nếu đang dùng `can('meetings.endEarly')` (CASL/RBAC) để show button — cần đổi cách check.

## 4. Field mới trên `MeetingResource`: `current_user_meeting_role`

BE thêm field này vào response của `GET /meetings/{id}` và `GET /meetings`:

```json
{
  "id": 123,
  "title": "...",
  "chairperson": {...},
  "operator": {...},
  "current_user_meeting_role": "chairperson"
}
```

Giá trị: `"chairperson"` | `"operator"` | `"participant"` | `null` (user không liên quan meeting).

→ FE dùng field này để show/hide button điều hành:

```js
const canControl = ['chairperson', 'operator'].includes(meeting.current_user_meeting_role)
const canEndEarly = canControl
const canLockAttendance = canControl
const canHighlightAgenda = canControl
const canOpenVote = canControl
const canCloseVote = canControl

// QR token vẫn check Spatie permission
const canShowQR = userPermissions.includes('meetings.showQrCode')
```

→ Super Admin: BE bypass policy nhưng `current_user_meeting_role` có thể vẫn là `null` (vì admin không phải chair/operator). FE có thể combine:

```js
const canControl =
  ['chairperson', 'operator'].includes(meeting.current_user_meeting_role)
  || userRoles.includes('Super Admin')
```

## 5. Error response khi không có quyền

HTTP **403** với body:

```json
{
  "success": false,
  "message": "Bạn không có quyền thực hiện thao tác này.",
  "code": "FORBIDDEN"
}
```

→ Khác với 401 (chưa login) và 422 (validation fail). FE handle 403 hiển thị toast cảnh báo phù hợp.

## 6. Migrate FE checklist

- [ ] Trên detail meeting page (Tab Điều hành / Tab Biểu quyết): đổi check permission từ `can('meetings.endEarly')` etc → dựa vào `meeting.current_user_meeting_role`
- [ ] Bỏ 5 ability/permission name đã liệt kê khỏi CASL ability config
- [ ] Test với 4 user role: chair / operator / participant / outsider — verify button show/hide đúng
- [ ] Test với Super Admin — vẫn thấy button (combine với role check FE)
- [ ] Giữ check `meetings.showQrCode` cho QR endpoint (không đổi)

## 7. BE behavior (FE không cần làm gì thêm)

- Policy `MeetingPolicy::endEarly`, `manageAttendance`, `highlight` — trả `true` nếu user là chair hoặc operator của meeting đó.
- Policy `MeetingVoteTopicPolicy::open`, `close` — check `topic->meeting` rồi check user vai trò.
- `Gate::before` ở `AppServiceProvider` cho Super Admin bypass tất cả policy.
- Spatie role `Đại biểu họp` / `Thư ký họp` vẫn giữ — kiểm soát role-based catalog/CRUD/dashboard.
