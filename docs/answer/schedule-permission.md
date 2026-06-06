# Lịch công tác — Phân quyền theo phân hệ (EXECUTIVE / OFFICE)

## Mô hình phân quyền mới

Module Scheduling có 2 phân hệ, phân biệt qua `module_type`:

| `module_type` | Tên hiển thị | Resource permission |
|---|---|---|
| `EXECUTIVE` | Lịch Thường trực | `schedules-executive.{action}` |
| `OFFICE` | Lịch Lãnh đạo | `schedules-office.{action}` |

Mỗi phân hệ có bộ permission riêng (14 action):

```
stats, index, show, store, update, destroy,
bulkDestroy, bulkUpdateStatus, changeStatus, export,
approve, duplicate, reorder, driver-view
```

## Cách hoạt động

### Middleware `schedule.module`

Tất cả endpoint quản trị (trừ `general*` và `driver*`) được bảo vệ bởi middleware `schedule.module:{action}`.

Luồng kiểm tra:
1. Đọc `module_type` từ request (query param với GET, body param với POST/PUT/PATCH)
2. Nếu thiếu → lỗi **422** `"Thiếu module_type để xác định phân hệ (EXECUTIVE hoặc OFFICE)."`
3. Nếu giá trị không hợp lệ → lỗi **422** `"module_type không hợp lệ. Chấp nhận: EXECUTIVE, OFFICE."`
4. Map `EXECUTIVE` → `schedules-executive`, `OFFICE` → `schedules-office`
5. Kiểm tra `user->hasPermissionTo("schedules-{module}.{action}")` — nếu có → pass
6. Fallback: kiểm tra `schedules.{action}` (permission cũ) → nếu có → pass
7. Không có cả 2 → lỗi **403** `"Bạn không có quyền thực hiện thao tác này."`

### FE cần gửi `module_type` ở đâu

| HTTP Method | Cách gửi |
|---|---|
| GET | Query param: `?module_type=EXECUTIVE` |
| POST | Body param: `{ "module_type": "EXECUTIVE", ... }` |
| PUT/PATCH | Body param: `{ "module_type": "OFFICE", ... }` |
| DELETE | Query param: `?module_type=EXECUTIVE` |

### Endpoint không cần `module_type`

Các endpoint này không qua middleware `schedule.module`:

| Endpoint | Ghi chú |
|---|---|
| `GET /general*` | Nhân viên xem lịch chung, scope `general_visibility` |
| `GET /driver-view*` | Lái xe xem lịch được phân công, check Policy |
| `GET /weeks` | Cần `module_type` (có middleware `schedule.module:index`) |
| `GET /reorder` | Cần `module_type` |

## Danh sách permission đầy đủ

### `schedules-executive` (Lịch Thường trực)

| Permission | Mô tả |
|---|---|
| `schedules-executive.stats` | Thống kê |
| `schedules-executive.index` | Danh sách |
| `schedules-executive.show` | Chi tiết |
| `schedules-executive.store` | Tạo mới + sao chép |
| `schedules-executive.update` | Cập nhật + gửi duyệt + bulk status + reorder |
| `schedules-executive.destroy` | Xóa + xóa hàng loạt |
| `schedules-executive.changeStatus` | Ban hành / Hủy ban hành |
| `schedules-executive.approve` | Duyệt / Từ chối |
| `schedules-executive.export` | Xuất Excel/PDF/Word |
| `schedules-executive.bulkDestroy` | Xóa hàng loạt |
| `schedules-executive.bulkUpdateStatus` | Cập nhật trạng thái hàng loạt |
| `schedules-executive.duplicate` | Sao chép |
| `schedules-executive.reorder` | Sắp xếp lại |

### `schedules-office` (Lịch Lãnh đạo)

Giống hệt `schedules-executive` nhưng với prefix `schedules-office`.

### `schedules.*` (cũ — fallback)

Vẫn tồn tại để backward-compatible. Nếu user có `schedules.index` nhưng không có `schedules-executive.index` hay `schedules-office.index`, middleware vẫn cho pass.

## Phân quyền theo role (mặc định từ PermissionSeeder)

| Role | Phân hệ | Quyền |
|---|---|---|
| **Thư ký** | EXECUTIVE | `stats, index, show, store, update, destroy, bulkDestroy, bulkUpdateStatus, changeStatus, export, duplicate, reorder` |
| **Lãnh đạo** | OFFICE | `index, show, stats, export, approve` |
| **Tổng hợp** | Cả 2 | Toàn bộ `schedules-executive.*` + `schedules-office.*` |
| **Quản trị** | Cả 2 | Full (qua role Admin/Super Admin, bỏ qua Policy) |
| **Lái xe** | Cả 2 | `schedules-executive.{index,show,driver-view}` + `schedules-office.{index,show,driver-view}` (xem lịch được gán) |

## Ví dụ

### Request hợp lệ

```bash
# Thư ký xem danh sách lịch Thường trực
GET /api/schedules?module_type=EXECUTIVE&status=0&approval_status=pending
# → 200 (có schedules-executive.index)

# Lãnh đạo duyệt lịch Lãnh đạo
PATCH /api/schedules/42/approve?module_type=OFFICE
# → 200 (có schedules-office.approve)
```

### Request bị từ chối

```bash
# Thư ký cố xem lịch Lãnh đạo
GET /api/schedules?module_type=OFFICE
# → 403 (chỉ có schedules-executive.index, không có schedules-office.index)

# Thiếu module_type
GET /api/schedules
# → 422 "Thiếu module_type để xác định phân hệ (EXECUTIVE hoặc OFFICE)."

# Module type sai
GET /api/schedules?module_type=ABC
# → 422 "module_type không hợp lệ. Chấp nhận: EXECUTIVE, OFFICE."
```

## LogActivity

Khi user thao tác, log sẽ ghi resource label tương ứng:

| Request | Label trong log |
|---|---|
| `?module_type=EXECUTIVE` | `lịch công tác - Thường trực` |
| `?module_type=OFFICE` | `lịch công tác - Lãnh đạo` |
