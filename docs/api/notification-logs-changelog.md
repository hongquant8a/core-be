# Changelog – Notification Logs API

## 2026-04-21 – Thêm destroy + bulk-delete + export

Bổ sung 3 endpoint cho trang Nhật ký thông báo. Cùng scope module + organization như các endpoint `/logs` hiện có.

### 1. Xoá 1 thông báo

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment/notification-config/logs/{id}` |
| **Permission** | `notifications.logs.destroy` |

**Response 200:**
```json
{ "success": true, "message": "Đã xóa thông báo thành công!" }
```

**Lỗi:** 404 nếu ID không tồn tại hoặc nằm ngoài scope (khác org/module). Notification deliveries cascade xoá theo.

### 2. Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment/notification-config/logs/export` |
| **Permission** | `notifications.logs.export` |
| **Response** | File Excel (`.xlsx`) — filename `notification-logs.xlsx` |

Query filter **dùng y hệt `/logs`**: `search`, `user_id`, `event_key`, `notifiable_type`, `notifiable_id`, `from_date`, `to_date`, `delivery_status`, `channel`.

FE gọi bình thường với cùng filter đang áp, browser tự download file.

### 3. Xoá hàng loạt

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment/notification-config/logs/bulk-delete` |
| **Permission** | `notifications.logs.bulkDestroy` |

**Body:**
```json
{ "ids": [1, 2, 3] }
```

**Response 200:**
```json
{ "success": true, "message": "Đã xóa thành công 3 thông báo!" }
```

**Lỗi validate:**
- `ids.required` — "Danh sách thông báo không được để trống."
- `ids.*.exists` — ID không tồn tại.

**Lưu ý:** BE tự lọc ID ngoài scope (khác org/module) → xoá an toàn. Count trả về là số bản ghi thực sự bị xoá, có thể nhỏ hơn `ids.length` nếu có ID ngoài scope. Notification deliveries cascade xoá theo.

---

## Permission

3 permission mới (`destroy`, `bulkDestroy`, `export`). Đã thêm vào `PermissionSeeder`, Super Admin + Admin tự nhận sau khi chạy:
```bash
php artisan db:seed --class=PermissionSeeder
```
