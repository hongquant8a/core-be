# API Báo cáo công việc (Task Assignment Item Report)

Quản lý báo cáo tiến độ/kết quả cho từng công việc: danh sách (lọc theo công việc), chi tiết, CRUD. Mỗi báo cáo thuộc một công việc cụ thể và do một người dùng (reporter) tạo; hỗ trợ đính kèm tệp.

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint chỉ thao tác dữ liệu thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`).

**Base path:** `/api/task-assignment-item-reports`

---

## Danh sách báo cáo

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-reports` |
| **Auth** | Bắt buộc. |
| **Query** | `task_assignment_item_id` (**required**, validated — ID công việc cần lấy báo cáo; request sẽ bị từ chối nếu thiếu), `search` (trích yếu/nội dung), `sort_by` (id \| completed_at \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection; mỗi item kèm `reporter`, `attachments`. |

---

## Chi tiết báo cáo

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-reports/{id}` |
| **Auth** | Bắt buộc. |
| **UrlParam** | `id` — ID báo cáo. |
| **Response** | Object báo cáo (TaskAssignmentItemReportResource), kèm `reporter`, `attachments`, thông tin công việc liên quan. |

---

## Tạo báo cáo

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-item-reports` |
| **Auth** | Bắt buộc. |
| **Body** | `task_assignment_item_id` (required, ID công việc), `content` (required, nội dung báo cáo), `progress` (optional, 0-100 — tiến độ tại thời điểm báo cáo), `files[]` (optional, tệp đính kèm, tối đa 10 tệp, multipart/form-data). |
| **Response** | 201, object báo cáo (kèm attachments) + `"message": "Báo cáo đã được tạo thành công!"`. |

---

## Cập nhật báo cáo

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-item-reports/{id}` |
| **Auth** | Bắt buộc. |
| **Body** | `content` (optional, nội dung báo cáo), `progress` (optional, 0-100), `files[]` (optional, tệp đính kèm mới, append), `remove_attachment_ids` (optional, mảng ID đính kèm cần xóa). |
| **Response** | Object báo cáo đã cập nhật (kèm attachments) + `"message": "Báo cáo đã được cập nhật!"`. |

**Xử lý file đính kèm:**
- `files[]` → upload file mới, thêm vào danh sách.
- `remove_attachment_ids` → xóa file theo ID.
- Không gửi → giữ nguyên.

---

## Xóa báo cáo

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-item-reports/{id}` |
| **Auth** | Bắt buộc. |
| **Response** | `{ "message": "Báo cáo đã được xóa thành công!" }`. |

---

## Response mẫu (TaskAssignmentItemReportResource)

```json
{
  "id": 1,
  "task_assignment_item_id": 1,
  "task_assignment_item": {
    "id": 1,
    "name": "Báo cáo tình hình nhân sự Q1/2026"
  },
  "reporter": {
    "id": 2,
    "name": "Nguyễn Văn A",
    "email": "a@example.com"
  },
  "content": "Đã hoàn thành 50% khối lượng công việc.",
  "progress": 50,
  "attachments": [
    { "id": 1, "name": "bao-cao-nhan-su-q1.pdf", "url": "https://..." }
  ],
  "created_at": "09:30:00 31/03/2026",
  "updated_at": "09:30:00 31/03/2026"
}
```
