# Changelog FE — Guest "Yêu cầu mở tài khoản"

**Ngày:** 2026-04-20
**Branch:** `main`
**Đối tượng:** FE team

Thêm 1 endpoint **public** cho khách (chưa đăng nhập) gửi yêu cầu mở tài khoản. BE forward yêu cầu qua email tới `contact_email` cấu hình trong settings. **Không** lưu DB, **không** trả về dữ liệu.

---

## 1. Endpoint mới

### `POST /api/auth/request-account` — Yêu cầu mở tài khoản (guest)

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/auth/request-account` |
| **Auth** | `@unauthenticated` — không cần Bearer token |
| **Content-Type** | `application/json` |
| **Rate limit** | **5 request / IP / 10 phút** (Laravel `throttle:5,10`) |

**Request body:**
```json
{
  "full_name": "Nguyễn Văn A",
  "phone": "0901234567",
  "email": "a@example.com",
  "content": "Xin mở tài khoản phòng X..."
}
```

**Field:**
| Field | Type | Required | Rule | Mô tả |
|---|---|---|---|---|
| `full_name` | string | ✓ | max 150 | Họ và tên người gửi |
| `phone` | string | ✓ | max 20 | Số điện thoại liên hệ |
| `email` | string | ✓ | email, max 150 | Thư điện tử liên hệ |
| `content` | string | ✓ | max 2000 | Nội dung yêu cầu |

**Response 200 — thành công:**
```json
{
  "success": true,
  "message": "Yêu cầu đã được gửi."
}
```

**Lỗi:**
| HTTP | Body message | Nguyên nhân | FE xử lý |
|---|---|---|---|
| 422 | `{ "success": false, "message": "...", "errors": { "full_name": ["..."], ... }, "code": "VALIDATION_ERROR" }` | Validation fail | Hiện inline error theo `errors[field]`. Message tiếng Việt sẵn từ BE. |
| 429 | (Laravel default) `{ "message": "Too Many Attempts." }` | Vượt quá 5 req/10 phút | Hiển thị toast: "Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau ít phút." |
| 503 | `{ "success": false, "message": "Hệ thống chưa cấu hình email tiếp nhận. Vui lòng liên hệ quản trị." }` | Admin chưa cấu hình `contact_email` hoặc chưa bật email | Hiển thị message trực tiếp. |

**Behavior quan trọng:**
- Gửi mail là **async** (queue). Response 200 chỉ xác nhận BE đã nhận yêu cầu hợp lệ và đã đẩy vào queue — **không** đảm bảo mail đã tới đích.
- Không có endpoint tra cứu trạng thái. Sau khi nhận 200, FE coi như xong.

---

## 2. Setting liên quan (admin cấu hình, FE chỉ tham khảo)

Key mới `contact_email` thêm vào group `general`, **`is_public = false`** → **không** xuất hiện trong `GET /api/settings/public`. FE guest không cần đọc.

Admin cấu hình qua trang Settings hiện có:

```http
PUT /api/settings
Content-Type: application/json
Authorization: Bearer {admin_token}

{ "contact_email": "admin@danang.gov.vn" }
```

→ Sau khi seed `php artisan db:seed --class=SettingSeeder --force`, key này sẽ xuất hiện trong group `general` ở UI Settings (label: **"Email tiếp nhận yêu cầu liên hệ"**).

**Điều kiện để API gửi mail thành công:**
1. `contact_email` không rỗng.
2. `email_enabled = true` + SMTP config (`email_smtp_host`, `email_smtp_username`, …) hợp lệ.
3. Queue worker đang chạy (`php artisan queue:work database --queue=notifications,default …`).

Thiếu 1 điều kiện → API trả 503 (cho 1, 2) hoặc 200 nhưng mail không gửi (cho 3, không phát hiện được client-side).

---

## 3. Integration flow — gợi ý cho FE

```
[Trang Liên hệ / Đăng ký guest]
 │
 │  User điền form 4 field → submit
 │
 │  POST /api/auth/request-account
 │    Body: { full_name, phone, email, content }
 │  ─────────────────────────────────────────►
 │                                  [validate → dispatch job → 200]
 │  ◄─────────────────────────────────────────
 │  { success: true, message: "Yêu cầu đã được gửi." }
 │
 │  → Hiện toast success + reset form
 │  → (Optional) navigate về trang chủ
```

**UX:**
- Nếu nhận 503 → KHÔNG hiện form lại, hiển thị banner báo "Hệ thống chưa sẵn sàng tiếp nhận, vui lòng thử lại sau hoặc liên hệ trực tiếp tới [hotline/email tĩnh trên FE]".
- Nếu nhận 429 → giữ nguyên dữ liệu trên form, disable submit 60s rồi enable lại.
- Sau 200 → reset form và disable submit thêm 30s để tránh user spam vô tình.

---

## 4. Validation messages mẫu (BE đã trả tiếng Việt)

| Field rule fail | Message |
|---|---|
| `full_name.required` | Họ tên không được để trống. |
| `full_name.max` | Họ tên tối đa 150 ký tự. |
| `phone.required` | Số điện thoại không được để trống. |
| `phone.max` | Số điện thoại tối đa 20 ký tự. |
| `email.required` | Email không được để trống. |
| `email.email` | Email không hợp lệ. |
| `email.max` | Email tối đa 150 ký tự. |
| `content.required` | Nội dung yêu cầu không được để trống. |
| `content.max` | Nội dung yêu cầu tối đa 2000 ký tự. |

FE chỉ cần `errors[field][0]` để hiện. Có thể client-side validate trước (cùng rule) để giảm round-trip.

---

## 5. Out of scope (v1)

- Không có CAPTCHA — chỉ throttle theo IP.
- Không có honeypot field.
- Không lưu danh sách yêu cầu vào DB → admin không có trang xem lại; mọi yêu cầu chỉ tồn tại trong mailbox.
- Không gửi mail xác nhận lại cho guest.
- Không có endpoint duyệt/từ chối — admin nhận mail xong tự tạo tài khoản qua trang admin.

---

## 6. Related

- **Design spec:** [../superpowers/specs/2026-04-20-guest-account-request-design.md](../superpowers/specs/2026-04-20-guest-account-request-design.md)
- **API tổng hợp Auth:** [../api/auth.md](../api/auth.md)
- **Scribe auto-generated:** `{APP_URL}/docs` (chạy `php artisan scribe:generate` để cập nhật)
