# Zalo OA Message — changelog FE

**Ngày:** 2026-05-06
**Đối tượng:** FE team

Channel Zalo chuyển từ ZNS (template-based, gửi theo phone) sang **Zalo OA Message** (free-form text, gửi theo Zalo `user_id`). FE cần làm 3 thứ: (1) đổi form Settings nhóm Zalo, (2) thêm field `zalo_user_id` vào form User, (3) đổi field test endpoint cho channel zalo.

---

## 1. Form Settings — nhóm `zalo`

**Bỏ 6 input cũ:** `zalo_server`, `zalo_username`, `zalo_password`, `zalo_sender`, `zalo_template_id`, `zalo_extra_params`.

**Render lại 4 input string + 1 toggle:**

| Key | Input type | Label | Placeholder gợi ý | Bắt buộc? |
|---|---|---|---|---|
| `zalo_enabled` | toggle/switch | Bật Zalo OA | — | — |
| `zalo_app_id` | text | App ID | `4105620146849935658` | Optional (chỉ để auto-refresh token) |
| `zalo_app_secret` | password | Secret Key | `••••••••` | Optional |
| `zalo_access_token` | textarea (1 dòng cũng ok, nhưng chuỗi rất dài ~250 ký tự) | Access Token | `b_sS6fafj...` | **Bắt buộc** để gửi tin |
| `zalo_refresh_token` | textarea | Refresh Token | `cxwl1Zuem...` | Optional |

### Logic FE

- `zalo_enabled = false` → channel tắt, các input còn lại có thể để trống.
- `zalo_enabled = true` + chỉ điền `zalo_access_token` → gửi tin được trong 1 giờ (đến khi token expire).
- `zalo_enabled = true` + điền đủ 4 → BE auto-refresh khi token expire, chạy 24/7.
- Nếu chỉ access_token mà thiếu 3 cái kia → cảnh báo nhỏ (tooltip/icon `!`): *"Token sẽ hết hạn sau 1 giờ. Điền App ID + Secret Key + Refresh Token để BE tự xoay."*

### Cách lấy 4 giá trị

| Field | Lấy ở đâu |
|---|---|
| `zalo_app_id` | URL portal: `developers.zalo.me/app/<APP_ID>` — số trong URL |
| `zalo_app_secret` | `developers.zalo.me/app/<APP_ID>` → **Cài đặt** / **Thông tin ứng dụng** → section **"Cài đặt khoá bí mật"** |
| `zalo_access_token` + `zalo_refresh_token` | `developers.zalo.me/tools/explorer` → **"Lấy Access Token"** → chọn **"OA Access Token"** + chọn OA → 2 token sinh ra cùng lúc |

### Reload settings sau save

BE refresh token trong nền — `zalo_access_token` + `zalo_refresh_token` trong DB sẽ thay đổi theo thời gian. FE nên **reload settings sau save** để không hiển thị token đã invalidate.

---

## 2. Form User (Tạo + Sửa)

BE thêm cột `users.zalo_user_id` (nullable, unique, max 100 ký tự).

### FE phải làm

**Form Tạo (`POST /api/users`) + Form Sửa (`PUT /api/users/{id}`):**

Thêm 1 field mới đặt **gần field `phone`**:

```html
<label>Zalo User ID <span class="optional">(không bắt buộc)</span></label>
<input
  type="text"
  name="zalo_user_id"
  maxlength="100"
  placeholder="VD: 9210474154953426059"
  hint="Lấy từ Zalo dev portal — danh sách follower của OA. Không bắt buộc."
/>
```

- **Type**: text
- **Required**: false (cho phép null, user có thể không dùng kênh Zalo)
- **Validation FE**: chỉ check `maxlength=100` (BE check unique)
- **Allow clear**: cho phép xoá → gửi `null` để bỏ link Zalo cho user đó
- **Vị trí**: ngay sau `phone` trong layout (cùng nhóm "Liên hệ")

**Form Sửa thêm**: pre-fill từ response `GET /api/users/{id}` field `zalo_user_id`.

### Validation errors BE trả

```json
{
  "errors": {
    "zalo_user_id": [
      "Zalo User ID 9210474154953426059 đã được gán cho người dùng khác.",
      "Zalo User ID không được vượt quá 100 ký tự."
    ]
  }
}
```

→ Hiển thị inline dưới field như các field khác.

### List User (`GET /api/users`) — optional

Nếu muốn admin thấy nhanh ai đã link Zalo:

| Cách | Mô tả |
|---|---|
| Cột riêng "Zalo" | Hiển thị icon ✓ (xanh) nếu `zalo_user_id != null`, dấu — nếu null. Tooltip on-hover hiện ID đầy đủ. |
| Filter `?zalo_linked=1` | Chưa support BE — nếu cần thì FE filter client-side |

### Resource response

`GET /api/users/{id}` và `GET /api/users` đã return field mới:
```json
{
  "id": 1,
  "name": "...",
  "email": "...",
  "phone": "0905xxx",
  "zalo_user_id": "9210474154953426059",   // ← MỚI, có thể null
  "user_name": "...",
  ...
}
```

---

## 3. Form test thông báo (`POST /api/notification/test`)

**Request shape không đổi.** Nhưng cho channel `zalo`, **đổi field gửi đi**:

| Trước (ZNS) | Sau (OA) |
|---|---|
| `phone: "0905xxx"` | `zalo_id: "<Zalo user_id>"` |
| `context: { template params... }` | `content: "Nội dung text tự do"` |

Form test hiện đang có ô `phone` + `context` cho channel zalo → đổi thành:

```html
<input name="zalo_id" placeholder="9210474154953426059" />
<textarea name="content" placeholder="Nội dung tin nhắn..."></textarea>
```

Payload mới:
```json
{
  "channels": ["zalo"],
  "zalo_id": "9210474154953426059",
  "content": "Xin chào, đây là tin test từ hệ thống QLCV.",
  "name": "Nguyễn Linh"
}
```

Response shape không đổi.

---

## 4. Lấy `zalo_user_id` của user — flow Admin nhập tay

BE không proxy. Admin tự lấy từ Zalo OA dashboard hoặc gọi thẳng Zalo API:

### Cách 1 — Zalo dev console (đơn giản nhất)

1. Vào `developers.zalo.me/tools/explorer` → chọn app + OA
2. **API Explorer** → chọn API **"Get OA followers"** (`/v2.0/oa/getfollowers`) → submit → xem list `user_id`
3. Click từng user_id → submit **"Get OA user detail"** (`/v2.0/oa/getprofile`) để xem `display_name` + `avatar`
4. Match với user QLCV → copy `user_id` → paste vào form Edit user QLCV

### Cách 2 — FE gọi thẳng Zalo API (nếu muốn dựng UI tự động)

CORS đã verify hoạt động. FE đọc `zalo_access_token` từ `GET /api/settings`, gọi:

```js
// List
const data = JSON.stringify({offset: 0, count: 50})
const res = await fetch(`https://openapi.zalo.me/v2.0/oa/getfollowers?data=${encodeURIComponent(data)}`, {
  headers: { access_token: zalo_access_token }
})
const { data: { followers, total } } = await res.json()
// followers: [{user_id: "..."}, ...]

// Profile
const data2 = JSON.stringify({user_id: '9210474154953426059'})
const res2 = await fetch(`https://openapi.zalo.me/v2.0/oa/getprofile?data=${encodeURIComponent(data2)}`, {
  headers: { access_token: zalo_access_token }
})
// → { data: { display_name, avatar, user_id_by_app, ... }, error: 0 }
```

⚠ Nếu Zalo trả `{error: -216, message: 'Access token is invalid'}` → token expired. Admin phải:
- Vào Zalo dev console **"Lấy Access Token"** lại → copy access_token mới → paste vào setting `zalo_access_token` → Lưu
- Hoặc gửi 1 tin test (bất kỳ) qua `POST /api/notification/test` → BE auto-refresh nếu có đủ creds → reload settings → token đã tươi

### Flow UI gợi ý (nếu làm Cách 2)

Trang **Quản trị → Liên kết Zalo OA** (optional, không bắt buộc cho MVP):
- Table list 50 follower/trang, ô search filter client-side theo `display_name`
- Mỗi row: avatar + display_name + user_id + nút **"Gán cho user QLCV..."**
- Click → modal chọn QLCV user → submit `PUT /api/users/{id}` body `{zalo_user_id: "..."}`

Hoặc tích hợp ngay trong form Edit User:
- Bên cạnh ô input `zalo_user_id` thêm nút **"🔍 Tìm trên Zalo OA"** → mở popup browse follower → click pick → fill vào ô input

---

## 5. BE behavior — FE không cần làm gì thêm

- **Gửi tin**: `POST https://openapi.zalo.me/v2.0/oa/message` — endpoint legacy v2.0 work với mọi OA tier (cả nhà nước/community). v3.0 endpoints (`/cs`, `/transaction`) chỉ work với OA verified business.
- **Auto-refresh token**: lazy reactive — khi gửi tin gặp lỗi token → BE refresh + retry 1 lần. Lock atomic chống race.
- **Errors**: trả về `SendResult.error` format `[<errorcode>] <message>` — FE hiển thị raw cũng được.

### Constraint cần biết

OA hiện tại (UBND phường) có thể là **community/government tier** → không gửi được CSKH free-form (-235). Tin gửi qua `/v2.0/oa/message` legacy thường vẫn work. Nếu trong tương lai Zalo deprecate v2.0, cần upgrade OA tier hoặc dùng template via `/transaction`.

**Cửa sổ 24h (chưa verify cho endpoint hiện tại)**: docs Zalo nói rõ tin CSKH (`/v3.0/oa/message/cs`) chỉ gửi được trong 24h kể từ user tương tác với OA. Endpoint code đang dùng (`/v2.0/oa/message` legacy) — docs không nêu rõ 24h có áp dụng không. Cần test thực tế: gửi cho user đã follow > 24h chưa tương tác → nếu Zalo trả `error: -32` hoặc tương tự = có window, `error: 0` = không.

Tạm thời assume **có thể có** window — notification theo lịch (reminder ngày sau) có rủi ro bị reject. Theo dõi log `SendResult.error` sau khi production để biết.
