# Hướng Dẫn Sử Dụng — Vai Trò Trưởng Phòng

> Ngày tạo: 16:50:31 25/08/2026  
> Cập nhật lần cuối: 16:50:31 25/08/2026

Dành cho người theo dõi công việc ở cấp phòng: nhìn được toàn bộ việc của phòng
mình, xuất báo cáo tháng, đồng thời vẫn tự thực hiện và báo cáo việc được giao.

**Làm trước:** đăng nhập, cài miniapp và bật thông báo theo
[phần chung](huong-dan-qlcv-chung_165031_25082026.md) — mục 3, 4, 5.

**Tài khoản thử:** mật khẩu `123123`.

| Tài khoản | Phòng ban | Trong dữ liệu mẫu |
|---|---|---|
| `truongphong1` | Phòng Hành chính - Tổng hợp | 2 công việc, 1 đơn thư *Mới tiếp nhận* |
| `truongphong2` | Phòng Kế hoạch - Tài chính | 2 công việc, 1 đơn thư *Đang xử lý* |
| `truongphong3` | Phòng Kỹ thuật - Công nghệ | 2 công việc, 1 đơn thư *Đã hoàn thành* |

Số công việc trên là của dữ liệu mẫu vừa seed. Sau khi mọi người thao tác thử,
con số thực tế sẽ khác.

---

## 1. Khác Nhân viên ở đúng hai điểm

Vai trò Trưởng phòng = toàn bộ quyền của Nhân viên, **cộng thêm**:

| Điểm khác | Ý nghĩa |
|---|---|
| **Phạm vi xem cấp phòng** | Màn Tổng quan tính trên **mọi công việc của các phòng ban bạn là thành viên**, kể cả việc không giao cho bạn |
| **Xuất báo cáo tháng** | Có thêm nút xuất báo cáo giao ban tháng ra Excel |

Ngoài hai điểm đó, thao tác hằng ngày giống hệt Nhân viên: cập nhật tiến độ, gửi
báo cáo, ghi chú, xử lý đơn thư của phòng. Xem chi tiết ở
[hướng dẫn Nhân viên](huong-dan-qlcv-nhan-vien_165031_25082026.md) mục 2–6.

Trưởng phòng **không** tạo văn bản, **không** giao việc, **không** duyệt hoàn
thành và **không** điều chuyển công việc. Những việc đó thuộc Quản lý chung.

> **Lưu ý về dữ liệu mẫu:** ba tài khoản `truongphong1-3` chưa được giao việc
> nào, nên màn **Công việc được giao** sẽ trống. Vào **Tổng quan công việc** để
> thấy việc của phòng. Muốn thử luồng báo cáo, hãy nhờ `quanly1` giao cho bạn một
> việc, hoặc dùng tài khoản `nhanvien*` cùng phòng.

---

## 2. Bạn nhìn thấy những gì

**Trên web** — menu **Theo dõi công việc** hiện 3 mục, giống Nhân viên:
Tổng quan công việc, Công việc được giao, Quản lý đơn thư.

**Trên miniapp** — dùng 4 tab: Trang chủ, Được giao, Đơn thư, Thống kê. Hai tab
Đang giao và Văn bản bấm vào sẽ báo chưa được phân quyền.

Menu giống Nhân viên, nhưng **nội dung màn Tổng quan khác hẳn**: đây mới là chỗ
thể hiện vai trò trưởng phòng.

---

## 3. Theo dõi công việc cấp phòng

Vào **Theo dõi công việc → Tổng quan công việc** (web) hoặc tab **Thống kê**
(miniapp).

### 3.1. Thẻ chỉ số

Tổng công việc, Hoàn thành, Chưa thực hiện, Đang thực hiện, Chờ duyệt, Tạm dừng,
Đã huỷ, Quá hạn, Tỷ lệ hoàn thành — tính trên **toàn bộ phòng bạn**.

Bấm vào một thẻ để mở danh sách công việc đứng sau con số đó.

### 3.2. Biểu đồ

- **Biểu đồ Trạng thái công việc phòng ban** — cơ cấu theo trạng thái xử lý.
- **Biểu đồ Tiến độ công việc phòng ban** — cơ cấu theo tiến độ thời hạn
  (Chưa đến hạn / Sớm hạn / Đúng hạn / Trễ hạn / Quá hạn).

### 3.3. Bảng chi tiết theo phòng ban / người thực hiện

Mỗi dòng là một người, các cột là số việc theo trạng thái (Tổng, Chưa thực hiện,
Đang thực hiện, Hoàn thành, Tạm dừng, Đã huỷ, Quá hạn) kèm tiến độ và thời hạn.
Đây là bảng dùng để nhìn ra ai đang tồn việc, ai đang quá hạn.

### 3.4. Bộ lọc

Khoảng ngày, phòng ban, nhân viên. Bộ lọc phòng ban chỉ chọn được trong phạm vi
của bạn — chọn phòng ngoài phạm vi thì kết quả trả về rỗng chứ không mở rộng
phạm vi xem.

> **Kiêm nhiệm nhiều phòng:** nếu bạn là thành viên của nhiều phòng ban, Tổng
> quan gộp cả các phòng đó, không chỉ một phòng.

---

## 4. Xuất báo cáo tháng

1. Vào **Tổng quan công việc**.
2. Đặt bộ lọc khoảng thời gian về tháng cần báo cáo.
3. Bấm **Xuất báo cáo** → chọn **Xuất báo cáo tháng**.
4. File Excel báo cáo giao ban tháng được tải về.

Số liệu trong file theo đúng phạm vi phòng ban của bạn.

Ngoài ra, ở màn **Công việc được giao** vẫn có nút xuất Excel danh sách việc của
riêng bạn theo bộ lọc đang áp.

---

## 5. Đơn thư của phòng

Giống Nhân viên: bạn thấy và xử lý đơn thư của các phòng ban mình là thành viên.

- Tạo đơn mới, cập nhật thông tin, đổi trạng thái
  (Mới tiếp nhận → Đang xử lý → Đã hoàn thành).
- Đơn **Đã hoàn thành** bị khoá, cần Quản lý chung mở khoá mới sửa được.
- Bộ lọc: từ khoá, trạng thái, phòng ban, ngày gửi, hạn xử lý.

Dữ liệu mẫu cho mỗi phòng đúng một đơn ở một trạng thái khác nhau — dùng để kiểm
chứng rằng bạn không thấy đơn của phòng khác.

---

## 6. Việc bạn KHÔNG làm được

| Việc | Thuộc về |
|---|---|
| Tạo, sửa, ban hành văn bản giao việc | Quản lý chung |
| Thêm công việc vào văn bản, giao việc cho nhân viên | Quản lý chung |
| Xác nhận hoàn thành / từ chối báo cáo | Quản lý chung |
| Điều chuyển công việc sang người khác | Quản lý chung |
| Tạm dừng, huỷ, mở lại công việc | Quản lý chung |
| Xem đơn thư ngoài phòng mình, mở khoá đơn đã hoàn thành | Quản lý chung |
| Sửa danh mục, phòng ban, danh sách nhân viên | Quản lý chung |
| Cấu hình kênh thông báo hệ thống | Quản trị (`admin`) |

Nếu đơn vị muốn trưởng phòng giao việc hoặc duyệt hoàn thành, quản trị bổ sung
quyền cho vai trò **Trưởng phòng** ở màn Vai trò — không cần sửa mã nguồn.

---

## 7. Hiểu đúng về "Người đại diện" phòng ban

Trong danh sách nhân viên của phòng, một người có gắn dấu **người đại diện**.
Đây **chỉ là trường tiện ích cho giao diện**: khi giao việc cho cả phòng, hệ
thống điền sẵn người này làm đầu mối nhận việc.

Nó **không phải** chức vụ trưởng phòng và **không** ảnh hưởng tới quyền hạn. Bạn
là trưởng phòng vì được gán **vai trò Trưởng phòng**, không phải vì cờ này. Trong
dữ liệu mẫu, người đại diện của ba phòng là `quanly1`, `nhanvien3`, `nhanvien5` —
cố ý để khác với ba tài khoản trưởng phòng, đúng bằng cách đó mới thấy hai khái
niệm này tách rời nhau.

---

## 8. Kịch bản thử 10 phút

1. Đăng nhập web bằng `truongphong2` / `123123` (Phòng Kế hoạch - Tài chính).
2. Bật thông báo đẩy ở trang cá nhân → tab Thông báo.
3. Mở **Công việc được giao** — danh sách trống, đúng như mô tả ở mục 1.
4. Mở **Tổng quan công việc**: thấy 2 công việc của phòng —
   *"Lập dự toán kinh phí quý IV"* (Đang thực hiện, 60%, của `nhanvien3`) và
   *"Đối chiếu quyết toán kinh phí năm 2026"* (Chưa thực hiện, của `nhanvien4`).
5. Bấm vào thẻ **Đang thực hiện** để mở danh sách công việc đứng sau con số.
6. Bấm **Xuất báo cáo → Xuất báo cáo tháng**, mở file Excel đối chiếu.
7. Mở **Quản lý đơn thư**: chỉ thấy đơn *"Phản ánh việc chậm thanh toán chế độ
   hỗ trợ"* của phòng mình, không thấy hai đơn còn lại.
8. Để so sánh phạm vi: đăng xuất, đăng nhập lại bằng `nhanvien4` cùng phòng và mở
   Tổng quan — chỉ thấy 1 công việc của riêng người đó.
