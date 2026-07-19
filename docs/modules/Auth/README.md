# Module: Auth (Xác thực)

> Ngày tạo: 11:25:35 19/07/2026
> Cập nhật lần cuối: 11:25:35 19/07/2026

---

## 1. Mục đích nghiệp vụ

Xác thực người dùng vào hệ thống: đăng nhập/đăng xuất bằng tài khoản nội bộ (email hoặc username + mật khẩu), quên/đặt lại mật khẩu, chuyển đổi tổ chức đang làm việc (đa tổ chức), và đăng nhập qua SSO của bên thứ 3 (Cổng SSO Đà Nẵng, hệ thống CBCCVC). Module còn xử lý yêu cầu cấp tài khoản khách (guest account request) khi người dùng ngoài hệ thống muốn xin cấp tài khoản. Đây là module nhỏ, không có domain model/entity riêng — chỉ có Controller + Service điều phối, dữ liệu người dùng thực tế (`User`) thuộc về module Core.

---

## 2. Vị trí trong codebase

```
app/Modules/Auth/
  AuthController.php    ← login, logout, me, switchOrganization, forgot/reset-password,
                            requestAccount — đặt trực tiếp ở root, không có thư mục Controllers/
  SsoController.php     ← exchange (OAuth code đa provider), cbccvcLogin
  Requests/              ← LoginRequest, ForgotPasswordRequest, ResetPasswordRequest,
                            SwitchOrganizationRequest, RequestAccountRequest,
                            SsoExchangeRequest, CbccvcLoginRequest
  Services/
    AuthService.php               ← login/logout/forgot/reset/switchOrganization
    CaslAbilityConverter.php      ← convert permissions → CASL abilities cho FE Vue
    GuestAccountRequestService.php← xử lý yêu cầu cấp tài khoản khách
    UserSyncService.php           ← đồng bộ/khớp user cục bộ khi đăng nhập qua SSO
    Providers/
      SsoProvider.php             ← interface chung cho provider SSO
      SsoDanangProvider.php       ← implement cho Cổng SSO Đà Nẵng
      CbccvcProvider.php          ← implement cho hệ thống CBCCVC
  Jobs/
    SendGuestAccountRequestEmail.php ← gửi email thông báo yêu cầu cấp tài khoản (queue: notifications)
  Routes/
    auth.php
```

Route prefix: `/auth` (bao gồm `/auth/sso/...`).
Namespace: `App\Modules\Auth`

**Không có** `Models/`/`Events/`/`Observers/` — module không sở hữu bảng dữ liệu riêng, `User` thuộc Core. Cấu trúc thư mục cũng KHÁC quy ước module nghiệp vụ chuẩn (Controller ở root, không có `Controllers/`/`Enums/`/`Resources/`) — phù hợp vì đây là module điều phối xác thực, không phải module CRUD danh mục.

---

## 3. Entities & Models

Module không định nghĩa Model riêng. Các entity liên quan thuộc module Core:

| Model (thuộc Core) | Vai trò trong luồng Auth |
|---|---|
| `User` | Đối tượng được xác thực — kiểm tra `email`/`user_name` + `password`, kiểm tra `status` (không cho đăng nhập nếu `banned`/`inactive`) |
| `UserPreference` | Lưu `current_organization_id` sau khi login/switch — dùng lại cho lần đăng nhập sau nếu vẫn hợp lệ |
| `Organization` | Danh sách `available_organizations` user có thể truy cập, trả về kèm kết quả login |
| `Setting` | Đọc cấu hình liên quan SSO (`sso_danang`, `sso_cbccvc` — theo `SettingGroupEnum`) và tên tổ chức dùng trong email yêu cầu tài khoản |

---

## 4. Business Rules & Invariants

- Đăng nhập chấp nhận CẢ email lẫn `user_name` ở cùng 1 trường `email` trong `LoginRequest` (tên field giữ nguyên `email` dù giá trị có thể là username).
- Sau khi login thành công: nếu user chỉ thuộc đúng 1 tổ chức → tự động gán `current_organization_id` và lưu vào `UserPreference`; nếu thuộc nhiều tổ chức và chưa có preference hợp lệ → trả `current_organization_id = null`, bắt buộc FE hiển thị màn chọn tổ chức rồi gọi `switch-organization`.
- `switchOrganization` chỉ cho phép chuyển sang tổ chức nằm trong danh sách tổ chức user thực sự có quyền truy cập (`getAccessibleOrganizations()`), không nhận `organization_id` bất kỳ từ client.
- Đăng nhập SSO (Đà Nẵng/CBCCVC) không tạo mật khẩu nội bộ — `UserSyncService::matchLocalUser()` khớp user cục bộ theo `email`/`username` từ thông tin do SSO Gateway trả về; nếu không khớp được user nào, luồng SSO thất bại (không tự động tạo user mới ở tầng Service này).
- Rate limit áp dụng ở tầng route (`throttle:5,1` cho login, `throttle:5,10` cho forgot/reset-password/request-account) để chống brute-force, không xử lý trong Service.
- `AuthService` KHÔNG BAO GIỜ gọi trực tiếp Mail — email yêu cầu cấp tài khoản đi qua `SendGuestAccountRequestEmail` Job (queue `notifications`), tách khỏi request-response cycle.
- Service KHÔNG có domain riêng để fire Event/Notification kiểu `event(new XxxEvent())` — vì đăng nhập/đăng xuất không phải "resource nghiệp vụ" cần audit theo pattern Event-Driven của các module khác; log hoạt động đăng nhập (nếu có) đi qua middleware `LogActivity` chung của Core.

---

## 5. State Machine

Module không sở hữu entity có `status` riêng để mô tả state machine — trạng thái liên quan (`User.status`) thuộc về module Core, chỉ được ĐỌC (không đổi) trong luồng Auth để quyết định cho phép đăng nhập hay không (`active` → cho phép; `inactive`/`banned` → từ chối).

---

## 6. Luồng nghiệp vụ chính

### 6.1 Đăng nhập nội bộ

```
1. POST /auth/login (LoginRequest: email/username + password), throttle 5 lần/phút.
2. AuthController::login() → AuthService::login($login, $password):
   - Tìm User theo email hoặc user_name, verify password.
   - Kiểm tra status (chặn inactive/banned).
   - buildAuthenticatedResponse(): tạo Sanctum token, xác định current_organization_id
     (từ UserPreference nếu hợp lệ, tự gán nếu chỉ có 1 tổ chức, null nếu cần chọn),
     nạp roles/permissions theo tổ chức đó, convert sang abilities qua CaslAbilityConverter
     (phục vụ Vue Casl ở FE).
3. Response 200: access_token, user, available_organizations, current_organization_id,
   roles, permissions, abilities.
```

### 6.2 Xem thông tin hiện tại & Chuyển tổ chức

```
1. GET /auth/me (auth:sanctum, kèm header X-Organization-Id) → trả user + roles/permissions
   của tổ chức đang chọn — dùng để Vue Casl khởi tạo lại ability khi refresh trang.
2. POST /auth/switch-organization (SwitchOrganizationRequest) → AuthService::switchOrganization()
   kiểm tra organization_id nằm trong getAccessibleOrganizations(), cập nhật UserPreference,
   trả lại bộ roles/permissions/abilities MỚI theo tổ chức vừa chuyển sang.
```

### 6.3 Quên mật khẩu / Đặt lại mật khẩu

```
1. POST /auth/forgot-password (email) → AuthService::forgotPassword() sinh token reset,
   gửi email chứa link đặt lại (qua hạ tầng Notification/Mail chung, không tự implement
   mailer riêng).
2. POST /auth/reset-password (email, password, token) → AuthService::resetPassword() verify
   token hợp lệ, đổi password.
```

### 6.4 Đăng xuất

```
1. POST /auth/logout (auth:sanctum) → AuthService::logout($user, $deviceId) thu hồi
   Sanctum token hiện tại (hoặc theo device_id nếu multi-device).
```

### 6.5 Yêu cầu cấp tài khoản khách (Guest Account Request)

```
1. POST /auth/request-account (RequestAccountRequest: full_name, phone, email, content),
   throttle 5 lần/10 phút, không cần đăng nhập.
2. GuestAccountRequestService::handle() đọc Setting để lấy email liên hệ/tên tổ chức nhận
   yêu cầu.
3. Dispatch SendGuestAccountRequestEmail Job (queue notifications, tries=3, backoff
   [10,30,60]) — Service KHÔNG gửi mail đồng bộ trong request, tránh block response khi
   SMTP chậm/lỗi.
```

### 6.6 Đăng nhập qua SSO (Đà Nẵng / CBCCVC)

```
1. POST /auth/sso/exchange (provider, code) → SsoController::exchange() chọn Provider tương
   ứng (SsoDanangProvider qua SsoProvider interface) để đổi authorization code lấy thông tin
   user từ SSO Gateway.
2. UserSyncService::matchLocalUser(email, username) tìm User cục bộ khớp với thông tin SSO
   trả về — luồng exchange() không tự tạo user mới nếu không khớp được.
3. Nếu khớp: AuthService::buildAuthenticatedResponse() tạo token + trả response giống luồng
   login thường (6.1 bước 2-3).
4. POST /auth/sso/cbccvc/login (CbccvcLoginRequest) là biến thể riêng cho hệ thống CBCCVC
   (CbccvcProvider), cùng pattern nhưng khác cơ chế xác thực đầu vào với SSO Đà Nẵng.
```

---

## 7. Events & Side-effects

Module không có `Events/`/`Listeners/`/`Observers/` riêng. Side-effect duy nhất đáng chú ý:

| Job | Khi nào dispatch | Queue | Ghi chú |
|---|---|---|---|
| `SendGuestAccountRequestEmail` | Sau khi `requestAccount()` xử lý xong | `notifications` | `tries = 3`, `backoff = [10, 30, 60]` — đúng chuẩn CLAUDE.md §4 (Job phải khai báo tries/backoff rõ ràng) |

Không có Event nghiệp vụ kiểu `UserLoggedIn`/`UserLoggedOut` được fire — nếu cần audit đăng nhập, dựa vào `last_login_at` (cột trên `User`, module Core) hoặc middleware `LogActivity` chung, không phải cơ chế riêng của module này.

---

## 8. Permissions

Module Auth không dùng permission key theo pattern `{resource}.{action}` như các module CRUD khác — vì các action ở đây (login, logout, forgot-password...) là hành vi xác thực công khai hoặc chỉ cần trạng thái đăng nhập (`auth:sanctum`), không phải resource cần phân quyền chi tiết.

| Route | Middleware bảo vệ |
|---|---|
| `login`, `forgot-password`, `reset-password`, `request-account` | `throttle` (không cần đăng nhập — `@unauthenticated`) |
| `logout`, `switch-organization`, `me` | `auth:sanctum` (chỉ cần đã đăng nhập, không check permission cụ thể) |
| `sso/exchange`, `sso/cbccvc/login` | Không cần đăng nhập trước (đây chính là điểm vào để đăng nhập) |

---

## 9. API Endpoints

Định nghĩa tại `app/Modules/Auth/Routes/auth.php`:

| Method | Path | Mô tả | Auth |
|---|---|---|---|
| `POST` | `/api/auth/login` | Đăng nhập nội bộ | ✗ (throttle 5/phút) |
| `GET` | `/api/auth/me` | Thông tin user + roles/permissions/abilities hiện tại | ✓ |
| `POST` | `/api/auth/logout` | Đăng xuất | ✓ |
| `POST` | `/api/auth/switch-organization` | Chuyển tổ chức đang làm việc | ✓ |
| `POST` | `/api/auth/forgot-password` | Yêu cầu đặt lại mật khẩu | ✗ (throttle 5/10 phút) |
| `POST` | `/api/auth/reset-password` | Đặt lại mật khẩu bằng token | ✗ (throttle 5/10 phút) |
| `POST` | `/api/auth/request-account` | Yêu cầu cấp tài khoản khách | ✗ (throttle 5/10 phút) |
| `POST` | `/api/auth/sso/exchange` | Đăng nhập qua SSO (đa provider, OAuth code exchange) | ✗ |
| `POST` | `/api/auth/sso/cbccvc/login` | Đăng nhập qua hệ thống CBCCVC | ✗ |

Chi tiết request/response mẫu xem [`docs/api/auth.md`](../../api/auth.md) và [`docs/api/sso.md`](../../api/sso.md).

---

## 10. Phụ thuộc module khác

| Phụ thuộc | Dùng gì | Ghi chú |
|---|---|---|
| `Core` | `User`, `UserPreference`, `UserPreferenceService`, `Organization`, `Setting`, `UserStatusEnum`, `UserResource` | Auth không sở hữu dữ liệu user — chỉ điều phối xác thực trên dữ liệu của Core |
| Sanctum (Laravel) | Token-based auth | `access_token` trả về ở mọi luồng login |
| Hạ tầng Mail/Notification chung | Gửi email quên mật khẩu, email yêu cầu tài khoản | Qua `SendGuestAccountRequestEmail` Job — không gọi Mail trực tiếp trong Service |

Không module nghiệp vụ nào (Meeting, Scheduling, TaskAssignment, Beneficiary) phụ thuộc ngược vào Auth — các module đó chỉ dựa vào `auth:sanctum` + `User` (Core) đã được xác thực từ trước.

---

## 11. Điểm dễ gây lỗi khi maintain

- **Controller đặt ở root `app/Modules/Auth/*.php`, không có `Controllers/`** — giống Core, khác với module nghiệp vụ chuẩn.
- **Trường `email` trong `LoginRequest` thực chất nhận cả username** — đừng thêm validate `email` format nghiêm ngặt cho trường này, sẽ chặn nhầm user đăng nhập bằng `user_name`.
- **`current_organization_id` có thể là `null` sau login** (user nhiều tổ chức, chưa từng chọn) — FE/BE code phía sau không được giả định luôn có giá trị, phải xử lý trường hợp cần chọn tổ chức trước.
- **`UserSyncService::matchLocalUser()` không tự tạo user mới** — nếu SSO trả về người dùng chưa có trong hệ thống, luồng thất bại thay vì auto-provision; cần xác nhận đây có đúng là hành vi mong muốn trước khi "sửa lỗi" tưởng như là bug.
- **Không có Event `UserLoggedIn`** — nếu sau này cần side-effect khi đăng nhập (vd cập nhật last_login_at đã có sẵn trên User, nhưng nếu cần thêm side-effect MỚI), phải cân nhắc thêm Event theo đúng CLAUDE.md §EDA thay vì nhét thêm logic thẳng vào `AuthService::login()`.

---

## 12. Câu hỏi thường gặp

**Q:** Vì sao module Auth không có `Models/`?
**A:** Vì Auth không sở hữu dữ liệu nghiệp vụ riêng — nó chỉ điều phối xác thực trên `User`/`Organization`/`Setting` vốn đã thuộc về module Core. Tạo Model trùng lặp ở đây sẽ vi phạm nguyên tắc single source of truth.

**Q:** Tại sao có 2 provider SSO riêng biệt (`SsoDanangProvider`, `CbccvcProvider`) thay vì 1 provider chung?
**A:** Vì 2 hệ thống SSO bên ngoài (Cổng SSO Đà Nẵng và CBCCVC) có cơ chế xác thực/API khác nhau — dùng chung 1 interface `SsoProvider` để `SsoController::exchange()` có thể chọn provider theo tham số `provider` từ request, nhưng phần implement tách riêng để không lẫn logic đặc thù của từng bên.

**Q:** Vì sao `requestAccount` dùng Job thay vì gửi email đồng bộ ngay trong Controller?
**A:** Vì gửi email phụ thuộc SMTP bên ngoài — nếu gửi đồng bộ, response cho user sẽ bị block/timeout khi SMTP chậm. Dispatch Job vào queue `notifications` cho phép trả response ngay, việc gửi email xử lý nền và có retry (`tries=3`, `backoff` tăng dần) nếu thất bại tạm thời.
