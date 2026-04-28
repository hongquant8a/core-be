# Changelog FE — LogActivity Dashboard snapshot endpoint

**Ngày:** 2026-04-28
**Branch:** `main`
**Đối tượng:** FE team (trang Dashboard "Thống kê hoạt động")

Gộp 4 request dashboard hiện tại (`stats`, `timeline`, `top-users`, `top-organizations`) thành **1 endpoint snapshot duy nhất** để 4 con số luôn khớp nhau.

**Không phải breaking change.** 4 endpoint cũ giữ nguyên, FE migrate dần.

---

## 1. Vấn đề trước đây

Trang dashboard gọi 4 request liên tiếp. Middleware `LogActivity` tự log MỖI request → mỗi call dashboard cũng được log lại → giữa các call, count tăng dần. Kết quả: 4 chỉ số hiển thị KHÁC NHAU dù lý thuyết phải bằng nhau.

Ví dụ thực tế từ network tab:
- `/stats` → `total: 2107`
- `/stats/top-users` → Admin `total: 2097`
- `/stats/timeline` → tooltip 04/2026 `total: 2112`

→ Lệch 5 (giữa stats và timeline) là do 5 request dashboard kẹp giữa được middleware log.

## 2. Giải pháp

Endpoint MỚI gom tất cả vào 1 response:

```
GET /api/log-activities/stats/dashboard
```

Permission: `log-activities.stats` (giống endpoint cũ — **không cần thêm CASL ability**).

## 3. Query params

Tất cả optional. Sync 100% với các endpoint cũ:

| Param | Type | Default | Áp dụng cho |
|-------|------|---------|-------------|
| `search` | string | — | stats, top_users, top_organizations |
| `organization_id` | int | — | stats, top_users, top_organizations |
| `from_date` | date `Y-m-d` | — | stats, top_users, top_organizations |
| `to_date` | date `Y-m-d` | — | stats, top_users, top_organizations |
| `method_type` | string `GET\|POST\|PUT\|PATCH\|DELETE` | — | stats, top_users, top_organizations |
| `status_code` | int | — | stats, top_users, top_organizations |
| `granularity` | `day` \| `month` | `month` | timeline |
| `top_users_limit` | int 1-100 | `5` | top_users |
| `top_organizations_limit` | int 1-100 | `5` | top_organizations |

**Lưu ý:** `timeline` luôn là ALL-time (từ log sớm nhất đến hiện tại), filter ngày KHÔNG áp dụng cho timeline (giữ behavior endpoint cũ). Nếu sau này muốn filter timeline theo date range → cần BE update.

## 4. Response shape

```json
{
  "success": true,
  "data": {
    "stats": {
      "total": 2107,
      "views": 2089,
      "creates": 14,
      "updates": 4,
      "deletes": 0
    },
    "timeline": {
      "granularity": "month",
      "data": [
        { "period": "2026-04", "total": 2107, "views": 2089, "creates": 14, "updates": 4, "deletes": 0 }
      ]
    },
    "top_users": [
      { "user_id": 1, "name": "Admin", "email": "admin@...", "user_name": "admin", "total": 2097 }
    ],
    "top_organizations": [
      { "organization_id": 1, "name": "Sở Nội vụ", "slug": "so-noi-vu", "total": 2107 }
    ]
  }
}
```

Mỗi sub-section có shape **giống y hệt** endpoint cũ tương ứng — chỉ là gom lại vào 1 object.

## 5. Migration (FE)

### Trước

```ts
const [stats, timeline, topUsers, topOrgs] = await Promise.all([
  api.get('/api/log-activities/stats'),
  api.get('/api/log-activities/stats/timeline?granularity=month'),
  api.get('/api/log-activities/stats/top-users?limit=5'),
  api.get('/api/log-activities/stats/top-organizations?limit=5'),
])
```

### Sau

```ts
const { data } = await api.get('/api/log-activities/stats/dashboard', {
  params: {
    granularity: 'month',
    top_users_limit: 5,
    top_organizations_limit: 5,
  },
})
const { stats, timeline, top_users, top_organizations } = data
```

### Lợi ích

- 1 request thay vì 4 → page load nhanh hơn ~3 round-trip.
- 4 con số HIỂN THỊ luôn khớp (cùng snapshot).
- Dropdown "Theo tháng / Theo ngày" chỉ cần đổi `granularity` query param + refetch.

## 6. Type definitions (TS gợi ý)

```ts
interface DashboardStats {
  total: number;
  views: number;       // GET
  creates: number;     // POST
  updates: number;     // PUT/PATCH
  deletes: number;     // DELETE
}

interface DashboardTimelineBucket {
  period: string;      // 'YYYY-MM' nếu granularity=month, 'YYYY-MM-DD' nếu day
  total: number;
  views: number;
  creates: number;
  updates: number;
  deletes: number;
}

interface DashboardTopUser {
  user_id: number;
  name: string | null;
  email: string | null;
  user_name: string | null;
  total: number;
}

interface DashboardTopOrganization {
  organization_id: number;
  name: string | null;
  slug: string | null;
  total: number;
}

interface LogActivityDashboardResponse {
  stats: DashboardStats;
  timeline: {
    granularity: 'day' | 'month';
    data: DashboardTimelineBucket[];
  };
  top_users: DashboardTopUser[];
  top_organizations: DashboardTopOrganization[];
}
```

## 7. Backward compat

Tất cả 4 endpoint cũ vẫn hoạt động bình thường. Không cần xóa code FE đang gọi chúng nếu đang dùng ở chỗ khác.

| Endpoint cũ | Status |
|-------------|--------|
| `GET /api/log-activities/stats` | Còn dùng được |
| `GET /api/log-activities/stats/timeline` | Còn dùng được |
| `GET /api/log-activities/stats/top-users` | Còn dùng được |
| `GET /api/log-activities/stats/top-organizations` | Còn dùng được |

→ Recommended: dashboard page migrate sang endpoint mới; chỗ nào chỉ cần 1-2 chỉ số có thể giữ endpoint cũ.

## 8. Tests đã có (BE)

4 feature tests trong `tests/Feature/Core/LogActivityDashboardTest.php`:

- `test_dashboard_returns_all_four_sections` — response có đủ shape
- `test_dashboard_stats_and_timeline_count_same_dataset` — `stats.total` bằng tổng các bucket trong `timeline.data`
- `test_dashboard_top_users_subset_matches_admin_count` — anonymous logs (`user_id=null`) bị loại khỏi top_users, đếm trong stats.total
- `test_dashboard_respects_filter_params` — `?method_type=GET` áp dụng nhất quán trên stats + top_users
