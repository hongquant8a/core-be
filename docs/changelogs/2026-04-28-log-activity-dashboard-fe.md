# LogActivity dashboard — changelog FE

**Ngày:** 2026-04-28
**Đối tượng:** FE team (trang Thống kê hoạt động)

Endpoint mới gộp 4 request dashboard thành 1, và stats giờ tính cả anonymous traffic.

---

## Endpoint

```
GET /api/log-activities/stats/dashboard
```

Permission: `log-activities.stats` (đã có sẵn — không cần đụng CASL).

Query params (tất cả optional, sync với endpoint cũ):

- `granularity` — `day` | `month` (default `month`)
- `top_users_limit` — default `5`
- `top_organizations_limit` — default `5`
- `from_date`, `to_date`, `organization_id`, `method_type`, `status_code`, `search` — filter cho `stats` + `top_users` + `top_organizations` (timeline luôn ALL-time, không nhận date filter)

## Response

```json
{
  "success": true,
  "data": {
    "stats": { "total": 2362, "views": 2340, "creates": 15, "updates": 7, "deletes": 0 },
    "timeline": {
      "granularity": "month",
      "data": [{ "period": "2026-04", "total": 2362, "views": 2340, "creates": 15, "updates": 7, "deletes": 0 }]
    },
    "top_users": [
      { "user_id": 11, "name": "Admin", "email": "...", "user_name": "admin", "total": 2351 },
      { "user_id": null, "name": "Khách", "email": null, "user_name": null, "total": 11 }
    ],
    "top_organizations": [
      { "organization_id": 1, "name": "Default", "slug": "default", "total": 2354 },
      { "organization_id": null, "name": "Không xác định", "slug": null, "total": 8 }
    ]
  }
}
```

## Điểm cần chú ý

**1. Sum khớp**: `sum(top_users[].total) === stats.total`. Không cần tự cộng bù anonymous nữa.

**2. Row "Khách"** trong `top_users` (`user_id: null`, `name: "Khách"`) gộp các log không có user (login form, public endpoint, deploy webhook…). Tương tự `"Không xác định"` cho `top_organizations`. Render:
- Ẩn avatar / không cho click
- Hiển thị bình thường, không phải lỗi

**3. Sum của top khác stats không có nghĩa là bug** — có thể do `top_*_limit` cắt mất user/org xếp ngoài top N. Tăng limit nếu cần đủ.

## Migration

Trước: 4 request song song.

```ts
const [stats, timeline, topUsers, topOrgs] = await Promise.all([
  api.get('/api/log-activities/stats'),
  api.get('/api/log-activities/stats/timeline?granularity=month'),
  api.get('/api/log-activities/stats/top-users?limit=5'),
  api.get('/api/log-activities/stats/top-organizations?limit=5'),
])
```

Sau: 1 request.

```ts
const { data } = await api.get('/api/log-activities/stats/dashboard', {
  params: { granularity: 'month', top_users_limit: 5, top_organizations_limit: 5 },
})
const { stats, timeline, top_users, top_organizations } = data
```

→ Page load nhanh hơn ~3 round-trip. 4 con số đảm bảo cùng snapshot (không lệch do middleware tự log mỗi request).

## Type

```ts
interface DashboardResponse {
  stats: { total: number; views: number; creates: number; updates: number; deletes: number };
  timeline: { granularity: 'day' | 'month'; data: TimelineBucket[] };
  top_users: TopUser[];
  top_organizations: TopOrganization[];
}

interface TopUser {
  user_id: number | null;          // null = row "Khách"
  name: string | null;             // "Khách" khi user_id=null
  email: string | null;
  user_name: string | null;
  total: number;
}

interface TopOrganization {
  organization_id: number | null;  // null = row "Không xác định"
  name: string | null;
  slug: string | null;
  total: number;
}

interface TimelineBucket {
  period: string;                  // 'YYYY-MM' hoặc 'YYYY-MM-DD'
  total: number;
  views: number;
  creates: number;
  updates: number;
  deletes: number;
}
```

## Backward compat

4 endpoint cũ (`/stats`, `/stats/timeline`, `/stats/top-users`, `/stats/top-organizations`) vẫn hoạt động — nhưng `top-users`/`top-organizations` cũ giờ cũng include row "Khách"/"Không xác định" như endpoint mới (cùng service). FE đang dùng phải kiểm tra xử lý `user_id: null` / `organization_id: null` không bị crash.
