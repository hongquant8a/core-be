# API: Tổng số lịch theo module type trong tuần

Status: **Đã implement**

## Endpoint

```
GET /api/schedules/week-counts
```

Public — không yêu cầu auth. Nếu có auth, tự động tính thêm `personal`.

## Query Params

| Param             | Type   | Required | Mô tả                                      |
|-------------------|--------|----------|--------------------------------------------|
| `anchor_date`     | string | Yes      | Ngày neo để xác định tuần (YYYY-MM-DD)      |
| `organization_id` | int    | No       | Lọc theo tổ chức (null = tất cả)            |

## Response

```json
{
  "success": true,
  "data": {
    "personal": 5,
    "executive": 12,
    "office": 8
  }
}
```

| Field        | Type | Mô tả |
|--------------|------|-------|
| `personal`   | int  | Số lịch của user hiện tại (chủ trì + lái xe + tham dự) trong tuần, gộp cả 2 module. Không có auth → 0 |
| `executive`  | int  | Số lịch Thường trực trong tuần |
| `office`     | int  | Số lịch Văn phòng trong tuần |

## Logic

1. Từ `anchor_date` → tính thứ 2 (`startOfWeek()`) và chủ nhật (`endOfWeek()`) của tuần
2. `COUNT(*) GROUP BY module_type WHERE date_time BETWEEN start AND end`
3. `personal`: đếm thêm lịch mà `auth()->id()` là `host_id` hoặc `driver_id` hoặc nằm trong `schedule_notification_recipients`

## Source

- Route: `routes/api.php:73`
- Controller: `App\Modules\Scheduling\Controllers\ScheduleController::weekCounts()`
- Service: `App\Modules\Scheduling\Services\ScheduleService::weekCounts()`
