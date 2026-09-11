# Khoá đơn thư đã hoàn thành và tách quyền trả lại / mở lại công việc

> Ngày tạo: 09:15:00 11/09/2026  
> Cập nhật lần cuối: 09:15:00 11/09/2026

Hai thay đổi ở backend ảnh hưởng tới nút bấm trên giao diện.

---

## 1. Đơn thư đã hoàn thành nay khoá cả xoá

Trước đây khoá chỉ phủ sửa và đổi trạng thái. Xoá thì không — mà bảng đơn thư
không có `deleted_at` nên xoá là xoá vĩnh viễn.

| Endpoint | Trước | Sau |
|---|---|---|
| `DELETE /api/task-assignment-petitions/{petition}` | Xoá được đơn `completed` | **403** với đơn `completed` |
| `DELETE /api/task-assignment-petitions/bulk-delete` | Xoá cả đơn `completed` | Bỏ qua đơn `completed`, chỉ xoá phần còn lại |

Thông báo của `bulk-delete` nay báo **số đã xoá thật**, kèm câu nhắc số đơn bị bỏ
qua. FE đang hiển thị `response.message` nên không phải sửa gì để có câu đúng.

Đơn thư cũng đã chuyển sang **xoá mềm**: bản ghi đã xoá không mất, chỉ ẩn khỏi
mọi truy vấn thường.

### Việc cần làm ở FE

1. **Ẩn nút Xoá** khi `processing_status === 'completed'` (đã làm ở web và
   miniapp trong cùng đợt).
2. **Chặn tích chọn** dòng `completed` ở bảng có thao tác hàng loạt — đã làm ở
   web bằng `:item-selectable`.
3. So thẳng `processing_status !== 'completed'`, **không** dùng helper
   `isTerminalStatus()`: helper đó gồm cả `cancelled`, trong khi backend chỉ
   khoá `completed` — dùng nó sẽ khiến giao diện chặt hơn luật thật.

---

## 2. Trả lại báo cáo và mở lại công việc: chỉ người đã giao việc

`reject` và `reopen` trước đây gác bằng policy `changeStatus`, mà policy đó chỉ
đòi "người liên quan tới công việc" — nên **chính người thực hiện tự trả lại báo
cáo của mình được**. Đây là hai mặt của việc duyệt (`markDone` là chấp nhận,
`reject` là từ chối) nên phải cùng một luật với duyệt.

| Endpoint | Trước | Sau |
|---|---|---|
| `PATCH /{item}/reject` | quyền `changeStatus\|pause\|cancel` + là người liên quan | quyền `my-assigned-tasks.changeStatus` + **là người đã giao việc** |
| `PATCH /{item}/reopen` | như trên | như trên |
| `PATCH /{item}/status` | không đổi | không đổi (tạm dừng / huỷ) |

Quản trị có `task-overview.manageAll` vẫn đi tắt được.

### Việc cần làm ở FE

- **Nút "Trả lại" và "Mở lại" gác bằng `can('changeStatus', 'TaskAssignmentItems')`**,
  không phải `can('markDone', ...)` — đã làm ở `TaskItemModal.vue`. Nút "Xác nhận
  hoàn thành" vẫn giữ `markDone`.
- Miniapp không phải sửa: `can('reject'/'reopen', 'TaskAssignmentItems', { task })`
  vốn đã rơi vào nhánh kiểm `assigned_by` nên đang khớp sẵn với backend.
- Giao diện vẫn chỉ kiểm **quyền**, không kiểm **quyền sở hữu** — người có quyền
  nhưng không phải người giao việc vẫn thấy nút rồi nhận 403 kèm thông báo. Đây
  là hành vi có sẵn của nút "Xác nhận hoàn thành", giữ nguyên cho nhất quán.
