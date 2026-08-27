# Hướng Dẫn Sử Dụng Phân Hệ Quản Lý Công Việc — Phần Chung

> Ngày tạo: 16:50:31 25/08/2026  
> Cập nhật lần cuối: 16:50:31 25/08/2026

Tài liệu này dành cho người dùng thao tác thử trên dữ liệu mẫu. Đọc phần chung
này trước (tài khoản, đăng nhập, cài ứng dụng, bật thông báo), sau đó mở tiếp
tài liệu đúng vai trò của bạn:

| Vai trò | Tài liệu |
|---|---|
| Nhân viên | [Hướng dẫn vai trò Nhân viên](huong-dan-qlcv-nhan-vien_165031_25082026.md) |
| Trưởng phòng | [Hướng dẫn vai trò Trưởng phòng](huong-dan-qlcv-truong-phong_165031_25082026.md) |
| Quản lý chung | [Hướng dẫn vai trò Quản lý chung](huong-dan-qlcv-quan-ly_165031_25082026.md) |

---

## 1. Tài khoản thử nghiệm

Tất cả tài khoản dưới đây do seeder tạo sẵn, thuộc tổ chức mặc định và đã kích
hoạt. **Mật khẩu chung: `123123`** (riêng tài khoản quản trị dùng mật khẩu riêng).

### 1.1. Ba nhóm chính

| Tài khoản | Mật khẩu | Vai trò | Phòng ban |
|---|---|---|---|
| `nhanvien1` | `123123` | Nhân viên | Phòng Hành chính - Tổng hợp |
| `nhanvien2` | `123123` | Nhân viên | Phòng Hành chính - Tổng hợp |
| `nhanvien3` | `123123` | Nhân viên | Phòng Kế hoạch - Tài chính |
| `nhanvien4` | `123123` | Nhân viên | Phòng Kế hoạch - Tài chính |
| `nhanvien5` | `123123` | Nhân viên | Phòng Kỹ thuật - Công nghệ |
| `nhanvien6` … `nhanvien10` | `123123` | Nhân viên | Rải đều 3 phòng |
| `truongphong1` | `123123` | Trưởng phòng | Phòng Hành chính - Tổng hợp |
| `truongphong2` | `123123` | Trưởng phòng | Phòng Kế hoạch - Tài chính |
| `truongphong3` | `123123` | Trưởng phòng | Phòng Kỹ thuật - Công nghệ |
| `quanly1` | `123123` | Quản lý công việc | Phòng Hành chính - Tổng hợp |
| `admin` | `quandcore**11` | Super Admin | — |

Đăng nhập được bằng **tên đăng nhập hoặc email**. Email theo mẫu
`<tên đăng nhập>@example.com`, ví dụ `nhanvien1@example.com`.

### 1.2. Nên chọn tài khoản nào để thử

| Muốn thử | Dùng tài khoản | Vì sao |
|---|---|---|
| Cập nhật tiến độ, làm báo cáo | `nhanvien3` | Đang có việc "Lập dự toán kinh phí quý IV" ở 60% |
| Việc chưa bắt đầu | `nhanvien4` | Việc "Đối chiếu quyết toán kinh phí năm 2026" ở 0% |
| Nhiều việc cùng lúc | `nhanvien5` | Có 2 việc thuộc Phòng Kỹ thuật - Công nghệ |
| Việc đã báo cáo, chờ duyệt | `nhanvien2` | Việc "Tổng hợp báo cáo nhân sự quý III" đang **Chờ duyệt** |
| Duyệt hoàn thành / từ chối | `quanly1` | Là người giao toàn bộ việc mẫu |
| Xem số liệu cấp phòng | `truongphong2` | Phòng Kế hoạch - Tài chính có 2 việc ở 2 trạng thái khác nhau |

> **Lưu ý:** ba tài khoản `truongphong1-3` **chưa được giao việc nào** trong dữ
> liệu mẫu. Đăng nhập vào, màn "Công việc được giao" sẽ trống — đó là đúng, không
> phải lỗi. Họ theo dõi việc của phòng ở màn **Tổng quan công việc**.

### 1.3. Dữ liệu mẫu có sẵn

- **2 văn bản giao việc** đã ban hành, **6 công việc**, **6 báo cáo**.
- **3 đơn thư**, mỗi phòng một đơn ở một trạng thái khác nhau (Mới tiếp nhận /
  Đang xử lý / Đã hoàn thành) để kiểm chứng phạm vi xem theo phòng ban.
- **3 phòng ban**: Hành chính - Tổng hợp, Kế hoạch - Tài chính, Kỹ thuật - Công nghệ.

Muốn dựng lại dữ liệu sạch: `sail artisan db:seed --class=TaskAssignmentDemoSeeder`.
Lệnh này đặt lại mật khẩu cho toàn bộ tài khoản mẫu, không xoá dữ liệu bạn tự tạo.

---

## 2. Địa chỉ truy cập

| Ứng dụng | Môi trường nội bộ | Ghi chú |
|---|---|---|
| Web (máy tính) | `http://localhost:5173` | Giao diện đầy đủ |
| Miniapp (điện thoại) | `http://localhost:3000/miniapp/` | Bản rút gọn, cài ra màn hình chính |
| API | `http://localhost:8001` | Không thao tác trực tiếp |

Trên môi trường thật, dùng địa chỉ do đơn vị cấp. Miniapp có ô **Địa chỉ máy chủ**
ở màn đăng nhập — nhập một lần, ứng dụng ghi nhớ cho các lần sau.

---

## 3. Đăng nhập

### 3.1. Trên web

1. Mở địa chỉ web, màn hình đăng nhập hiện ra.
2. Nhập **Tên đăng nhập hoặc Email** và **Mật khẩu**.
3. Bấm **Đăng Nhập**.

Tài khoản mẫu đã được gán tổ chức mặc định nên vào thẳng **trang cá nhân**. Nếu
tài khoản thuộc nhiều tổ chức và chưa chọn mặc định, hệ thống chuyển sang màn
**Chọn tổ chức** trước.

Ngoài mật khẩu, màn đăng nhập còn có **SSO Đà Nẵng** và **CBCCVC** — chỉ dùng
được khi đơn vị đã cấu hình, tài khoản mẫu không đăng nhập theo đường này.

### 3.2. Trên miniapp

1. Mở địa chỉ miniapp trên trình duyệt điện thoại.
2. Lần đầu: bấm vào dòng **Địa chỉ máy chủ**, nhập địa chỉ API rồi lưu.
3. Nhập **Tên đăng nhập hoặc Email** + **Mật khẩu**, bấm đăng nhập.

### 3.3. Đăng nhập không được

| Hiện tượng | Nguyên nhân thường gặp |
|---|---|
| "Tên đăng nhập hoặc mật khẩu không đúng" | Gõ nhầm; mật khẩu mẫu là `123123`, không phải `123456` |
| Vào được nhưng báo không có quyền | Tài khoản chưa thuộc tổ chức nào — chạy lại seeder |
| Miniapp quay vòng ở màn đăng nhập | Sai địa chỉ máy chủ — sửa lại ở ô Địa chỉ máy chủ |

---

## 4. Cài miniapp ra màn hình chính

Miniapp là ứng dụng web cài được (PWA). Cài xong có biểu tượng riêng, mở nhanh
như app và **bắt buộc phải cài trên iPhone/iPad thì mới nhận được thông báo đẩy**.

### 4.1. Android (Chrome, Edge, Cốc Cốc)

1. Mở miniapp, đăng nhập.
2. Một thanh **"Cài đặt ứng dụng"** hiện ở cạnh dưới màn hình.
3. Bấm **Cài đặt** → xác nhận trong hộp thoại của trình duyệt.
4. Biểu tượng xuất hiện ở màn hình chính.

Bấm dấu **✕** để bỏ qua thì thanh này ẩn trong **14 ngày**. Muốn cài lại ngay:
mở menu trình duyệt (⋮) → **Cài đặt ứng dụng** / **Thêm vào Màn hình chính**.

### 4.2. iPhone / iPad (Safari)

Safari không cho ứng dụng tự cài, phải làm tay:

1. Mở miniapp bằng **Safari** (không dùng Chrome trên iOS).
2. Bấm nút **Chia sẻ** ở thanh công cụ.
3. Chọn **Thêm vào MH chính** → bấm **Thêm**.
4. **Mở lại ứng dụng từ biểu tượng vừa tạo**, không mở từ tab Safari nữa.

Miniapp cũng hiện hướng dẫn 2 bước này khi bạn bấm nút **Cài đặt** trên iOS.

### 4.3. Máy tính

Chrome/Edge: biểu tượng cài đặt ở cuối thanh địa chỉ → **Cài đặt**. Không cài
cũng dùng bình thường.

---

## 5. Bật thông báo

Hệ thống gửi thông báo khi: **ban hành văn bản**, **được giao việc mới**,
**có báo cáo hoàn thành**, **công việc được xác nhận**, và các mốc
**nhắc trước hạn / đến hạn / quá hạn**.

Thông báo đẩy bật **riêng cho từng thiết bị và từng trình duyệt**. Bật trên máy
tính không có nghĩa là điện thoại cũng nhận được — phải bật lại trên mỗi máy.

### 5.1. Trên web

Cách 1 — nhanh nhất:
1. Bấm biểu tượng **chuông** trên thanh trên cùng.
2. Trong hộp vừa mở, bấm nút bật thông báo.
3. Trình duyệt hỏi quyền → chọn **Cho phép**.

Cách 2 — đầy đủ:
1. Vào **Trang cá nhân** → tab **Thông báo**
   (đường dẫn trực tiếp: `/dashboard/user-overview?tab=notifications`).
2. Ở thẻ **Thông báo đẩy**, bấm **Bật thông báo**.
3. Chọn **Cho phép** khi trình duyệt hỏi.

Thẻ này cũng là chỗ **tắt** thông báo trên thiết bị hiện tại.

### 5.2. Trên miniapp

1. Mở tab **Trang chủ** ở thanh dưới → tab **Thông báo**.
2. Bấm nút bật ở thẻ thông báo đẩy.
3. Cho phép khi máy hỏi.

**iPhone/iPad:** phải **Thêm vào MH chính** (mục 4.2) và mở app từ biểu tượng
trước, nếu không nút bật sẽ báo cần cài đặt. Safari chỉ mở thông báo đẩy cho ứng
dụng chạy ở chế độ độc lập, từ iOS 16.4 trở lên.

### 5.3. Khi bật không được

| Hiện tượng | Xử lý |
|---|---|
| Trình duyệt đang **chặn** thông báo | Web không hỏi lại quyền được nữa. Vào Cài đặt trình duyệt → Thông báo → cho phép lại địa chỉ này |
| Đã cho phép nhưng vẫn báo "chưa đăng ký được với máy chủ" | Máy chủ chưa cấu hình Firebase, hoặc mạng lỗi. Bấm **Đăng ký lại**; vẫn không được thì báo quản trị |
| Nút bật không xuất hiện trên iOS | Chưa cài ra màn hình chính, hoặc đang mở bằng tab Safari |
| Bật xong vẫn không thấy thông báo | Kiểm tra chế độ Không làm phiền của máy, và quyền thông báo ở Cài đặt hệ thống |

> Tắt trong ứng dụng nghĩa là **huỷ đăng ký thiết bị** — máy chủ ngừng gửi tới
> máy này. Quyền hiển thị của trình duyệt vẫn còn; muốn gỡ hẳn thì vào Cài đặt
> trình duyệt.

Ngoài thông báo đẩy, hệ thống còn gửi qua Email, SMS, Zalo OA/ZNS, Telegram tuỳ
cấu hình. Việc bật/tắt từng kênh cho từng loại sự kiện do **quản trị hệ thống**
(`admin`) làm ở **Thông báo nhắc lịch → Cấu hình thông báo** — ba vai trò nghiệp
vụ không thấy menu này.

---

## 6. So sánh nhanh ba vai trò

| Chức năng | Nhân viên | Trưởng phòng | Quản lý chung |
|---|:---:|:---:|:---:|
| Xem việc được giao cho mình | ✓ | ✓ | ✓ |
| Cập nhật tiến độ, gửi báo cáo | ✓ | ✓ | ✓ |
| Ghi chú công việc | ✓ | ✓ | ✓ |
| Xuất Excel danh sách việc của mình | ✓ | ✓ | ✓ |
| Tổng quan — phạm vi số liệu | Việc của mình | **Cả phòng mình** | **Toàn tổ chức** |
| Xuất báo cáo tháng | ✗ | **✓** | ✓ |
| Đơn thư của phòng mình | ✓ | ✓ | ✓ |
| Đơn thư toàn tổ chức | ✗ | ✗ | ✓ |
| Mở khoá đơn thư đã hoàn thành | ✗ | ✗ | ✓ |
| Tạo / ban hành văn bản giao việc | ✗ | ✗ | ✓ |
| Giao việc cho người khác | ✗ | ✗ | ✓ |
| Duyệt / từ chối hoàn thành | ✗ | ✗ | ✓ |
| Điều chuyển công việc | ✗ | ✗ | ✓ |
| Tạm dừng / huỷ / mở lại công việc | ✗ | ✗ | ✓ |
| Danh mục, phòng ban, nhân viên | ✗ | ✗ | ✓ |
| Trình diễn công việc | ✗ | ✗ | ✓ |
| Cấu hình thông báo hệ thống | ✗ | ✗ | ✗ (chỉ `admin`) |

Quyền hạn của từng vai trò **do quản trị cấu hình** ở màn Vai trò. Bảng trên mô
tả cấu hình mặc định của dữ liệu mẫu; đơn vị chỉnh lại thì màn hình thấy được
cũng đổi theo.

---

## 7. Hai điểm dễ hiểu nhầm

**"Người đại diện" của phòng ban không phải chức vụ.** Đây chỉ là trường tiện ích
cho giao diện: khi giao việc cho cả phòng, hệ thống điền sẵn người này làm đầu
mối. Nó **không** liên quan tới phân quyền, không phải trưởng phòng. Trong dữ
liệu mẫu, người đại diện của ba phòng là `quanly1`, `nhanvien3`, `nhanvien5` —
không trùng với ba tài khoản trưởng phòng.

**Miniapp hiện đủ 6 tab cho mọi vai trò.** Bấm vào tab ngoài quyền của mình sẽ
nhận thông báo *"Bạn chưa được phân quyền sử dụng chức năng…"*. Đây là khác biệt
so với web — web ẩn hẳn menu không có quyền.
