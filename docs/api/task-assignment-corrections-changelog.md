# Changelog – TaskAssignment Corrections

## 2026-04-21 – Multi-department users + Report confirm/lock + Enum sync

Các thay đổi API từ đợt correction ngày 2026-04-21. FE cần cập nhật theo các mục 1–5.

---

## 1. `PATCH /api/task-assignment-item-reports/{id}/confirm` – **NEW**

Xác nhận báo cáo và khóa (1 step — confirm đồng thời lock).

**Request:**
```json
{
  "confirm_note": "Đạt quy định"   // optional
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Đã xác nhận báo cáo.",
  "data": {
    "id": 1,
    "manager_confirmed": true,
    "manager_confirmed_by": 42,
    "manager_confirmed_at": "2026-04-21T10:30:00.000000Z",
    "manager_confirm_note": "Đạt quy định",
    "is_locked": true,
    "locked_at": "2026-04-21T10:30:00.000000Z",
    "locked_by": 42,
    "...": "các field cũ của report giữ nguyên"
  }
}
```

**Response 422 — đã bị khóa (không gọi lại được):**
```json
{ "success": false, "message": "Báo cáo đã khóa, không thể xác nhận lại." }
```

**Permission:** `task-assignment-item-reports.confirm` (Super Admin + Admin tự có).

---

## 2. `PATCH /api/task-assignment-item-reports/{id}` – behavior change

**Mới:** Nếu report đã `is_locked=true` → trả **422**:
```json
{ "success": false, "message": "Báo cáo đã khóa, không thể sửa." }
```

FE nên check `is_locked` trước khi hiển thị nút "Sửa" trên UI.

Tương tự cho `DELETE /api/task-assignment-item-reports/{id}` → **422 khi locked**:
```json
{ "success": false, "message": "Báo cáo đã khóa, không thể xóa." }
```

---

## 3. Report resource shape – **thêm 7 field**

Mọi endpoint trả report (`index`, `show`, `store`, `update`, `confirm`) nay có thêm:

| Field | Type | Nullable | Ghi chú |
|---|---|---|---|
| `manager_confirmed` | boolean | – | Default `false` |
| `manager_confirmed_by` | integer (user id) | ✅ | Null khi chưa confirm |
| `manager_confirmed_at` | datetime ISO | ✅ | |
| `manager_confirm_note` | string | ✅ | Ghi chú quản lý khi xác nhận |
| `is_locked` | boolean | – | Default `false` |
| `locked_at` | datetime ISO | ✅ | |
| `locked_by` | integer (user id) | ✅ | |

FE action:
- Màn danh sách báo cáo: thêm badge `Đã xác nhận` / `Đã khóa`.
- Màn chi tiết: hiển thị thông tin xác nhận (ai, khi, ghi chú) khi `manager_confirmed=true`.
- Nút `Sửa`/`Xóa`: disabled khi `is_locked=true`.
- Nút mới `Xác nhận`: hiển thị cho quản lý khi có permission `task-assignment-item-reports.confirm` **và** `is_locked=false`.

---

## 4. Task-assignment departments users – behavior change

**Endpoint:** `POST /api/task-assignment-departments/{id}/sync-users`
(không đổi URL/method, chỉ đổi behavior)

### 4.1 Delta sync (không destroy-all)

**Trước:** gọi `syncUsers` xóa toàn bộ user cũ trong phòng ban rồi insert lại → nếu user thuộc phòng ban khác sẽ **mất** liên kết đó.

**Giờ:** delta sync — chỉ xóa user không có trong payload, thêm user mới. User thuộc phòng ban khác **không bị ảnh hưởng**.

### 4.2 User có thể thuộc nhiều phòng ban

Cùng 1 user có thể xuất hiện trong nhiều department (cùng organization). FE danh sách user có thể thấy cùng 1 user ở nhiều phòng ban.

### 4.3 Field mới: `is_primary`

Record `task_assignment_users` nay có cờ `is_primary` (boolean).

Endpoint `GET /api/task-assignment-departments/{id}/users` trả thêm field này trong mỗi record.

**Rule tự động (BE handle):**
- User lần đầu được add vào department → auto `is_primary=true` nếu user **chưa có** record primary nào khác trong cùng org.
- User đã có primary ở dept khác → add mới vào dept này sẽ là `is_primary=false`.
- Khi remove user khỏi dept đang primary → BE tự promote 1 record khác (nếu có) thành primary.

### 4.4 `PATCH .../set-primary` – (gợi ý FE, BE chưa có endpoint công khai)

Nếu cần UI đổi primary cho user, BE đã có service method `TaskAssignmentDepartmentService::setPrimary($userId, $deptId)` nhưng **chưa expose endpoint**. Khi FE cần → liên hệ BE thêm endpoint.

---

## 5. Permission mới

| Name | Guard | Resource label | Action label |
|---|---|---|---|
| `task-assignment-item-reports.confirm` | web | Báo cáo công việc | Xác nhận |

Super Admin + Admin auto có. Role khác → admin gán qua màn Permission Management.

CASL ability tương ứng:
```js
$can('confirm', 'TaskAssignmentItemReport')
```

---

## 6. Enum `TaskReminderStatusEnum` – breaking sync

Enum cũ đã **không khớp** DB. Sync lại theo migration `2026-04-16-restructure`:

| Trước (stale) | Giờ (khớp DB) | Tiếng Việt |
|---|---|---|
| `pending` | `pending` | Chờ gửi |
| `sent` | `fired` | Đã gửi |
| `failed` | `cancelled` | Đã hủy |

FE impact: nếu đang đọc `status` của reminder qua bất kỳ endpoint nào (hiện tại chưa có endpoint public), cần map:
- `sent` → `fired`
- `failed` → `cancelled`

Mất giá trị `failed` — reminder không còn bị mark "thất bại", thay vào đó là `cancelled` cho các lý do (item done/cancelled, config disabled, v.v.).

---

## 7. Quick summary (cho PM/QA)

- Flow duyệt báo cáo: quản lý bấm **Xác nhận** → báo cáo tự động khóa. Sau đó không sửa/xóa được (trừ trường hợp thêm endpoint unlock đặc biệt sau).
- User có thể thuộc nhiều phòng giao việc cùng lúc, có 1 phòng "chính".
- Không có breaking change về URL — tất cả thay đổi là **thêm endpoint mới**, **thêm field response**, hoặc **đổi behavior** (mã lỗi khi locked).

---

## Related documents

- **Correction spec:** [docs/superpowers/specs/2026-04-21-task-assignment-corrections.md](../superpowers/specs/2026-04-21-task-assignment-corrections.md)
- **Implementation plan:** [docs/superpowers/plans/2026-04-21-task-assignment-corrections.md](../superpowers/plans/2026-04-21-task-assignment-corrections.md)
- **Module spec gốc:** [phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md](../../phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md)
