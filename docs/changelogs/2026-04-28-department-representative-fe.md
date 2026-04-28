# Changelog FE — Người đại diện phòng ban

**Ngày:** 2026-04-28
**Branch:** `main`
**Đối tượng:** FE team

Thêm flag "người đại diện" cho thành viên phòng ban. Khi tạo công việc và chọn phòng (cấp phòng), FE auto-fill user có flag vào field "Người thực hiện".

**Không thêm API mới.** Chỉ extend 2 endpoint đã có.

---

## 1. Khái niệm

- Mỗi phòng ban (`task_assignment_department`) có **tối đa 1 đại diện**.
- Đại diện là **optional** — phòng có thể không có đại diện (FE handle null).
- Khi đại diện rời phòng (remove user) → phòng không còn đại diện. Không auto-promote.
- Khi switch đại diện → flag cũ tự động clear (BE đảm bảo invariant).
- `is_representative` (per-department) khác với `is_primary` (per-user — phòng chính của user). Một user có thể vừa `is_primary=true` vừa `is_representative=true`.

---

## 2. API changes

### 2.1. `GET /api/task-assignment-departments/{id}/users` — response thêm `is_representative`

```diff
{
  "data": [
    {
      "id": 12,
      "user_id": 5,
      "name": "Đặng Minh Tuấn",
      "email": "dmtuan@snvdn.gov.vn",
      "user_name": "dmtuan",
      "avatar": "/storage/...",
      "status": "active",
+     "is_representative": true
    },
    {
      "id": 13,
      "user_id": 8,
      "name": "Nhân viên",
      ...,
+     "is_representative": false
    }
  ]
}
```

- Type: `bool`
- Tối đa 1 item trong list có `is_representative === true`. Có thể không item nào có (chưa set).

### 2.2. `POST /api/task-assignment-departments/{id}/users` — request thêm field optional `representative_user_id`

**Trước:**
```json
{
  "user_ids": [1, 2, 3]
}
```

**Sau (backward-compat — body cũ vẫn work):**
```json
{
  "user_ids": [1, 2, 3],
  "representative_user_id": 2
}
```

| Field | Type | Bắt buộc | Ý nghĩa |
|-------|------|----------|---------|
| `user_ids` | `int[]` | Yes | Như cũ |
| `representative_user_id` | `int \| null` | No | ID user làm đại diện. **Phải nằm trong `user_ids`**. |

**Behavior:**
- Nếu `representative_user_id` có → BE set user đó làm đại diện sau khi sync. Các user khác trong phòng tự động `is_representative=false`.
- Nếu `representative_user_id` absent hoặc `null` → BE **không đụng** đến trạng thái đại diện hiện tại (giữ nguyên).
- Nếu user hiện đang là đại diện bị remove khỏi `user_ids` → row bị xóa → phòng không còn đại diện (tự nhiên).

**Validation lỗi 422:**
```json
{
  "errors": {
    "representative_user_id": ["Người đại diện phải nằm trong danh sách thành viên được chọn."]
  }
}
```

(xảy ra khi `representative_user_id` không có trong `user_ids`)

---

## 3. UX flows cần implement

### 3.1. Form tạo công việc — auto-fill "Người thực hiện" theo đại diện phòng

Khi user chọn "Đơn vị thực hiện" (dropdown phòng):
1. FE call `GET /api/task-assignment-departments/{id}/users`
2. Tìm item có `is_representative === true`
3. Auto-fill `user_id` đó vào field "Người thực hiện"
4. User vẫn có thể đổi tay trước khi submit

Nếu không có item nào `is_representative === true` → để trống, user tự pick.

### 3.2. Form quản lý thành viên phòng ban — set đại diện khi add user

Trong dialog "NGƯỜI DÙNG — PHÒNG..." (UIUX hiện có):
- **Khi search + add user mới**: nếu UI cho phép tick "đặt làm đại diện" → submit kèm `representative_user_id`. Một call duy nhất.
- **Khi switch đại diện** giữa các user đã có trong list: gọi lại endpoint cũ với full `user_ids` (không đổi) + `representative_user_id` mới. BE chỉ update flag, không đụng membership.

**Cần render trong list users:** badge/icon đánh dấu user nào là đại diện (dựa vào `is_representative` flag trong response). Cho phép user click để switch.

### 3.3. Clear đại diện (rep nghỉ việc, giữ list user)

Hiện tại **chưa có cách clear rep mà không remove user**. Workarounds:
- Remove user khỏi phòng → cũng clear flag (qua sync user_ids không bao gồm user đó).

Nếu cần clear-only flow → bàn lại với BE để mở rộng (sẽ thêm `clear_representative: true` hoặc verb riêng — không phải scope hiện tại).

---

## 4. Type definitions (TS gợi ý)

```ts
interface DepartmentUserItem {
  id: number;
  user_id: number;
  name: string;
  email: string | null;
  user_name: string | null;
  avatar: string | null;
  status: 'active' | 'inactive';
  is_representative: boolean;
}

interface SyncDepartmentUsersBody {
  user_ids: number[];
  representative_user_id?: number | null;
}
```

---

## 5. Edge cases

| Case | BE behavior | FE handle |
|------|-------------|-----------|
| Phòng không có đại diện | Tất cả items có `is_representative: false` | Để field "Người thực hiện" trống |
| Submit `representative_user_id` không có trong `user_ids` | 422 với message tiếng Việt | Hiện toast lỗi |
| Submit `representative_user_id: null` cùng `user_ids` mới | Sync user_ids, KHÔNG đụng rep state cũ | Nếu muốn clear → remove user khỏi list |
| User là rep nhưng bị remove qua sync | Row deleted → flag biến mất | Sau remove, refetch list để cập nhật UI |
| User vừa `is_primary=true` vừa `is_representative=true` | Hợp lệ, không conflict | Hai badge có thể cùng hiển thị |

---

## 6. Tests đã có (BE)

9 feature tests trong `tests/Feature/TaskAssignment/DepartmentRepresentativeTest.php` cover:
- Sync không có rep → không user nào có flag
- Sync có rep → đúng user có flag, các user khác không
- Validation rep không trong user_ids → 422
- Switch rep → flag cũ clear, flag mới set
- GET response shape (HTTP-level)
- Remove user là rep → phòng không còn rep
- Sync excluding current rep → flag biến mất
- Multi-org isolation
- `is_representative` độc lập với `is_primary`
