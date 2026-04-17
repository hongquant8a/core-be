# Hệ thống Thông báo — Mô tả hoạt động

Tài liệu tóm tắt cách hệ thống thông báo hoạt động, dùng để trình bày cho cấp quản lý / stakeholder.

---

## 1. Tổng quan

Hệ thống thông báo tự động gửi tin đến người dùng qua 4 kênh: **SMS, Email, Zalo OA, và Push notification trên trình duyệt**. Mỗi khi có sự kiện nghiệp vụ xảy ra (ban hành văn bản giao việc, hoàn thành công việc…) hoặc đến lịch nhắc (trước hạn, đến hạn, quá hạn), hệ thống sẽ tự động gửi thông báo phù hợp đến đúng người.

Quản trị viên có thể bật/tắt từng loại thông báo, chọn kênh gửi, và cấu hình lịch nhắc cho từng mốc thời gian.

---

## 2. Các loại thông báo

Hệ thống hiện có 6 loại thông báo cho module Giao việc, chia thành 2 nhóm:

### 2.1. Thông báo theo sự kiện (gửi tức thì)

| Loại | Khi nào gửi | Người nhận |
|---|---|---|
| **Văn bản được ban hành** | Khi văn bản giao việc chuyển từ nháp sang trạng thái đã ban hành | Tất cả cán bộ được giao công việc trong văn bản |
| **Công việc báo cáo hoàn thành** | Khi cán bộ thực hiện báo cáo hoàn thành công việc (chờ duyệt) | Người quản lý đã giao công việc đó |
| **Công việc được xác nhận** | Khi quản lý duyệt và xác nhận công việc đã hoàn thành | Cán bộ đã thực hiện công việc |

### 2.2. Thông báo nhắc lịch (theo deadline)

| Loại | Khi nào gửi | Người nhận |
|---|---|---|
| **Nhắc trước hạn** | Trước deadline một khoảng thời gian do quản trị viên cấu hình (vd trước 1 ngày, trước 2 giờ) | Cán bộ thực hiện chưa hoàn thành |
| **Nhắc đến hạn** | Đúng thời điểm deadline | Cán bộ thực hiện chưa hoàn thành |
| **Nhắc quá hạn** | Sau deadline một khoảng thời gian (vd 1 ngày sau hạn) | Cán bộ thực hiện chưa hoàn thành |

---

## 3. Quản trị viên kiểm soát gì

### 3.1. Bật/tắt từng loại thông báo

Mỗi loại thông báo có công tắc riêng. Khi tắt — hệ thống không gửi thông báo đó dù có sự kiện xảy ra.

### 3.2. Chọn kênh gửi cho từng loại

Với mỗi loại thông báo, quản trị chọn kênh nào: SMS, Email, Zalo, hoặc Push. Có thể chọn nhiều kênh cùng lúc — người nhận sẽ nhận thông báo qua tất cả các kênh đã chọn.

Ví dụ: "Văn bản được ban hành" có thể cấu hình gửi cả Email và SMS để đảm bảo cán bộ không bỏ sót.

### 3.3. Cấu hình lịch nhắc linh hoạt

Với 3 loại nhắc (trước/đến/quá hạn), quản trị có thể tạo nhiều mốc khác nhau:
- Nhắc trước 1 tuần qua Email
- Nhắc trước 1 ngày qua Email + SMS
- Nhắc trước 2 giờ qua SMS + Push
- Đến hạn qua tất cả kênh
- Trễ 1 ngày qua Email
- Trễ 1 tuần qua Email (thông báo tới quản lý…)

Số lượng mốc không giới hạn.

### 3.4. Bật/tắt kênh gửi toàn cục

Mỗi kênh (SMS, Email, Zalo, Push) có công tắc riêng ở trang Cấu hình hệ thống. Khi tắt — toàn bộ thông báo qua kênh đó ngừng gửi, dù từng loại có cấu hình bật. Dùng khi:
- Cần tạm ngưng gửi SMS để tiết kiệm chi phí
- Cần bảo trì máy chủ email
- Credentials của nhà cung cấp bị hết hạn

---

## 4. Luồng nghiệp vụ end-to-end

Ví dụ cụ thể: **Giao việc cho cán bộ A + B qua văn bản "Kế hoạch quý 2"**

### Bước 1: Ban hành văn bản

Quản lý tạo văn bản "Kế hoạch quý 2", thêm 2 công việc, giao cho cán bộ A + B. Sau khi hoàn tất, quản lý bấm "Ban hành" (chuyển trạng thái từ nháp sang ban hành).

### Bước 2: Hệ thống tự động phản ứng

- Ghi nhận sự kiện "Văn bản được ban hành".
- Tạo bản ghi thông báo cho từng (cán bộ × công việc) — ở đây là 2 cán bộ × 2 công việc = 4 bản ghi thông báo.
- Tương ứng với cấu hình kênh (vd Email + SMS), tạo 4 × 2 = 8 lượt gửi (delivery).
- Xếp các lượt gửi vào hàng đợi xử lý (queue).

### Bước 3: Hàng đợi xử lý gửi tin

Tiến trình nền (queue worker) lấy từng delivery, gọi nhà cung cấp tương ứng (PSC cho SMS, SMTP cho Email, …), ghi nhận kết quả thành công/thất bại kèm mã lỗi.

### Bước 4: Song song — hệ thống tạo lịch nhắc

Khi văn bản ban hành (và các công việc được gán), hệ thống tự tính toán dựa trên deadline của từng công việc và các mốc nhắc đã cấu hình → tạo sẵn các bản ghi nhắc lịch với thời điểm cụ thể (status = chờ xử lý).

Ví dụ công việc có deadline `20/04/2026 17:00` và cấu hình "nhắc trước 1 ngày, trước 2 giờ, đến hạn, trễ 1 ngày" → sẽ tạo 4 bản ghi nhắc:
- `19/04/2026 17:00` — nhắc trước 1 ngày
- `20/04/2026 15:00` — nhắc trước 2 giờ
- `20/04/2026 17:00` — đến hạn
- `21/04/2026 17:00` — trễ 1 ngày

### Bước 5: Khi cán bộ A hoàn thành trước hạn

- Cán bộ A báo cáo hoàn thành → hệ thống gửi thông báo "Công việc báo cáo hoàn thành" đến quản lý.
- Quản lý xác nhận → hệ thống gửi thông báo "Công việc được xác nhận" đến cán bộ A.
- **Các lịch nhắc của công việc này (của cán bộ A) tự động hủy** — không nhắc nữa.

Cán bộ B chưa làm xong → các lịch nhắc của B vẫn chạy theo đúng mốc.

### Bước 6: Người dùng thấy thông báo

- **Qua SMS**: nhận tin nhắn trên điện thoại.
- **Qua Email**: nhận email HTML đẹp với tiêu đề + nội dung công việc.
- **Qua Zalo**: nhận tin theo template Zalo OA đã đăng ký.
- **Qua Push trình duyệt**: hiện thông báo trên góc màn hình trình duyệt (khi user đang có trang web mở hoặc kể cả khi đóng nhưng trình duyệt chạy).
- **Trên trang web**: icon chuông hiện số thông báo chưa đọc. Click icon thấy list notification, click vào item → tự động đánh dấu đã đọc + điều hướng đến công việc liên quan.

---

## 5. Hệ thống lưu gì

### Log thông báo cho user

Mỗi thông báo gửi cho user được lưu trong hệ thống. User có trang inbox để xem lại toàn bộ thông báo đã nhận, bao gồm cả thông báo đã đọc lẫn chưa đọc. User có thể đánh dấu đã đọc, xóa từng item.

### Log gửi tin cho quản trị

Mỗi lượt gửi qua kênh được lưu đầy đủ trong bảng `notification_deliveries` (status, message_id, error_message, sent_at, channel). Quản trị tra cứu qua API `/api/notifications/logs` (list + filter + detail + stats). Log audit bất biến — không có endpoint xóa.

---

## 6. Độ tin cậy & xử lý lỗi

### Hỏng 1 kênh không ảnh hưởng kênh khác

Nếu SMS lỗi → Email vẫn gửi. Từng kênh xử lý độc lập.

### Tự retry

Nếu gửi thất bại do lỗi tạm thời (timeout, mạng…), hệ thống tự retry 2-3 lần. Sau đó ghi log thất bại vĩnh viễn.

### Đảm bảo không bỏ sót

Thông báo nằm trong hàng đợi — nếu server crash hoặc restart, các thông báo chưa gửi vẫn còn trong hàng đợi và sẽ tiếp tục xử lý khi server lên lại.

### Dedupe / Idempotency

Mỗi lần sự kiện xảy ra → tạo thông báo mới. Vd văn bản ban hành → nháp → ban hành sẽ gửi lại 2 lần (đây là behavior cố ý để không bỏ sót khi quản lý chỉnh sửa rồi ban hành lại).

Với nhắc lịch: mỗi bản ghi nhắc chỉ fire đúng 1 lần (không lặp).

---

## 7. Giới hạn & lưu ý

### Phụ thuộc cấu hình nhà cung cấp

- SMS yêu cầu credentials của PSC (Đầu số DVC-Danang).
- Email yêu cầu SMTP server (Gmail, Outlook, hoặc hosting).
- Zalo yêu cầu đăng ký Zalo OA + template ZNS với South Telecom.
- FCM (Push) yêu cầu project Firebase + service account JSON.

Nếu chưa cấu hình → tương ứng kênh đó sẽ báo lỗi "chưa cấu hình".

### Phụ thuộc dữ liệu người dùng

- Gửi SMS → user cần có số điện thoại.
- Gửi Email → user cần có email.
- Gửi Push → user cần đăng ký trên trình duyệt (cấp quyền thông báo).

Nếu user thiếu field → delivery đó được mark "skipped" (bỏ qua, không lỗi).

### Yêu cầu hạ tầng server

- **Queue worker** phải chạy liên tục trên server (xử lý nền). Khi dừng worker → thông báo ùn trong hàng đợi, không gửi được.
- **Cron job** chạy mỗi phút để fire lịch nhắc. Không có cron → nhắc lịch không hoạt động.
- Cần monitor 2 thành phần này để đảm bảo thông báo hoạt động.

### Zalo ZNS gửi theo template

Không gửi được text tự do qua Zalo. Mỗi loại thông báo cần template đã đăng ký trước với Zalo — nội dung bám theo placeholder của template (vd `{customer_name}`, `{task_name}`). Thay đổi nội dung cần cập nhật template trên cả Zalo portal và backend.

### Push chỉ cho trình duyệt web

FCM trong hệ thống này phục vụ **web push** (trình duyệt) — không phục vụ app mobile. Mai sau cần mở rộng sang app thì phần kiến trúc đã sẵn sàng.

---

## 8. Mở rộng tương lai

Kiến trúc được thiết kế mở, có thể thêm:

- **Module mới** (vd module Quản lý đào tạo, module Quản lý công văn) — chỉ cần đăng ký trong hệ thống notification là tự có bộ cấu hình riêng.
- **Kênh mới** (vd Telegram, Viber, Microsoft Teams) — thêm 1 handler là dùng được.
- **Loại thông báo mới** (vd báo cáo hàng tuần, sinh nhật nhân viên) — thêm event + nội dung là xong.
- **Ứng dụng mobile push** — khi có app, thêm token mobile là hoạt động (hiện đã hỗ trợ FCM token cho web, mở rộng sang mobile không cần refactor).

---

## 9. Tóm lại

Hệ thống thông báo **tự động hóa hoàn toàn** việc gửi tin theo nghiệp vụ + lịch nhắc, **linh hoạt** cho quản trị bật/tắt + cấu hình từng mốc, **đảm bảo tin cậy** qua hàng đợi và retry, **minh bạch** với log và lịch sử đầy đủ, và **sẵn sàng mở rộng** cho module/kênh/app mới.
