# User Profile + Activity chart — changelog FE

**Ngày:** 2026-04-29
**Đối tượng:** FE team

3 việc trên trang **Hồ sơ cá nhân** giờ làm được:

1. Update form combined (user + profile fields) — 1 call
2. Hiển thị "Đăng nhập cuối"
3. Chart "Xu hướng hoạt động" 12 tháng

---

## 1. Form "Thông tin cá nhân"

Form trộn user fields (name, email) + profile fields (phone, gender, ngày sinh, CCCD, địa chỉ) → submit **1 call duy nhất** qua endpoint cũ:

```ts
await api.put(`/api/users/${userId}`, {
  name, email, user_name, status,            // user
  phone, gender, birth_date, citizen_id,     // profile (BE tự lưu vào bảng riêng)
  permanent_address, temporary_address,
})
```

Atomic: validation fail bất kỳ field → 422, không lưu gì hết.

### Profile fields

| Field | Type | Validation |
|-------|------|------------|
| `phone` | string | max 20 |
| `gender` | string | `male` / `female` / `other` |
| `birth_date` | date `YYYY-MM-DD` | < hôm nay |
| `citizen_id` | string | max 20, **unique** giữa các user |
| `permanent_address` | string | max 500 |
| `temporary_address` | string | max 500 |

Tất cả nullable. Partial update — field nào không gửi giữ nguyên.

### Lỗi 422 hay gặp

| Case | Message |
|------|---------|
| `gender` không phải male/female/other | `Giới tính chỉ được là male, female hoặc other.` |
| `birth_date` ≥ hôm nay | `Ngày sinh phải trước ngày hiện tại.` |
| `citizen_id` đã có ở user khác | `Số CCCD/CMND này đã được sử dụng bởi người dùng khác.` |

### Endpoint riêng (optional)

Nếu trang/tab chỉ submit profile, không đụng user fields:

```ts
await api.get(`/api/users/${userId}/profile`)
await api.put(`/api/users/${userId}/profile`, { phone, gender, ... })
```

Permission: cùng `users.show` / `users.update` — **không đụng CASL**.

### Avatar — endpoint cũ giữ nguyên

```ts
const fd = new FormData()
fd.append('avatar', file)
await api.put(`/api/users/${userId}`, fd, {
  headers: { 'Content-Type': 'multipart/form-data' },
})
```

Avatar có thể save NGAY khi user click "Tải Lên" (tách riêng với form chính).

---

## 2. Field "Đăng nhập cuối"

`GET /api/user` (endpoint "me") trả thêm `last_login_at` ở response:

```json
{
  "data": {
    "user": {
      "id": 5,
      "name": "Nguyễn Văn A",
      "...": "...",
      "last_login_at": "2026-04-29T08:30:00+07:00"
    },
    "roles": [...],
    "permissions": [...]
  }
}
```

- ISO 8601 hoặc `null` nếu user chưa từng login.
- **Chỉ ở `/api/user`** — endpoint `/api/users/{id}` và list users KHÔNG có field này.

---

## 3. Chart "Xu hướng hoạt động" — 12 tháng theo user

```ts
const { data } = await api.get('/api/log-activities/stats/timeline', {
  params: {
    from_date: `${year}-01-01`,
    to_date:   `${year}-12-31`,
    granularity: 'month',
    user_id: userId,    // bỏ qua nếu chart hệ thống
  },
})
```

Response:

```json
{
  "success": true,
  "data": {
    "granularity": "month",
    "data": [
      { "period": "2026-01", "total": 45, "views": 40, "creates": 3, "updates": 2, "deletes": 0 },
      { "period": "2026-02", "total": 52, "views": 48, ... },
      // ... đủ 12 phần tử
      { "period": "2026-12", "total": 0, "views": 0, ... }
    ]
  }
}
```

→ FE map `period` ('YYYY-MM') sang label `T1..T12`. Dùng `total` cho line chart. Mốc không có activity → BE tự pad = 0 (luôn đủ 12 buckets).

Đổi `granularity=day` + range hẹp → chart theo ngày (vd 30 ngày gần nhất).

## Edge cases

| Case | BE handle | FE handle |
|------|-----------|-----------|
| User mới tạo | Tự có profile rỗng | GET `/profile` trả tất cả null trừ id/user_id |
| `citizen_id` trùng chính user (no-op) | OK 200 | OK |
| User chưa từng login | `last_login_at: null` | Hiển thị "—" / "Chưa đăng nhập" |
| Tháng không có activity | `total: 0` (padded) | Vẫn vẽ điểm trên chart |
| Filter `from_date` + `to_date` không truyền | Timeline trả ALL-time | Trang Dashboard không cần đổi |

## Backward compat

- `POST /api/users` / `PUT /api/users/{id}` với body cũ có `phone` → vẫn work.
- Trang dashboard cũ gọi `/timeline` không có date filter → không break (fallback ALL-time).
- `/api/users/{id}` response giữ nguyên — không add `last_login_at`.
