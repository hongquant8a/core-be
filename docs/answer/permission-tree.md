# Permission Tree API - Tài liệu cho Frontend

## Endpoint

```
GET /api/permissions/tree
```

**Auth:** Yêu cầu `Authorization: Bearer {token}` + `X-Organization-Id: {id}`

## Cấu trúc cây

Cây permission có 3 tầng:

```
Tầng 1: module:{Module}         — Nhóm module (Hệ thống, Giao việc, Cuộc họp, Lịch công tác)
Tầng 2: group:{resource}        — Nhóm resource/chức năng (Người dùng, Vai trò, ...)
Tầng 3: {resource}.{action}     — Action cụ thể (users.stats, users.index, ...)
```

Mỗi node có các trường:

| Trường | Kiểu | Mô tả |
|---------|------|-------|
| `id` | int | ID trong DB |
| `name` | string | `module:Core`, `group:users`, `users.stats` |
| `guard_name` | string | Luôn `"web"` |
| `description` | string | Nhãn tiếng Việt (dùng để hiển thị) |
| `sort_order` | int | Thứ tự sắp xếp |
| `parent_id` | int\|null | ID node cha |
| `children` | array | Các node con (rỗng nếu là action) |

## 4 module gốc

| name | description |
|------|-------------|
| `module:Core` | Hệ thống |
| `module:TaskAssignment` | Giao việc |
| `module:Meeting` | Cuộc họp |
| `module:Scheduling` | Lịch công tác |

## Cách dùng trên Frontend

### 1. Render cây phân quyền

Dùng `name` để phân biệt loại node:

- `name.startsWith("module:")` → node module, render làm accordion/collapsible cấp 1
- `name.startsWith("group:")` → node resource, render làm group cấp 2
- Còn lại → action, render làm checkbox item

### 2. Check permission

Để kiểm tra user có quyền không, gọi API user permissions hoặc dùng middleware response.

### 3. Gán quyền cho role

Khi gán quyền cho role, gửi danh sách `name` của các action node (tầng 3) — không gửi `module:` hay `group:`.

Ví dụ gán quyền "Xem danh sách người dùng" cho role:
```json
{
  "permissions": ["users.index"]
}
```

### 4. Mở rộng khi thêm module mới

Khi BE thêm module mới, FE không cần thay đổi code — cây tự động có thêm `module:{Tên mới}` ở tầng 1.

## Các module con chi tiết

### Core (Hệ thống)
- **users** — Người dùng
- **permissions** — Quyền
- **roles** — Vai trò
- **organizations** — Tổ chức
- **log-activities** — Nhật ký truy cập
- **settings** — Cấu hình hệ thống
- **sso-settings** — Cấu hình SSO
- **dashboard** — Tổng quan
- **notifications** — Thông báo
- **notifications.event-configs** — Cấu hình sự kiện thông báo
- **notifications.schedules** — Cấu hình lịch nhắc
- **notifications.logs** — Nhật ký gửi thông báo

### TaskAssignment (Giao việc)
- **task-assignment-departments** — Phòng ban giao việc
- **task-assignment-employees** — Nhân viên giao việc
- **task-assignment-types** — Loại văn bản giao việc
- **task-assignment-item-types** — Loại công việc
- **task-assignment-documents** — Văn bản giao việc
- **task-assignment-items** — Công việc
- **task-assignment-item-reports** — Báo cáo công việc
- **task-assignment-item-transfers** — Điều chuyển công việc
- **task-assignment-item-notes** — Ghi chú công việc
- **presentation** — Trình chiếu tổng quan công việc
- **my-received-tasks** — Công việc được giao
- **my-assigned-tasks** — Công việc đang giao

### Meeting (Cuộc họp)
- **meetings** — Cuộc họp
- **meeting-types** — Loại cuộc họp
- **meeting-locations** — Địa điểm họp
- **meeting-document-types** — Loại tài liệu họp
- **meeting-attendee-groups** — Nhóm đại biểu họp
- **meeting-attendees** — Đại biểu họp
- **meeting-agendas** — Chương trình họp
- **meeting-documents** — Tài liệu họp
- **meeting-participants** — Người tham dự họp
- **meeting-attendances** — Điểm danh họp
- **meeting-vote-topics** — Chương trình biểu quyết
- **meeting-vote-responses** — Phiếu biểu quyết
- **meeting-discussion-registrations** — Đăng ký thảo luận/chất vấn
- **meeting-personal-notes** — Ghi chú cá nhân họp
- **meeting-personal-note-attachments** — File ghi chú cá nhân
- **meeting-minutes-templates** — Template biên bản họp
- **meeting-invitation-templates** — Template giấy mời họp
- **meeting-settings** — Cấu hình cuộc họp

### Scheduling (Lịch công tác)
- **schedules** — Lịch công tác (cross-cutting, dùng cho Lái xe)
- **schedules-executive** — Lịch công tác - Thường trực
- **schedules-office** — Lịch công tác - Lãnh đạo
- **scheduling-employees** — Nhân viên lịch công tác
- **scheduling-employee-groups** — Nhóm nhân viên lịch công tác
- **scheduling-settings** — Cấu hình lịch công tác

## Response mẫu (rút gọn)

```json
{
  "data": [
    {
      "id": 405,
      "name": "module:Core",
      "guard_name": "web",
      "description": "Hệ thống",
      "sort_order": 0,
      "parent_id": null,
      "children": [
        {
          "id": 1,
          "name": "group:users",
          "guard_name": "web",
          "description": "Người dùng",
          "sort_order": 1,
          "parent_id": 405,
          "children": [
            {
              "id": 2,
              "name": "users.stats",
              "guard_name": "web",
              "description": "Người dùng - Thống kê",
              "sort_order": 0,
              "parent_id": 1,
              "children": []
            },
            {
              "id": 3,
              "name": "users.index",
              "guard_name": "web",
              "description": "Người dùng - Danh sách",
              "sort_order": 1,
              "parent_id": 1,
              "children": []
            }
          ]
        }
      ]
    }
  ]
}
```
