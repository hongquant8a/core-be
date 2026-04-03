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
| **Body** | `task_assignment_item_id` (required, ID công việc), `completed_at` (optional, YYYY-MM-DD HH:mm:ss — ngày hoàn thành theo báo cáo), `report_document_number` (optional, số văn bản báo cáo), `report_document_excerpt` (optional, trích yếu văn bản), `report_document_content` (optional, nội dung chi tiết), `attachments[]` (optional, tệp đính kèm, có thể nhiều file). Form-data hoặc JSON. |
| **Response** | 201, object báo cáo (kèm attachments) + `"message": "Báo cáo công việc đã được tạo thành công!"`. |

---

## Cập nhật báo cáo

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-item-reports/{id}` |
| **Auth** | Bắt buộc. |
| **Body** | Giống tạo (các trường tùy chọn, không bao gồm `task_assignment_item_id`). Thêm: `remove_attachment_ids` (mảng ID tệp đính kèm cần xóa), `attachments[]` (tệp mới append). |
| **Response** | Object báo cáo đã cập nhật (kèm attachments). |

---

## Xóa báo cáo

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-item-reports/{id}` |
| **Auth** | Bắt buộc. |
| **Response** | `{ "message": "Báo cáo công việc đã được xóa thành công!" }`. |

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
  "completed_at": "2026-03-31 17:00:00",
  "report_document_number": "BC-NS-01/2026",
  "report_document_excerpt": "Báo cáo tổng hợp nhân sự quý 1 năm 2026",
  "report_document_content": "Nội dung chi tiết báo cáo...",
  "attachments": [
    { "id": 1, "name": "bao-cao-nhan-su-q1.pdf", "url": "https://..." }
  ],
  "created_at": "09:30:00 31/03/2026",
  "updated_at": "09:30:00 31/03/2026"
}
```
