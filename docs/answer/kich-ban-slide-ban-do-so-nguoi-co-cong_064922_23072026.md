# Kịch bản Slide — Hệ thống Quản lý & Bản đồ số Người có công

> Ngày tạo: 06:49:22 23/07/2026
> Cập nhật lần cuối: 06:49:22 23/07/2026

Tài liệu gồm **2 bộ slide** soạn dựa trên chức năng thực tế của module **Người có công (Beneficiary)** ở backend `core-be` và cụm module bản đồ số (`map`, `map-studio`, `map-import-log`) ở frontend `core-fe`:

- **Bộ 1 (9 slide)** — Giới thiệu hệ thống, trọng tâm **bản đồ số**: tìm kiếm • chỉ đường • cung cấp thông tin.
- **Bộ 2 (9 slide)** — **Luồng hoạt động** & hướng dẫn người dùng vận hành từ A→Z.

Mỗi slide gồm: **Tiêu đề · Nội dung trình bày · Ghi chú thuyết trình (🎤)**. Bản trình chiếu HTML tương ứng: `kich-ban-slide-ban-do-so-nguoi-co-cong.html`.

---

# BỘ 1 — GIỚI THIỆU HỆ THỐNG BẢN ĐỒ SỐ NGƯỜI CÓ CÔNG

## Slide 1 — Trang bìa
- **Tiêu đề:** HỆ THỐNG QUẢN LÝ & BẢN ĐỒ SỐ NGƯỜI CÓ CÔNG
- **Phụ đề:** Số hóa hồ sơ – Định vị tận nhà – Tri ân đúng người, đúng chỗ
- 🎤 "Đây là hệ thống giúp chính quyền phường/xã quản lý người có công không chỉ trên giấy tờ, mà đặt từng con người lên đúng vị trí trên bản đồ số của địa phương."

## Slide 2 — Bối cảnh & Bài toán
- Mỗi phường/xã quản lý hàng trăm–hàng nghìn người có công (Liệt sĩ, Thương binh, Bà mẹ VNAH, nạn nhân chất độc hóa học…).
- Hồ sơ giấy phân tán → khó tra cứu, khó biết **ai đang ở đâu** khi cần thăm hỏi, chi trả, xác minh.
- Cán bộ mới nhận địa bàn không nắm được vị trí thực tế của đối tượng.
- 🎤 "Vấn đề lớn nhất không phải là lưu dữ liệu, mà là *tìm được đúng người và đến được tận nơi*."

## Slide 3 — Tổng quan kiến trúc hệ thống
- **Backend `core-be`** (Laravel modular): module **Beneficiary** — quản lý người có công, hộ gia đình, thân nhân, trợ cấp, lịch viếng thăm; đa tổ chức (mỗi phường/xã là 1 tenant).
- **Frontend `core-fe`** (Vue 3): cụm module **bản đồ số** — `map`, `map-studio`, `map-import-log`.
- Mỗi hồ sơ có **tọa độ (lat/lng)** → dữ liệu quản lý tự động lên bản đồ.
- 🎤 "Điểm khác biệt: dữ liệu nghiệp vụ và dữ liệu bản đồ là **một**, không phải hai hệ thống rời."

## Slide 4 — Trái tim hệ thống: Bản đồ số người có công
- Hiển thị toàn bộ người có công trên **nền ranh giới phường/xã** thật (GeoJSON).
- Mỗi người = 1 **marker biểu tượng quốc huy**; phóng to hiện tên + loại đối tượng.
- Tự **gom cụm (cluster)** khi thu nhỏ — xem cả nghìn điểm không rối.
- Đã chạy thực tế với **~2.000 điểm** trên phường Xuân Phú.
- 🎤 "Toàn cảnh người có công của cả phường gói gọn trong một màn hình, mượt mà dù dữ liệu lớn."

## Slide 5 — Tìm kiếm & Lọc thông minh
- **Tìm kiếm tức thì** theo: Tên · Số điện thoại · CCCD · Địa chỉ.
- **Lọc theo nhóm đối tượng** — 12 nhóm theo Pháp lệnh 02/2020 (Liệt sĩ, Thương binh, Bệnh binh, Bà mẹ VNAH…).
- Bản đồ + bảng thống kê **cập nhật đồng bộ**: Tổng số / Đang hiển thị theo bộ lọc.
- Lọc theo **tổ dân phố** để khoanh vùng địa bàn.
- 🎤 "Cần tìm nhanh 'các thương binh ở Tổ 5' — vài cú chạm, bản đồ chỉ còn đúng nhóm đó."

## Slide 6 — Cung cấp thông tin: Hồ sơ ngay trên bản đồ
- Click marker mở popup gồm: Họ tên, Loại người có công, Ngày sinh.
- Địa chỉ, **Tiểu sử / mô tả** đóng góp.
- **Lịch sử thăm hỏi** (đã thăm dịp nào, ai thăm, mục đích).
- Hộ gia đình & thân nhân liên quan. Thông tin tải theo lớp (nhẹ trước – chi tiết sau) để mở nhanh.
- 🎤 "Không cần mở hồ sơ giấy — mọi thông tin tri ân hiện ngay tại điểm trên bản đồ."

## Slide 7 — Chỉ đường tận nhà 🧭
- Mỗi popup có nút **"Chỉ đường"** → mở Google Maps dẫn đường tới đúng tọa độ/địa chỉ nhà.
- **Định vị "Vị trí hiện tại của tôi"** để đo đường từ chỗ cán bộ đang đứng.
- Công cụ **ghim/chọn tọa độ trên bản đồ** khi cập nhật vị trí nhà (kéo marker, tìm địa chỉ, sao chép tọa độ).
- Giá trị: cán bộ mới, đoàn thăm hỏi dịp 27/7 – Tết đến tận nơi, không lạc.
- 🎤 "Từ danh sách tên trừu tượng thành lộ trình cụ thể đến từng mái nhà."

## Slide 8 — Nhiều chế độ xem & Quản lý lớp bản đồ
- **2 chế độ:** Bản đồ *Hộ gia đình* ↔ *Người có công* (chuyển 1 chạm).
- **Bản đồ thường ↔ Vệ tinh**, toàn màn hình.
- **Map Studio (quản trị):** thêm/ẩn/sắp xếp **lớp bản đồ**, nhập dữ liệu **GeoJSON**, khôi phục dữ liệu gốc.
- **Nhật ký nhập liệu (Map Import Log):** truy vết mỗi lần nạp dữ liệu bản đồ.
- 🎤 "Bản đồ không cố định — quản trị viên tự chồng thêm lớp ranh giới, khu vực, dữ liệu riêng của địa phương."

## Slide 9 — Hiệu năng, An toàn & Lộ trình mở rộng
- Tối ưu: tải trước song song, Supercluster, marker pool, debounce → mở bản đồ **< 3 giây (4G)**, mượt tới ~5.000 điểm/phường.
- **Đa tổ chức có kiểm soát:** dữ liệu tách theo từng phường/xã (tenant), chặn truy cập chéo.
- **Lộ trình:** JSON tĩnh → API backend (ranh giới, điểm, chi tiết, thống kê) → tìm kiếm server-side → cluster theo vùng khi lên quy mô toàn thành phố.
- 🎤 "Hôm nay là một phường, kiến trúc đã sẵn cho cả thành phố."

---

# BỘ 2 — LUỒNG HOẠT ĐỘNG & HƯỚNG DẪN SỬ DỤNG

## Slide 1 — Trang bìa
- **Tiêu đề:** HƯỚNG DẪN SỬ DỤNG HỆ THỐNG NGƯỜI CÓ CÔNG
- **Phụ đề:** Từ tiếp nhận hồ sơ → lên bản đồ → tra cứu & tri ân
- 🎤 "Phần này đi theo đúng thao tác hằng ngày của cán bộ phường."

## Slide 2 — Đăng nhập & Chọn địa bàn
- Cán bộ **đăng nhập**, hệ thống cấp phiên làm việc.
- **Chọn phường/xã (tổ chức)** đang phụ trách — mọi dữ liệu tự lọc theo địa bàn này.
- Phân quyền theo chức năng (xem / tạo / sửa / duyệt trạng thái / báo cáo).
- 🎤 "Bạn chỉ thấy và sửa được dữ liệu của phường mình — an toàn tuyệt đối."

## Slide 3 — Bước 1: Thiết lập danh mục (làm 1 lần)
- Khai báo **Tổ dân phố** (để khoanh vùng địa bàn).
- Khai báo **Chính sách trợ cấp** (mức tiền, căn cứ pháp lý, hiệu lực).
- Có sẵn 12 nhóm **loại đối tượng** theo Pháp lệnh — không cần tự nhập.
- 🎤 "Giống như dựng khung nhà trước khi đưa hồ sơ vào — làm một lần, dùng mãi."

## Slide 4 — Bước 2: Tiếp nhận & Tạo hồ sơ người có công
- **1 màn hình duy nhất** tạo trọn gói: thông tin NCC + **Phân loại đối tượng** (nhiều loại, chọn 1 loại chính).
- Kèm **Hộ gia đình** (nếu mới) và **Thân nhân** đi cùng — không nhập rời từng bảng.
- Nhập Excel hàng loạt (có file mẫu tải sẵn) khi số hóa dữ liệu cũ.
- 🎤 "Một biểu mẫu — tạo luôn người có công, hộ, và thân nhân; giảm 3–4 lần nhập tay."

## Slide 5 — Bước 3: Gán vị trí lên bản đồ 📍
- Mở công cụ **"Chọn vị trí trên bản đồ"**: tìm địa chỉ → ghim marker → kéo chỉnh cho đúng nhà.
- Dùng **"Vị trí hiện tại"** khi cán bộ đang đứng tại nhà đối tượng.
- Lưu **tọa độ (vĩ độ/kinh độ)** vào hồ sơ → điểm tự xuất hiện trên bản đồ số.
- 🎤 "Chỉ khi có tọa độ, người có công mới 'lên bản đồ' — đừng bỏ qua bước này khi đi thực địa."

## Slide 6 — Bước 4: Phân loại, Thân nhân & Trợ cấp
- **Phân loại:** cập nhật giấy tờ công nhận (quyết định, ngày, cơ quan) khi có đủ hồ sơ.
- **Thân nhân & quan hệ hưởng chế độ:** khai báo con/vợ-chồng/cha-mẹ; hệ thống tự tính điều kiện hưởng theo tuổi & tình trạng.
- **Cấp trợ cấp:** chọn chính sách còn hiệu lực; khi Nhà nước tăng mức → 1 thao tác **"Ban hành mức mới"** tự nối tiếp cho mọi đối tượng.
- 🎤 "Nghiệp vụ chi trả và điều kiện hưởng được hệ thống canh giúp, giảm sai sót thủ công."

## Slide 7 — Bước 5: Vòng đời hồ sơ & Lịch viếng thăm
- **Trạng thái hồ sơ:** Chờ công nhận → Đang hưởng → (Đã mất / Chuyển đi / Tạm dừng). Mọi thay đổi được **ghi lịch sử**.
- Khi đối tượng mất/chuyển đi → hệ thống **tự dừng trợ cấp** liên quan.
- **Lịch viếng thăm/tặng quà** (Tết, 27/7…) tự sinh; cán bộ đánh dấu **"Đã thăm"** kèm **ảnh minh chứng**.
- 🎤 "Toàn bộ diễn biến của một hồ sơ được lưu vết minh bạch từ đầu đến cuối."

## Slide 8 — Bước 6: Tra cứu trên bản đồ số
- Vào bản đồ → **tìm** theo tên/địa chỉ hoặc **lọc** theo nhóm đối tượng/tổ dân phố.
- **Click marker** → xem hồ sơ, tiểu sử, lịch sử thăm hỏi.
- Bấm **"Chỉ đường"** → dẫn đường tới tận nhà. Mẹo: dùng cluster để xem mật độ, chuyển vệ tinh để nhận diện địa hình.
- 🎤 "Đây là màn hình dùng nhiều nhất khi đi thực địa và khi công khai tra cứu."

## Slide 9 — Bước 7: Báo cáo, Thống kê & Tổng kết luồng
- **Thống kê:** tổng số theo trạng thái, theo nhóm đối tượng, số hộ, tổng kinh phí trợ cấp.
- **Xuất Excel** đầy đủ để báo cáo cấp trên.
- **Luồng tổng thể:** Đăng nhập → Danh mục → Tạo hồ sơ → Gán tọa độ → Quản lý trợ cấp/thân nhân → Bản đồ tra cứu & chỉ đường → Báo cáo.
- 🎤 "Một vòng khép kín: dữ liệu nhập đúng một lần, phục vụ cả quản lý, bản đồ, lẫn báo cáo."

---

**Ghi chú chung khi trình bày:**
- Chèn ảnh chụp màn hình thật từ `core-fe` (màn bản đồ, popup, nút Chỉ đường, Map Studio) để tăng thuyết phục.
- Bộ 1 nên demo trực tiếp: tìm kiếm → click marker → chỉ đường. Bộ 2 nên demo: tạo 1 hồ sơ mới và gán tọa độ.
