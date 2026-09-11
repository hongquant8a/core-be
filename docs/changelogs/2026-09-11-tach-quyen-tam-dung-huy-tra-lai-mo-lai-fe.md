# Tách quyền cho tạm dừng, huỷ, trả lại báo cáo, mở lại công việc

> Ngày tạo: 10:30:00 11/09/2026  
> Cập nhật lần cuối: 10:30:00 11/09/2026

Bốn thao tác can thiệp vào công việc nay là bốn nghiệp vụ riêng: mỗi cái một
quyền, một endpoint, một luật. Thay đổi này **phá vỡ tương thích** với cách gọi
cũ — đọc kỹ mục 3.

---

## 1. Vấn đề cũ

`pause` và `cancel` có mặt trong bảng quyền nhưng **không có tác dụng**: policy
gộp cả ba bằng OR.

```php
$hasPermission = $user->hasPermissionTo('my-assigned-tasks.changeStatus')
    || $user->hasPermissionTo('my-assigned-tasks.pause')
    || $user->hasPermissionTo('my-assigned-tasks.cancel');
```

Có một trong ba là làm được cả ba. Đo thực tế: vai trò **chỉ có `pause`** vẫn huỷ
được công việc.

`reject` và `reopen` thì không có quyền riêng, bám vào `changeStatus`.

---

## 2. Quyền mới

| Quyền | Thao tác | Ai được |
|---|---|---|
| `my-assigned-tasks.markDone` | Xác nhận hoàn thành | người đã giao việc |
| `my-assigned-tasks.reject` | **mới** — Trả lại báo cáo | người đã giao việc |
| `my-assigned-tasks.reopen` | **mới** — Mở lại công việc | người đã giao việc |
| `my-assigned-tasks.pause` | Tạm dừng | người đã giao việc |
| `my-assigned-tasks.cancel` | Huỷ | người đã giao việc |
| `my-assigned-tasks.changeStatus` | Chuyển Chưa thực hiện ↔ Đang thực hiện | người liên quan |

Vai trò **Quản lý công việc** được cấp đủ cả sáu (tự động, vì seeder cấp toàn bộ
action của `my-assigned-tasks`). Quản trị có `task-overview.manageAll` vẫn đi tắt.

Chạy lại: `php artisan db:seed --class=PermissionSeeder`

---

## 3. Thay đổi API — **phá vỡ tương thích**

| Endpoint | Trước | Sau |
|---|---|---|
| `PATCH /{item}/pause` | không có | **mới** — gác `can:pause` |
| `PATCH /{item}/cancel` | không có | **mới** — gác `can:cancel` |
| `PATCH /{item}/status` | nhận todo, in_progress, paused, cancelled | **chỉ nhận `todo` và `in_progress`** |
| `PUT /{item}` | `processing_status` nhận mọi giá trị kể cả `done` | **chỉ nhận `todo` và `in_progress`** |
| `PATCH /{item}/reject` | gác `can:changeStatus` | gác `can:reject` |
| `PATCH /{item}/reopen` | gác `can:changeStatus` | gác `can:reopen` |
| `PATCH /bulk-status` | đổi sang paused/cancelled chỉ cần quyền sửa công việc | đòi đúng `pause` / `cancel` |

Gửi `processing_status: 'done'` hoặc `'cancelled'` qua `PUT /{item}` nay trả **422**.

### Vì sao siết `PUT`

Đặt `done` qua `PUT` là duyệt việc bằng cửa sau: bỏ qua điều kiện phải đang chờ
duyệt, không ghi `approved_by` / `completed_at`, không bắn sự kiện thông báo.
Miniapp đang duyệt / tạm dừng / huỷ / trả lại / mở lại theo đúng đường đó.

---

## 4. Việc cần làm ở FE

**Web** — đã làm trong cùng đợt:
- `TaskItemService.updateProcessingStatus()` định tuyến `paused` → `/pause`,
  `cancelled` → `/cancel`, còn lại giữ `/status`. Nơi gọi không đổi.
- Nút Tạm dừng và Huỷ vốn đã gác đúng `can('pause')` / `can('cancel')`.
- Nút Trả lại đổi sang `can('reject')`, nút Mở lại đổi sang `can('reopen')`.
  Nút Xác nhận hoàn thành giữ `can('markDone')`.

**Miniapp** — đã làm trong cùng đợt: `markDone`, `approveTask`, `pauseTask`,
`cancelTask`, `reopenTask`, `rejectTask` chuyển hết sang endpoint chuyên dụng
thay vì `PUT /{id}`.

**Chỗ nào còn gọi `PUT /{item}` kèm `processing_status` là done/paused/cancelled
thì phải đổi**, nếu không sẽ nhận 422.
