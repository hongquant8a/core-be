# Changelog – Dashboard API

## 2026-04-21 – Thêm 4 endpoint thống kê cho dashboard

Bổ sung các thống kê còn thiếu cho dashboard (timeline, top N, phân bố).

**Tất cả endpoint stat ALL-TIME**, không nhận date filter. Dropdown "theo tháng / theo ngày" chỉ là selector grain cho line chart (truyền `granularity` param).

**Không tạo permission mới** — reuse `log-activities.stats` + `users.stats` đã có.

---

## 1. Timeline nhật ký (line chart)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/stats/timeline` |
| **Permission** | `log-activities.stats` |

**Query:** `granularity` — `"day"` hoặc `"month"` (mặc định `"month"`).

**Range:** từ mốc **sớm nhất có log** → **hiện tại**, BE pad 0 cho mốc trống để chart liên tục. Nếu chưa có log nào → `data: []`.

**Response grain month:**
```json
{
  "success": true,
  "data": {
    "granularity": "month",
    "data": [
      { "period": "2026-01", "total": 2100, "views": 1800, "creates": 200, "updates": 80, "deletes": 20 },
      { "period": "2026-04", "total": 15008, "views": 13000, "creates": 1500, "updates": 400, "deletes": 108 }
    ]
  }
}
```

**Response grain day:**
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

BE pad full range từ log sớm nhất → hiện tại, mốc không có log trả `total: 0`. FE chỉ cần plot thẳng.

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

- Tất cả endpoint dashboard **stat all-time**, không nhận date filter.
- Dropdown "theo tháng / theo ngày" chỉ đổi `granularity` param cho `/stats/timeline` — không ảnh hưởng widget khác.
- `period` format theo grain: `YYYY-MM` (month) hoặc `YYYY-MM-DD` (day).
