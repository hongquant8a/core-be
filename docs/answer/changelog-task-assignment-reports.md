# Changelog — Báo cáo công việc: thêm người thực hiện

**Ngày:** 2026-06-16

---

## Tóm tắt

Báo cáo công việc (`task_assignment_item_reports`) giờ có thêm field `assignee_user_id` để lưu **người thực hiện** công việc (khác với `reporter_user_id` là người nộp báo cáo).

---

## 1. Migration mới

Chạy `sail artisan migrate` để thêm cột `assignee_user_id` vào bảng `task_assignment_item_reports`.

---

## 2. API thay đổi

### 2.1. `POST /api/task-assignment-item-reports` — Tạo báo cáo

**Thêm body param:**

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `assignee_user_id` | integer | No | ID người thực hiện công việc |

### 2.2. `PATCH /api/task-assignment-item-reports/{id}` — Cập nhật báo cáo

**Thêm body param (optional):**

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `assignee_user_id` | integer | No | ID người thực hiện công việc |

### 2.3. Response (index / show / store / update)

Response giờ có thêm field `assignee` (cùng format với `reporter`):

```json
{
  "id": 1,
  "task_assignment_item_id": 168,
  "reporter": { "id": 3, "name": "Nguyễn Văn A" },
  "assignee": { "id": 5, "name": "Trần Thị B" },
  "completion_percent": 80,
  ...
}
```

`assignee` = `null` nếu không được set hoặc không load relation.

---

## 3. Dropdown người thực hiện

FE dùng API có sẵn, không cần endpoint mới:

```
GET /api/task-assignment-employees/options?department_id=<DEPARTMENT_ID>&status=active
```

Response (đã bổ sung `is_representative`):

```json
[
  {
    "id": 1,
    "user_id": 5,
    "name": "Nguyễn Văn A",
    "email": "a@example.com",
    "user_name": "nguyenvana",
    "status": "active",
    "is_representative": true
  }
]
```

**Cách lấy `department_id`:** từ `task_assignment_item_user.department_id` của task hiện tại. Mỗi task có 1 hoặc nhiều phòng ban được gán — FE có thể lấy từ response `GET /api/task-assignment-items/{id}` (field `departments`).
