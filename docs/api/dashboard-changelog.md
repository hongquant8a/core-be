# Changelog – Dashboard API

## 2026-04-21 – Thêm 4 endpoint thống kê cho dashboard

Bổ sung các thống kê còn thiếu cho dashboard (biểu đồ theo tháng, top N, phân bố). Tất cả đều **nhận filter `from_date` + `to_date`** — FE bắn 1 bộ filter chung cho toàn section.

**Không tạo permission mới** — reuse `log-activities.stats` + `users.stats` đã có.

---

## 1. Thống kê nhật ký theo mốc thời gian (line chart)

**1 endpoint duy nhất.** BE tự chọn grain theo độ dài range, FE chỉ bắn `from_date` + `to_date` và dựa vào `granularity` trong response để format trục X.

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/stats/timeline` |
| **Permission** | `log-activities.stats` |

**Rule grain:**
- Diff ≤ 62 ngày (vd "1 tháng gần nhất", "30 ngày") → `granularity = "day"`, `period` format `YYYY-MM-DD`
- Diff > 62 ngày (vd "6 tháng", "1 năm") → `granularity = "month"`, `period` format `YYYY-MM`
- Thiếu `from_date` / `to_date` → mặc định 12 tháng gần nhất, grain `month`

**Query:** `from_date`, `to_date`, `organization_id`, `search`, `method_type`, `status_code`

**Response grain month (vd "6 tháng gần nhất"):**
```json
{
  "success": true,
  "data": {
    "granularity": "month",
    "data": [
      { "period": "2025-11", "total": 1200, "views": 1000, "creates": 120, "updates": 60, "deletes": 20 },
      { "period": "2026-04", "total": 15008, "views": 13000, "creates": 1500, "updates": 400, "deletes": 108 }
    ]
  }
}
```

**Response grain day (vd "30 ngày gần nhất"):**
```json
{
  "success": true,
  "data": {
    "granularity": "day",
    "data": [
      { "period": "2026-04-01", "total": 120, "views": 100, "creates": 15, "updates": 3, "deletes": 2 },
      { "period": "2026-04-21", "total": 340, "views": 290, "creates": 40, "updates": 8, "deletes": 2 }
    ]
  }
}
```

Chỉ trả mốc có dữ liệu — FE cần pad mốc trống nếu muốn chart liên tục.

---

## 2. Top N người dùng hoạt động

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/stats/top-users` |
| **Permission** | `log-activities.stats` |

**Query:** `limit` (mặc định 5) + các filter của `/stats`.

**Response:**
```json
{
  "success": true,
  "data": [
    { "user_id": 12, "name": "Sở Nội vụ PLĐVHC", "email": "...", "user_name": "...", "total": 2448 }
  ]
}
```

---

## 3. Top N tổ chức hoạt động

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/stats/top-organizations` |
| **Permission** | `log-activities.stats` |

**Query:** `limit` (mặc định 5) + các filter của `/stats`.

**Response:**
```json
{
  "success": true,
  "data": [
    { "organization_id": 7, "name": "Sở Nội vụ", "slug": "so-noi-vu", "total": 4428 }
  ]
}
```

---

## 4. Phân bố người dùng theo tổ chức (pie chart)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users/stats/by-organization` |
| **Permission** | `users.stats` |

Đếm user theo **primary org** (`user_preferences.current_organization_id`), mỗi user đếm đúng 1 lần. User chưa có primary org gom vào slice `"Khác"` (`organization_id: null`).

**Query:** `limit` (mặc định 10), `status`, `search`.

**Response:**
```json
{
  "success": true,
  "data": [
    { "organization_id": 1, "name": "Sở Nội vụ", "total": 260 },
    { "organization_id": 5, "name": "UBND phường Hải Châu", "total": 18 },
    { "organization_id": null, "name": "Khác", "total": 27 }
  ]
}
```

Tổng `total` = tổng user thỏa filter.

---

## Tổng quan widget → endpoint

| Widget | Endpoint |
|---|---|
| Card "Hoạt động" (28608) | `GET /api/log-activities/stats` (cũ) — `total` |
| Card "Người dùng" (305) | `GET /api/users/stats` (cũ) — `total` |
| Card "Tổ chức" (118) | `GET /api/organizations/stats` (cũ) — `total` |
| Line chart theo thời gian (auto grain day/month) | **`GET /api/log-activities/stats/timeline`** (mới) |
| Top N người dùng | **`GET /api/log-activities/stats/top-users`** (mới) |
| Top N tổ chức | **`GET /api/log-activities/stats/top-organizations`** (mới) |
| Pie chart hành động (Xem/Thêm/Sửa/Xóa) | `GET /api/log-activities/stats` (cũ) — `views/creates/updates/deletes` |
| Pie chart user theo tổ chức | **`GET /api/users/stats/by-organization`** (mới) |
| User active/inactive | `GET /api/users/stats` (cũ) |
| Cây tổ chức | `GET /api/organizations/tree` (cũ) |

---

## Lưu ý FE

- **Filter `from_date` + `to_date` dùng chung cho cả section** — khi user đổi khoảng thời gian ở selector "Hoạt động theo tháng", gọi lại cả 4 endpoint + 3 card trên cùng với filter này.
- Card tổ chức (118) **không** áp filter thời gian (tổng hệ thống).
- Ngày format `YYYY-MM-DD`. Tháng trong response `by-month` format `YYYY-MM`.
