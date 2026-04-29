# FCM multi-device — changelog FE

**Ngày:** 2026-04-29
**Đối tượng:** FE team

1 user giờ có thể đăng nhập nhiều thiết bị/browser cùng lúc, đều nhận được push. Khi logout chỉ tắt push thiết bị đó.

---

## Thay đổi

`users.fcm_token` (1 cột) → bảng riêng `fcm_tokens (user_id, device_id, fcm_token, device_type, last_used_at)`.

FE không cần endpoint mới. Chỉ cần **gửi đúng header trên mỗi request**.

## Headers FE phải gửi

| Header | Giá trị | Khi nào |
|--------|---------|---------|
| `Authorization: Bearer <token>` | Sanctum token | Như cũ |
| `X-Organization-Id` | id org đang work | Như cũ |
| `X-Device-Id` | **UUID stable** của browser/device (lưu localStorage) | **MỚI** — bắt buộc nếu muốn push |
| `X-FCM-Token` | Token Firebase | Như cũ — gửi khi có |
| `X-Device-Type` | `web` / `android` / `ios` | Optional |

### `X-Device-Id` — generate ở FE

Web app: tạo UUID 1 lần khi user mở app lần đầu, lưu localStorage:

```ts
function getOrCreateDeviceId(): string {
  let id = localStorage.getItem('device_id')
  if (!id) {
    id = crypto.randomUUID()
    localStorage.setItem('device_id', id)
  }
  return id
}

// Axios interceptor — gắn cứng vào mọi request
axios.interceptors.request.use(config => {
  config.headers['X-Device-Id'] = getOrCreateDeviceId()
  if (fcmToken) config.headers['X-FCM-Token'] = fcmToken
  config.headers['X-Device-Type'] = 'web'
  return config
})
```

→ User mở Chrome (device_id = A) + Firefox (device_id = B) → 2 row trong DB → cả 2 nhận push.
→ User đổi browser, clear localStorage → tạo device_id mới → row cũ thành orphan (BE cleanup tự khi Firebase báo invalid).

## Logout

Khi gọi `POST /api/auth/logout`, **gửi `X-Device-Id`** để BE chỉ xóa FCM của thiết bị này (không đụng các device khác).

```ts
await api.post('/api/auth/logout', null, {
  headers: { 'X-Device-Id': getOrCreateDeviceId() },
})
// Sau logout: device này không còn nhận push, các thiết bị khác vẫn nhận.
```

→ Nếu logout thiếu header → BE giữ FCM rows (defensive — không "đoán" device nào).

## Token rotation

Firebase đôi khi rotate FCM token (vd update browser). FE detect → gửi token mới qua `X-FCM-Token` header. BE updateOrCreate dựa trên `(user_id, device_id)` → row cũ overwritten với token mới. Không cần FE call riêng.

## "Phone bán cho người khác" / "Reset trình duyệt"

Edge case BE handle: nếu cùng `fcm_token` đến với `user_id` khác → BE tự xóa row của user cũ trước khi insert row của user mới. FE không phải làm gì.

## Push behavior

BE giờ gửi multicast: 1 event → tất cả tokens của user → 1 HTTP call Firebase. User mở 5 tab → cả 5 nhận cùng lúc.

Token bị Firebase báo `Invalid` hoặc `NotFound` (app uninstall, browser revoke permission) → BE tự xóa row, không spam retry.

## Backward compat

- API cũ `users.fcm_token` đã drop. Code FE nào vẫn dùng `user.fcm_token` field từ response → trả về null. Không cần `fcm_token` field trên user object nữa.
- `POST /api/users` / `PUT /api/users/{id}` với `fcm_token` field → silently dropped (no-op). FE không bị 422.

## Edge cases

| Case | BE | FE |
|------|----|----|
| User chưa cấp quyền push browser | `X-FCM-Token` không có → middleware skip | Bình thường |
| User cấp quyền sau login | Request kế tiếp có header → BE upsert | Tự nhiên |
| 2 tab cùng browser | Cùng `X-Device-Id` (localStorage chung) → 1 row | Mỗi tab vẫn nhận push (cùng FCM SDK lắng nghe) |
| Clear localStorage | Device_id mới → row cũ orphan | Bình thường, BE cleanup tự khi token invalid |
| Logout 1 device, các device khác | Chỉ device đó mất FCM row | Các tab/browser khác vẫn nhận push |
