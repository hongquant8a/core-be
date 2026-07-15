# API Nhật ký truy cập (LogActivity) – Core

> Cập nhật lần cuối: 15/07/2026 — bổ sung 6 endpoint chưa được doc (self-service `/me`, `/me/stats/timeline`, và 4 endpoint dashboard/thống kê nâng cao), bổ sung filter `organization_id`/`user_id` còn thiếu.

Quản lý nhật ký truy cập: thống kê, dashboard, timeline, top user/tổ chức, danh sách, chi tiết, xuất Excel, xóa, xóa hàng loạt, xóa theo khoảng thời gian, xóa toàn bộ.

**Base path:** `/api/log-activities`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/stats` |
| **Query** | `search`, `organization_id`, `user_id`, `from_date`, `to_date`, `method_type` (GET/POST/PUT/PATCH/DELETE hoặc alias view/create/update/delete), `status_code`, `sort_by`, `sort_order`, `limit`. |
| **Response** | `{ "total": 100 }` |

---

## Dashboard tổng hợp (1 request)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/stats/dashboard` |
| **Permission** | `log-activities.stats` |
| **Query** | Giống Thống kê, thêm `granularity` (`day`\|`month`, mặc định `month`), `top_users_limit` (1-100, mặc định 5), `top_organizations_limit` (1-100, mặc định 5). |
| **Response** | `{ "stats": {...}, "timeline": {...}, "top_users": [...], "top_organizations": [...] }` — gộp 4 API dưới đây trong 1 lần gọi, tránh sai lệch số liệu do middleware tự log mỗi request. |

---

## Timeline thống kê (line chart)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/stats/timeline` |
| **Permission** | `log-activities.stats` |
| **Query** | `granularity` (`day`\|`month`, mặc định `month`). |
| **Response** | `{ "granularity": "month", "data": [{ "period": "2026-01", "total": 2100, "views": 1800, "creates": 200, "updates": 80, "deletes": 20 }] }` |

---

## Top người dùng hoạt động nhiều nhất

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/stats/top-users` |
| **Permission** | `log-activities.stats` |
| **Query** | `limit` (mặc định 5, tối đa 50), `from_date`, `to_date`, `organization_id`. |
| **Response** | `[{ "user_id": 1, "name": "Nguyễn Văn A", "email": "...", "user_name": "nva", "total": 2448 }]` |

---

## Top tổ chức hoạt động nhiều nhất

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/stats/top-organizations` |
| **Permission** | `log-activities.stats` |
| **Query** | `limit` (mặc định 5, tối đa 50), `from_date`, `to_date`. |
| **Response** | `[{ "organization_id": 1, "name": "Sở Nội vụ", "slug": "so-noi-vu", "total": 2448 }]` |

---

## Self-service (nhật ký của chính mình)

Không cần permission Spatie — Controller tự ép `user_id = auth()->id()`.

### Danh sách nhật ký của tôi

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/me` |
| **Query** | Giống "Danh sách nhật ký" (trừ `user_id`, đã bị ép cứng). |

### Timeline nhật ký của tôi

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/me/stats/timeline` |
| **Query** | `granularity` (`day`\|`month`, mặc định `month`). |

---

## Danh sách nhật ký

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities` |
| **Query** | `search` (description, route, ip_address, country, user_type), `organization_id`, `user_id`, `from_date`, `to_date`, `method_type`, `status_code`, `sort_by`, `sort_order`, `limit`. |
| **Response** | Paginated collection (LogActivityResource). |

---

## Chi tiết nhật ký

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/{id}` |
| **UrlParam** | `id` — ID nhật ký. |
| **Response** | Object nhật ký (LogActivityResource). |

---

## Xuất nhật ký

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/log-activities/export` |
| **Query** | `search`, `from_date`, `to_date`, `method_type`, `status_code`, `sort_by`, `sort_order`. |
| **Response** | File Excel `log-activities.xlsx`. |

---

## Xóa nhật ký

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/log-activities/{id}` |
| **Response** | `{ "message": "Đã xóa nhật ký thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/log-activities/bulk-delete` |
| **Body** | `ids` (array) — danh sách ID nhật ký. |
| **Response** | `{ "message": "Đã xóa thành công N nhật ký!" }`. |

---

## Xóa theo khoảng thời gian

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/log-activities/delete-by-date` |
| **Body** | `from_date` (required, Y-m-d), `to_date` (required, Y-m-d). |
| **Response** | `{ "message": "Đã xóa thành công N nhật ký trong khoảng thời gian!" }`. |

---

## Xóa toàn bộ

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/log-activities/clear` |
| **Response** | `{ "message": "Đã xóa toàn bộ N nhật ký!" }`. |
