# API: Tổng số lịch theo module type trong tuần

Status: **Đã implement**

## Endpoint

```
GET /api/schedules/week-counts
```

Yêu cầu auth (Bearer token). Middleware: `auth:sanctum`, `set.permissions.team`, `ensure.route.org`, `log.activity`, `schedule.module:index` (permission `schedules.index`).

## Headers

| Header              | Type   | Required | Mô tả                     |
|---------------------|--------|----------|---------------------------|
| `Authorization`     | string | Yes      | Bearer token              |
| `X-Organization-Id` | int    | Yes      | ID tổ chức làm việc       |

## Query Params

| Param             | Type   | Required | Mô tả                                      |
|-------------------|--------|----------|--------------------------------------------|
| `anchor_date`     | string | Yes      | Ngày neo để xác định tuần (YYYY-MM-DD)      |

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
| `personal`   | int  | Số lịch của user hiện tại (chủ trì + lái xe + tham dự) trong tuần, gộp cả 2 module. Có auth nên luôn được tính |
| `executive`  | int  | Số lịch Thường trực trong tuần |
| `office`     | int  | Số lịch Văn phòng trong tuần |

## Logic

1. Từ `anchor_date` → tính thứ 2 (`startOfWeek()`) và chủ nhật (`endOfWeek()`) của tuần
2. `COUNT(*) GROUP BY module_type WHERE date_time BETWEEN start AND end`
3. `personal`: đếm thêm lịch mà `auth()->id()` là `host_id` hoặc `driver_id` hoặc nằm trong `schedule_notification_recipients`

## Source

- Route: `app/Modules/Scheduling/Routes/schedule.php`
- Controller: `App\Modules\Scheduling\Controllers\ScheduleController::weekCounts()`
- Service: `App\Modules\Scheduling\Services\ScheduleService::weekCounts()`
