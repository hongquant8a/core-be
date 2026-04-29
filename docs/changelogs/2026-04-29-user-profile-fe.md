# User Profile — changelog FE

**Ngày:** 2026-04-29
**Đối tượng:** FE team (trang Hồ sơ cá nhân)

Tách thông tin cá nhân ra bảng `user_profiles` riêng. **2 endpoint mới**, không break code cũ.

---

## Endpoints mới

```
GET  /api/users/{user_id}/profile
PUT  /api/users/{user_id}/profile
```

Permission: reuse `users.show` (cho GET) + `users.update` (cho PUT). FE đã có 2 ability này → **không cần đụng CASL**.

## GET response

```json
{
  "success": true,
  "data": {
    "id": 12,
    "user_id": 5,
    "phone": "0901234567",
    "gender": "male",
    "birth_date": "1990-05-15",
    "citizen_id": "201234567890",
    "permanent_address": "123 ABC, Q.1, TP.HCM",
    "temporary_address": "456 DEF, Q.3, TP.HCM",
    "updated_at": "2026-04-29T10:00:00+07:00"
  }
}
```

Tất cả field cá nhân đều có thể `null` (user chưa fill).

## PUT request body

Tất cả optional. Partial update — chỉ field nào gửi mới update, field không gửi giữ nguyên.

```json
{
  "phone": "0901234567",
  "gender": "male",
  "birth_date": "1990-05-15",
  "citizen_id": "201234567890",
  "permanent_address": "123 ABC, Q.1, TP.HCM",
  "temporary_address": "456 DEF, Q.3, TP.HCM"
}
```

| Field | Type | Validation |
|-------|------|-----------|
| `phone` | string | max 20 ký tự |
| `gender` | string | `male` / `female` / `other` |
| `birth_date` | date `Y-m-d` | < hôm nay |
| `citizen_id` | string | max 20, **unique** (không trùng user khác, được phép trùng chính mình) |
| `permanent_address` | string | max 500 |
| `temporary_address` | string | max 500 |

## Lỗi validation thường gặp

| Case | Status | Message |
|------|--------|---------|
| `gender` không phải male/female/other | 422 | `Giới tính chỉ được là male, female hoặc other.` |
| `birth_date` ≥ hôm nay | 422 | `Ngày sinh phải trước ngày hiện tại.` |
| `citizen_id` đã có ở user khác | 422 | `Số CCCD/CMND này đã được sử dụng bởi người dùng khác.` |

Format error chuẩn:
```json
{ "success": false, "message": "...", "errors": { "field": ["..."] }, "code": "VALIDATION_ERROR" }
```

## TypeScript

```ts
interface UserProfile {
  id: number;
  user_id: number;
  phone: string | null;
  gender: 'male' | 'female' | 'other' | null;
  birth_date: string | null;          // 'YYYY-MM-DD'
  citizen_id: string | null;
  permanent_address: string | null;
  temporary_address: string | null;
  updated_at: string;                  // ISO 8601
}

interface UpdateUserProfileBody {
  phone?: string | null;
  gender?: 'male' | 'female' | 'other' | null;
  birth_date?: string | null;          // 'YYYY-MM-DD'
  citizen_id?: string | null;
  permanent_address?: string | null;
  temporary_address?: string | null;
}
```

## ⚠ Avatar — vẫn dùng endpoint cũ

Avatar **không** thuộc profile. Để upload/đổi avatar, vẫn gọi `PUT /api/users/{id}` với multipart `avatar` field như trước. Spatie media collection nằm trên model `User`, không move sang profile.

```ts
// Upload avatar
const fd = new FormData()
fd.append('avatar', file)
await api.put(`/api/users/${userId}`, fd, {
  headers: { 'Content-Type': 'multipart/form-data' },
})

// Update các field profile (riêng biệt, JSON)
await api.put(`/api/users/${userId}/profile`, {
  phone, gender, birth_date, ...
})
```

→ Trang "Hồ sơ cá nhân" có 2 component → 2 call riêng. Avatar có thể save ngay khi user click "Tải Lên Ảnh Mới" (không cần submit form), profile fields save khi click "Lưu".

## Migration cho FE

### 1. Trang "Hồ sơ cá nhân" / "Thông tin cá nhân"

Form fields hiện tại đang nằm rải đâu? Nếu trước đây field `phone` được fetch/update qua `GET/PUT /api/users/{id}` → giờ dời sang `/api/users/{id}/profile`.

```ts
// Trước
const { data } = await api.get(`/api/users/${userId}`)
// data.phone (cùng level với name, email)

// Sau
const [user, profile] = await Promise.all([
  api.get(`/api/users/${userId}`),
  api.get(`/api/users/${userId}/profile`),
])
// user.data.name, user.data.email
// profile.data.phone, profile.data.gender, ...
```

### 2. Tab "Thông tin cá nhân" như UI mockup

Form chỉ submit profile fields:
```ts
await api.put(`/api/users/${userId}/profile`, {
  phone, gender, birth_date, citizen_id,
  permanent_address, temporary_address,
})
```

Tab "Tổng Quan" / "Cài Đặt Bảo Mật" vẫn dùng endpoint `users` cũ (đổi mật khẩu, role, status).

### 3. Hiển thị `phone` ở chỗ khác

Nếu FE đang hiển thị `user.phone` ở list/detail card user → tiếp tục dùng được. BE giữ `User->phone` accessor đọc qua profile, response của `GET /api/users/{id}` vẫn trả `phone` field như cũ (qua model resource).

→ **Kiểm tra UserResource** ở response `GET /api/users` và `/api/users/{id}` xem có expose `phone` không. Nếu có thì BE cần loop qua profile để lấy → hiện chưa làm. **Action item BE**: nếu FE cần `phone` trong response user list, BE sẽ thêm `phone` field vào UserResource (đọc qua accessor).

## Backward compat

- Tạo user qua `POST /api/users` với body có `phone` → vẫn work, BE tự routing vào profile.
- Update user qua `PUT /api/users/{id}` với body có `phone` → vẫn work, routing vào profile.
- `GET /api/users/{id}` response — chưa kiểm UserResource có `phone` không. Nếu FE cần, báo BE add.

## Edge cases

| Case | BE behavior | FE handle |
|------|-------------|-----------|
| User mới tạo | UserObserver tự create empty profile | GET trả tất cả null trừ id/user_id |
| User legacy chưa có profile (data cũ trước migration) | Endpoint GET tự `firstOrCreate` lazy | Không thấy gì khác |
| `citizen_id` trùng chính user đó (no-op) | OK 200 | OK |
| User upload avatar | Spatie media collection trên User (KHÔNG move sang profile) | Endpoint avatar giữ nguyên |
| User xóa | FK cascade → profile auto xóa | OK |

## Tests đã có (BE)

10 feature tests trong `tests/Feature/Core/UserProfileTest.php`:
- `profile_auto_created_when_user_created`
- `show_returns_existing_profile`
- `show_creates_profile_if_missing` (legacy user)
- `update_persists_all_fields`
- `update_partial_does_not_clear_other_fields`
- `update_validates_birth_date_before_today`
- `update_validates_gender_enum`
- `update_rejects_duplicate_citizen_id`
- `update_allows_same_citizen_id_for_self`
- `user_phone_accessor_reads_from_profile`
