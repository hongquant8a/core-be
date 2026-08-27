# Hướng Dẫn Sử Dụng — Vai Trò Quản Lý Chung

> Ngày tạo: 16:50:31 25/08/2026  
> Cập nhật lần cuối: 16:50:31 25/08/2026

Dành cho người điều hành phân hệ: tạo văn bản, giao việc, theo dõi toàn tổ chức,
duyệt hoàn thành và quản trị danh mục.

**Làm trước:** đăng nhập, cài miniapp và bật thông báo theo
[phần chung](huong-dan-qlcv-chung_165031_25082026.md) — mục 3, 4, 5.

**Tài khoản thử:** `quanly1` / `123123` (vai trò *Quản lý công việc*), thuộc
Phòng Hành chính - Tổng hợp và là người giao toàn bộ công việc mẫu.

---

## 1. Bạn nhìn thấy những gì

**Trên web** — menu **Theo dõi công việc** đầy đủ:

| Mục | Dùng để |
|---|---|
| Tổng quan công việc | Số liệu **toàn tổ chức** + xuất báo cáo tháng |
| Văn bản giao việc | Tạo văn bản, thêm công việc, ban hành |
| Công việc đang giao | Theo dõi việc mình đã giao, duyệt hoàn thành |
| Công việc được giao | Việc người khác giao cho chính bạn |
| Trình diễn công việc | Màn hình toàn khung để chiếu họp giao ban |
| Quản lý đơn thư | Đơn thư **mọi phòng ban** |
| Danh mục | Loại văn bản, Loại công việc, Phòng ban, Nhân viên |

**Trên miniapp** — dùng đủ 6 tab: Trang chủ, Đang giao, Được giao, Đơn thư, Văn
bản, Thống kê.

Nhóm **Thông báo nhắc lịch** (cấu hình thông báo, mẫu ZNS, nhật ký) **không**
thuộc vai trò này — chỉ tài khoản `admin` thấy.

---

## 2. Luồng nghiệp vụ đầy đủ

```
Tạo văn bản (Nháp)
   └─> Thêm công việc, phân công phòng ban / người thực hiện
         └─> Ban hành văn bản        → thông báo "Ban hành văn bản" + "Được giao việc mới"
               └─> Nhân viên cập nhật tiến độ, gửi báo cáo
                     └─> Báo cáo 100% → Chờ duyệt   → thông báo "Báo cáo hoàn thành"
                           ├─> Xác nhận hoàn thành  → Hoàn thành, đóng việc
                           └─> Từ chối (kèm lý do)  → quay lại Đang thực hiện
```

---

## 3. Tạo văn bản giao việc

1. **Theo dõi công việc → Văn bản giao việc → Thêm Mới**.
2. Điền phần **Thông tin Văn bản giao việc**:
   - **Tên văn bản** — bắt buộc.
   - **Tóm tắt nội dung**.
   - **Ngày ban hành**.
   - **Loại văn bản giao việc** — lấy từ danh mục (Công văn / Kế hoạch / Thông báo).
   - **Trạng thái** — *Nháp* hoặc *Đã ban hành*.
   - **Kênh thông báo tức thì** — kênh gửi khi ban hành, giao việc, hoàn thành,
     xác nhận (Email, Thông báo đẩy, SMS, Zalo OA/ZNS, Telegram tuỳ cấu hình).
   - **Nhắc lịch** — thêm các mốc nhắc *Trước / Đúng giờ / Sau* kèm khoảng cách
     (phút, giờ, ngày) và kênh gửi cho từng mốc.
   - **Văn bản đính kèm** — PDF, DOC, DOCX, TXT, tối đa 50MB mỗi tệp.
3. Bấm **Lưu**.

Văn bản để ở **Nháp** thì chưa ai nhận được thông báo. Chuyển sang **Ban hành**
khi đã phân công xong.

### 3.1. Phân tích AI (tuỳ chọn)

Có sẵn nội dung thông báo kết luận / văn bản giao việc thì không phải gõ tay:

1. Trong màn chi tiết văn bản, mở khu **Phân tích công việc**.
2. Dán toàn bộ nội dung vào ô văn bản.
3. Bấm **Phân Tích AI**.

AI tự điền **tên văn bản**, **tóm tắt**, **ngày văn bản**, và đề xuất **danh sách
đầu việc tạm**. Với mỗi đầu việc, nếu tên đơn vị trong văn bản khớp được một
phòng ban, hệ thống điền sẵn phòng ban đó **và người đại diện của phòng** làm
người thực hiện.

> Kết quả AI là **đề xuất**, chưa lưu. Bắt buộc rà lại tên công việc, phòng ban,
> người thực hiện và thời hạn trước khi bấm lưu. Phòng ban khớp bằng so sánh
> chuỗi nên có thể đoán nhầm.

---

## 4. Thêm công việc và giao việc

Trong màn chi tiết văn bản → **Danh sách công việc** → **Thêm công việc**.

| Trường | Ghi chú |
|---|---|
| Tên công việc | Bắt buộc |
| Mô tả | |
| Loại công việc | Từ danh mục (Thường xuyên / Đột xuất / Trọng tâm) |
| Người quản lý | Người giao việc, mặc định là bạn |
| **Phân công** | Mỗi dòng gồm: Phòng ban + Người thực hiện + Vai trò phòng ban (Chính / Phối hợp) + Vai trò cá nhân (Chính / Hỗ trợ) |
| Mức ưu tiên | Thấp / Trung bình / Cao / Khẩn cấp |
| Thời hạn công việc | *Có thời hạn* (kèm ngày bắt đầu, ngày kết thúc) hoặc *Không thời hạn* |
| Tệp đính kèm | Tối đa 10 tệp |
| Nhắc lịch | Các mốc nhắc riêng cho công việc này |

**Chọn phòng ban thì người thực hiện được điền sẵn** bằng người đại diện của
phòng đó — đổi lại được. Để trống người thực hiện thì khi lưu hệ thống tự lấy
người đại diện.

> **Một công việc gắn một phòng ban.** Ở màn thêm mới, khai nhiều dòng phân công
> thuộc nhiều phòng thì khi lưu hệ thống tạo **nhiều công việc**, mỗi phòng một
> công việc. Khi sửa thì chỉ còn một phòng ban, đổi phòng ban sẽ ghi đè danh sách
> người thực hiện.

> **Phòng ban chưa có người đại diện** thì không giao việc theo phòng được — hệ
> thống báo *"Phòng ban ID X chưa có người đại diện"*. Đặt người đại diện ở
> **Danh mục → Phòng ban** trước.

---

## 5. Ban hành văn bản

Trong màn chi tiết văn bản, bấm **Ban hành**. Lúc này hệ thống gửi thông báo
*Ban hành văn bản* và *Được giao việc mới* tới người thực hiện qua các kênh đã
chọn. Bấm **Chuyển nháp** để rút lại.

Ở màn danh sách văn bản có bộ lọc theo từ khoá, loại văn bản, trạng thái
(Nháp / Đã ban hành), tháng hoặc khoảng ngày; và các thẻ Tổng / Đã ban hành /
Nháp. Cột **Số CV** cho biết văn bản có bao nhiêu công việc, cột **Tiến độ** là
mức hoàn thành trung bình.

---

## 6. Theo dõi và duyệt công việc

Vào **Theo dõi công việc → Công việc đang giao**.

### 6.1. Xác nhận hoàn thành

1. Mở công việc đang ở trạng thái **Chờ duyệt**.
2. Đọc báo cáo trong mục **Danh sách báo cáo** — số văn bản, trích yếu, nội dung,
   tệp đính kèm.
3. Bấm **Xác nhận hoàn thành** → công việc chuyển **Hoàn thành** và đóng lại.
   Người thực hiện nhận thông báo *Công việc được xác nhận*.

### 6.2. Từ chối

Bấm **Từ chối**, nhập **lý do từ chối**. Công việc quay về **Đang thực hiện**, chi
tiết hiện nhãn *Đã bị từ chối* để người thực hiện đọc lý do và làm lại.

Chỉ công việc đang ở **Chờ duyệt** mới duyệt hoặc từ chối được.

> **Chỉ người giao việc mới duyệt được.** Xác nhận hoàn thành, từ chối, tạm dừng
> và huỷ chỉ áp dụng cho công việc do **chính bạn** giao — đúng những việc nằm ở
> màn *Công việc đang giao*. Việc do người khác giao thì bạn xem được nhưng không
> thao tác trạng thái.

### 6.3. Các thao tác khác

| Thao tác | Tác dụng |
|---|---|
| **Tạm dừng** | Dừng theo dõi tạm thời |
| **Huỷ** | Đóng công việc, không tính vào tỷ lệ hoàn thành |
| **Mở lại** | Mở công việc đã đóng. Trạng thái mới suy từ tiến độ: 0% → Chưa thực hiện, 1–99% → Đang thực hiện, 100% → Chờ duyệt. Việc đã hoàn thành 100% và xác nhận thì không mở lại được |
| **Điều chuyển** | Chuyển việc sang người khác |
| **Ghi chú** | Trao đổi trong công việc |

### 6.4. Điều chuyển công việc

1. Mở chi tiết công việc → khu **Điều chuyển**.
2. Chọn **Phòng ban** → hệ thống tải danh sách nhân viên và **điền sẵn người đại
   diện** của phòng đó.
3. Đổi sang người khác nếu cần. Danh sách đã loại bạn và những người đang được
   giao việc này.
4. Nhập **lý do điều chuyển**, bấm xác nhận.

Làm được cả trên miniapp, ở chi tiết việc trong tab **Đang giao**.

---

## 7. Đơn thư

Bạn thấy và xử lý **đơn thư của mọi phòng ban**, khác với nhân viên và trưởng
phòng chỉ thấy đơn phòng mình.

- Tạo, sửa, đổi trạng thái, xoá, xoá hàng loạt, đổi trạng thái hàng loạt, xuất Excel.
- **Mở khoá đơn đã hoàn thành**: đơn ở trạng thái *Đã hoàn thành* bị khoá với mọi
  người; chỉ vai trò này mở khoá được để sửa lại.
- Bộ lọc: từ khoá (tên, CCCD, SĐT, email, nội dung), trạng thái, phòng ban,
  khoảng ngày gửi, khoảng hạn xử lý.

Trạng thái đơn: Mới tiếp nhận → Đang xử lý → Đã hoàn thành (còn Tạm dừng, Đã huỷ).

---

## 8. Báo cáo thống kê

### 8.1. Tổng quan công việc

Phạm vi **toàn tổ chức**, không giới hạn phòng ban.

- **Thẻ chỉ số**: Tổng công việc, Hoàn thành, Chưa thực hiện, Đang thực hiện,
  Chờ duyệt, Tạm dừng, Đã huỷ, Quá hạn, Tỷ lệ hoàn thành. Bấm vào thẻ để mở danh
  sách công việc đứng sau con số.
- **Biểu đồ Trạng thái công việc phòng ban** và **Biểu đồ Tiến độ công việc phòng ban**.
- **Bảng chi tiết** theo phòng ban / người thực hiện.
- **Bộ lọc**: khoảng ngày, phòng ban, nhân viên.
- **Xuất báo cáo → Xuất báo cáo tháng**: file Excel báo cáo giao ban tháng.

### 8.2. Trình diễn công việc

Màn hình toàn khung, chữ và biểu đồ phóng to, dùng để chiếu trong cuộc họp giao
ban. Vẫn bấm vào biểu đồ để mở danh sách công việc chi tiết.

### 8.3. Xuất dữ liệu ở từng màn

Các màn danh sách (văn bản, công việc, đơn thư, danh mục, nhân viên) đều có nút
xuất Excel theo bộ lọc đang áp.

---

## 9. Quản trị danh mục

Nhóm **Danh mục** trong menu:

| Màn | Nội dung | Thao tác |
|---|---|---|
| **Loại văn bản** | Công văn, Kế hoạch, Thông báo… | Thêm, sửa, xoá, đổi trạng thái, xuất/nhập Excel |
| **Loại công việc** | Thường xuyên, Đột xuất, Trọng tâm… | Như trên |
| **Phòng ban** | Danh sách phòng, thành viên, **người đại diện**, thứ tự hiển thị | Như trên |
| **Nhân viên** | Gắn tài khoản người dùng vào phân hệ và vào phòng ban | Như trên |

**Đặt người đại diện phòng ban:** mở phòng ban → thêm nhân viên vào phòng →
chọn **Người đại diện** trong danh sách nhân viên đã thêm. Mỗi phòng chỉ một
người: đặt người mới thì cờ của người cũ tự bỏ.

> **Người đại diện không phải chức vụ và không phải phân quyền.** Đây chỉ là đầu
> mối để hệ thống điền sẵn khi giao việc hoặc điều chuyển theo phòng ban. Muốn
> một người có quyền xem cấp phòng thì gán cho họ **vai trò Trưởng phòng**, không
> phải đặt cờ đại diện.

**Thêm người dùng mới vào phân hệ:** tài khoản do quản trị hệ thống tạo ở module
Người dùng; sau đó bạn vào **Danh mục → Nhân viên → Thêm** để gắn tài khoản đó
vào phân hệ và phòng ban.

---

## 10. Việc bạn KHÔNG làm được

| Việc | Thuộc về |
|---|---|
| Tạo tài khoản người dùng, gán vai trò | Quản trị hệ thống (`admin`) |
| Sửa quyền của vai trò | Quản trị hệ thống |
| Bật/tắt kênh thông báo cho từng loại sự kiện | Quản trị hệ thống — **Thông báo nhắc lịch → Cấu hình thông báo** |
| Sửa mẫu tin nhắn Zalo ZNS | Quản trị hệ thống |
| Xem nhật ký gửi thông báo | Quản trị hệ thống |

---

## 11. Kịch bản thử 15 phút

1. Đăng nhập web bằng `quanly1` / `123123`, bật thông báo đẩy.
2. **Văn bản giao việc → Thêm Mới**: tạo văn bản *"Công văn thử nghiệm 09/CV-VP"*,
   để trạng thái **Nháp**, chọn kênh thông báo tức thì có **Thông báo đẩy**.
3. Trong chi tiết văn bản, thử **Phân tích AI**: dán vài dòng nội dung giao việc
   có nhắc tên phòng ban, xem hệ thống có điền sẵn phòng ban và người thực hiện không.
4. Thêm một công việc thủ công: chọn **Phòng Kỹ thuật - Công nghệ** → kiểm tra ô
   người thực hiện tự điền `nhanvien5` (người đại diện phòng đó); đổi sang
   `nhanvien10`, đặt ưu tiên **Cao**, thời hạn 7 ngày.
5. Bấm **Ban hành**. Kiểm tra `nhanvien10` có nhận được thông báo *Được giao việc mới*.
6. Đăng nhập `nhanvien10` ở trình duyệt khác, cập nhật tiến độ **100%** và gửi
   một báo cáo.
7. Quay lại `quanly1` → **Công việc đang giao**: công việc đã ở **Chờ duyệt**.
   Bấm **Từ chối** kèm lý do, xem `nhanvien10` nhận thông báo và thấy nhãn từ chối.
8. Cho `nhanvien10` báo cáo lại, rồi **Xác nhận hoàn thành**.
9. Thử **Điều chuyển** một công việc khác sang phòng ban khác — xác nhận người
   thực hiện được điền sẵn.
10. Vào **Tổng quan công việc**, đối chiếu số liệu và bấm **Xuất báo cáo tháng**.
11. Vào **Quản lý đơn thư**: thấy đủ 3 đơn của 3 phòng. Mở đơn *"Đề nghị hỗ trợ
    khắc phục sự cố đường truyền mạng"* (Đã hoàn thành) và thử **mở khoá**.
