# Gia hạn thời hạn công việc — Backend đã xong, FE cần làm

> Ngày tạo: 12/09/2026

Người thực hiện xin dời thời hạn kèm lý do; người đã giao việc duyệt hoặc từ chối;
lịch sử mọi lần xin được lưu lại.

Thiết kế đầy đủ: [`docs/answer/gia-han-thoi-han-cong-viec_180547_10092026.md`](../answer/gia-han-thoi-han-cong-viec_180547_10092026.md)

---

## 1. Hai quyền mới

| Quyền | CASL ability | Ai được cấp |
|---|---|---|
| `my-received-tasks.requestExtension` | `{action: 'requestExtension', subject: 'TaskAssignmentItems'}` | Nhân viên · Trưởng phòng · Lãnh đạo · Quản lý công việc |
| `my-assigned-tasks.approveExtension` | `{action: 'approveExtension', subject: 'TaskAssignmentItems'}` | Quản lý công việc |

Sau khi kéo code, chạy `sail artisan db:seed --class=PermissionSeeder` để có hai
quyền này (tổng số quyền: 312 → **314**).

## 2. Endpoint

| Thao tác | Method | Đường dẫn | Quyền | Policy |
|---|---|---|---|---|
| Lịch sử xin gia hạn | `GET` | `/api/task-assignment-items/{item}/extensions` | một trong hai quyền trên | — |
| Gửi yêu cầu | `POST` | `/api/task-assignment-items/{item}/extensions` | `requestExtension` | chỉ **người thực hiện** |
| Duyệt | `PATCH` | `/api/task-assignment-items/{item}/extensions/{id}/approve` | `approveExtension` | chỉ **người đã giao việc** |
| Từ chối | `PATCH` | `/api/task-assignment-items/{item}/extensions/{id}/reject` | `approveExtension` | chỉ **người đã giao việc** |
| Thu hồi | `DELETE` | `/api/task-assignment-items/{item}/extensions/{id}` | `requestExtension` | chỉ **người đã gửi** yêu cầu đó |

Duyệt và từ chối là **hai endpoint riêng**, không dùng `PATCH /{id}/status` — chúng
là hai nghiệp vụ, không phải hai giá trị của một trường trạng thái. Quy ước chung
ở `CLAUDE.md` mục 3 đã được cập nhật theo hướng này.

`{id}` được gác scope: yêu cầu phải thuộc đúng `{item}` trong URL, không thì **404**.

### Body

```jsonc
// POST — gửi yêu cầu
{
  "requested_end_at": "2026-10-15 17:00:00",  // chỉ cần là ngày hợp lệ
  "reason": "Đơn vị phối hợp chưa gửi số liệu quý III."  // 10-2000 ký tự, BẮT BUỘC
}

// PATCH approve — ghi chú tuỳ chọn
{ "review_note": "Đồng ý dời hạn." }

// PATCH reject — ghi chú BẮT BUỘC, để trống trả 422
{ "review_note": "Việc này đã gia hạn một lần, đề nghị hoàn thành đúng hạn." }
```

**Hạn xin CỐ Ý không ràng buộc gì** — không đòi lớn hơn hạn cũ, không đòi lớn hơn
hiện tại. Người xin tự biết mình cần hạn nào, và mọi yêu cầu đều phải qua người
duyệt. Đừng thêm luật ở form FE.

### Response

```jsonc
{
  "id": 1,
  "task_assignment_item_id": 514,
  "requested_by": { "id": 82, "name": "...", "avatar": null },
  "current_end_at": "23:59:59 17/12/2025",   // hạn tại thời điểm xin
  "requested_end_at": "17:00:00 31/12/2026",
  "reason": "...",
  "status": "pending",                       // pending | approved | rejected | cancelled
  "status_label": "Chờ duyệt",               // đã dịch sẵn, FE dùng thẳng
  "reviewed_by": null,
  "reviewed_at": null,
  "review_note": null,
  "created_at": "13:33:49 12/09/2026"
}
```

## 3. Điều cần nắm khi dựng giao diện

**Thời hạn KHÔNG đổi khi đang chờ duyệt.** Việc quá hạn vẫn hiện quá hạn, lịch nhắc
vẫn chạy theo hạn cũ. Chỉ khi duyệt xong `end_at` mới dời. Đừng hiển thị hạn mới
như đã có hiệu lực khi yêu cầu còn `pending`.

**Điều kiện hiện nút "Xin gia hạn":**

- `can('requestExtension', 'TaskAssignmentItems')`
- `deadline_type === 'has_deadline'`
- `processing_status` không thuộc `done` / `cancelled` / `pending_approval`
- chưa có yêu cầu nào đang `pending`

**Điều kiện hiện nút Duyệt / Từ chối:** `can('approveExtension', …)` **và** có yêu
cầu đang `pending`. Lưu ý điều kiện là **trạng thái của yêu cầu**, không phải
`processing_status` của công việc — một việc đang `in_progress` vẫn có thể có yêu
cầu chờ duyệt.

**Mã lỗi 422 có thể gặp** (thông báo đã là tiếng Việt, hiển thị thẳng):

| Thông báo | Khi nào |
|---|---|
| Công việc đang có một yêu cầu gia hạn chờ duyệt. | Gửi yêu cầu thứ hai |
| Công việc đang ở trạng thái không thể xin gia hạn. | `done` / `cancelled` / `pending_approval` |
| Công việc không có thời hạn nên không cần gia hạn. | `deadline_type = no_deadline` |
| Yêu cầu gia hạn này đã được xử lý. | Duyệt / từ chối cái đã xong |
| Vui lòng nhập lý do từ chối. | `reject` mà `review_note` rỗng |
| Chỉ người gửi yêu cầu mới thu hồi được yêu cầu đó. | Thu hồi yêu cầu của người khác |

## 4. Dòng thời gian

`GET /api/task-assignment-items/{item}/timeline` nay trả thêm **hai** loại entry:

| `type` | `timestamp` | `actor` |
|---|---|---|
| `extension_requested` | lúc gửi yêu cầu | người xin |
| `extension_reviewed` | lúc duyệt / từ chối | người duyệt |

Một yêu cầu sinh **hai mốc riêng**, không gộp — gộp thì lần duyệt hôm nay lại hiện
ở vị trí của ngày gửi tuần trước. `data` của cả hai là object yêu cầu ở mục 2.

FE cần thêm hai nhánh render; thiếu nhánh thì entry rơi vào `v-else` và hiện sai.

## 5. Thông báo

Hai sự kiện mới, **mặc định TẮT** — quản trị bật ở màn Cấu hình thông báo:

| Event key | Báo cho | Nội dung |
|---|---|---|
| `deadline_extension_requested` | người đã giao việc | có yêu cầu chờ duyệt |
| `deadline_extension_reviewed` | người đã gửi yêu cầu | kết quả duyệt / từ chối |

Sau khi kéo code chạy `sail artisan db:seed --class=NotificationEventConfigSeeder`
để tạo cấu hình cho các tổ chức đã có.

## 5b. Sáu sự kiện thông báo khác vừa bổ sung cùng đợt

Rà lại toàn module thấy nhiều thao tác quan trọng không báo cho ai. Sáu sự kiện
dưới đây thêm cùng đợt, **tất cả mặc định TẮT**:

| Event key | Báo cho | Vá lỗ hổng gì |
|---|---|---|
| `task_deadline_changed` | người thực hiện | quản lý sửa thẳng `end_at` thì hạn đổi sau lưng người thực hiện, trong khi đi đường xin gia hạn thì họ được báo đầy đủ |
| `task_status_changed` | người thực hiện | tạm dừng / huỷ / mở lại trước đây câm lặng hoàn toàn — tạm dừng khoá cập nhật tiến độ mà người thực hiện chỉ phát hiện khi bấm vào thấy lỗi |
| `note_added` | người liên quan, trừ tác giả | kênh trao đổi chính trong công việc không báo cho ai |
| `petition_created` | đại diện phòng ban tiếp nhận | cả phân hệ đơn thư không có sự kiện nào |
| `petition_status_changed` | đại diện phòng ban, trừ người vừa đổi | như trên |
| `document_updated` | người thực hiện các việc trong văn bản | sửa văn bản **sau khi đã ban hành** — nội dung chỉ đạo đổi mà người làm không biết |

`task_status_changed` gộp cả ba thao tác vào một sự kiện, phân nhánh nội dung
theo trạng thái mới — quản trị chỉ cần một công tắc thay vì ba.

Chạy `sail artisan db:seed --class=NotificationEventConfigSeeder` sau khi kéo
code để tạo cấu hình cho các tổ chức đã có.

**Ghi nhận để xử lý sau:** `task_assignment_petitions.created_by` luôn NULL —
hệ thống không có chỗ nào ghi người lập đơn (kể cả đơn nhập từ hệ thống cũ). Vì
vậy `petition_status_changed` báo cho đại diện phòng ban chứ không phải người
lập đơn. Khi nào ghi được người lập thì mở rộng thêm.

## 6. Miniapp

Làm **cùng đợt** với web: nút xin ở màn Được giao, nút duyệt cắm vào
`TaskAssignedApprovalActions.jsx` (component này đã có sẵn prop `extraActions`).
