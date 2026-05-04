# Giải pháp phòng họp không giấy đơn giản

**Ngày tạo:** 2026-04-30  
**Mục đích:** Xây dựng bản đặc tả giải pháp phòng họp không giấy theo hướng thực tiễn, dễ triển khai và sẵn sàng phát triển thành phần mềm dùng thật cho UBND phường/xã, HĐND và các cuộc họp định kỳ tại địa phương.

---

## 1. Định hướng chung

### 1.1 Mục đích của phần mềm

Phần mềm được định hướng để số hóa trọn vẹn vòng đời một cuộc họp: chuẩn bị trước họp, điều hành trong họp và tổng hợp sau họp. Trọng tâm là thay thế quy trình giấy tờ rời rạc bằng một luồng thống nhất, có dữ liệu theo thời gian thực, giúp lãnh đạo và đơn vị vận hành ra quyết định nhanh và chính xác hơn.

### 1.2 Ý nghĩa triển khai

- Giảm chi phí in ấn, phát hành tài liệu giấy và công việc thủ công.
- Nâng cao tính minh bạch trong điều hành, biểu quyết, điểm danh và theo dõi tiến độ họp.
- Chuẩn hóa quy trình phối hợp giữa chủ trì, thư ký/điều hành và đại biểu.
- Tạo nền tảng dữ liệu phục vụ báo cáo, tra cứu lịch sử và đánh giá hiệu quả cuộc họp.
- Phù hợp lộ trình chuyển đổi số cấp cơ sở, triển khai từng bước nhưng vẫn tạo giá trị ngay.

### 1.3 Định hướng chức năng

Giải pháp nên được thiết kế theo hướng **ít màn hình, thao tác rõ ràng, người dùng phổ thông dễ dùng**. Ở giai đoạn đầu, hệ thống ưu tiên nhóm chức năng cốt lõi, tránh dàn trải nghiệp vụ nâng cao:

- Tạo cuộc họp.
- Công khai cuộc họp và tài liệu.
- Quản lý chương trình họp.
- Quản lý tài liệu.
- Mời đại biểu theo danh sách hoặc theo nhóm.
- Gửi giấy mời và nhắc lịch.
- Đại biểu xác nhận tham gia/vắng mặt.
- Điểm danh QR hoặc điểm danh trong phần mềm.
- Đăng ký thảo luận/chất vấn.
- Biểu quyết khi được kích hoạt.
- Ghi chú cá nhân.
- Điều hành cuộc họp.
- Màn chiếu.
- Cập nhật kết luận.
- Lưu lượt xem cuộc họp và tài liệu.

Các chức năng nâng cao (AI âm thanh, realtime sâu, tích hợp đa kênh thông báo...) triển khai sau khi người dùng đã vận hành ổn định nhóm chức năng cốt lõi.

Hệ thống có 2 khu vực chính:

1. **Trang người dùng/trang công khai**
   - Người dân xem cuộc họp công khai.
   - Đại biểu đăng nhập để xem cuộc họp mình tham gia, tài liệu nội bộ, biểu quyết, ghi chú, điểm danh.

2. **Trang quản trị**
   - Quản trị hệ thống, tổ chức, tài khoản.
   - Quản lý danh mục.
   - Tạo và điều hành cuộc họp.

---

## 2. Form tạo cuộc họp

### 2.1 Thông tin chung cuộc họp

Các trường cần có:

- `title`: Tên cuộc họp, kiểu đoạn văn bản.
- `meeting_type_id`: Loại cuộc họp, kiểu lựa chọn.
- `is_public`: Công khai, kiểu `true/false`.
- `chairperson_attendee_id`: Người chủ trì, kiểu lựa chọn từ danh sách đại biểu/người dự họp.
- `chairperson_info`: Thông tin chủ trì, kiểu đoạn văn bản.
- `operator_attendee_id`: Thư ký/Điều hành, kiểu lựa chọn từ danh sách đại biểu/người dự họp.
- `operator_info`: Thông tin thư ký/điều hành, kiểu đoạn văn bản.
- `content`: Nội dung cuộc họp, kiểu đoạn văn bản.
- `meeting_location_id`: Địa điểm, kiểu lựa chọn.
- `start_time`: Thời gian bắt đầu, gồm giờ phút ngày tháng năm.
- `end_time`: Thời gian kết thúc, gồm giờ phút ngày tháng năm.

Gợi ý đơn giản hóa giao diện:

- Nhóm các trường này vào một màn hình "Thông tin cuộc họp".
- `Công khai` chỉ là nút bật/tắt.
- `Người chủ trì` và `Thư ký/Điều hành` chọn từ danh sách đại biểu đã có.
- `Thông tin chủ trì` và `Thông tin thư ký/điều hành` cho phép nhập text để hiển thị theo đúng ngữ cảnh cuộc họp.

### 2.2 Danh sách chương trình cuộc họp

Mỗi dòng chương trình gồm:

- `start_time`: Giờ bắt đầu, kiểu giờ phút.
- `end_time`: Giờ kết thúc, kiểu giờ phút.
- `content`: Nội dung, kiểu đoạn văn bản.
- `person_in_charge`: Người phụ trách, kiểu đoạn văn bản.
- `allow_discussion_registration`: Cho đăng ký thảo luận, kiểu `true/false`.
- `allow_question_registration`: Cho đăng ký chất vấn, kiểu `true/false`.
- `parent_id`: Chương trình cha nếu là mục con, kiểu lựa chọn.
- `sort_order`: Thứ tự hiển thị trong cùng cấp.

Gợi ý đơn giản hóa:

- Hiển thị dạng bảng có nút "Thêm chương trình".
- Không bắt buộc chọn người phụ trách từ tài khoản; nhập text là đủ dễ dùng.
- Chỉ khi chương trình bật `allow_discussion_registration` thì đại biểu mới thấy nút đăng ký thảo luận.
- Chỉ khi chương trình bật `allow_question_registration` thì đại biểu mới thấy nút đăng ký chất vấn.
- Cho sắp xếp thứ tự bằng kéo thả trong cùng cấp và có thể đẩy vào mục con.
- Nên giới hạn tối đa 2-3 cấp phân cấp để dễ theo dõi trên màn chiếu và thiết bị cá nhân.

### 2.3 Danh sách tài liệu đính kèm

Mỗi tài liệu gồm:

- `title`: Tên văn bản, kiểu đoạn văn bản.
- `document_type_id`: Loại văn bản, kiểu lựa chọn.
- `document_number`: Số ký hiệu, kiểu đoạn văn bản.
- `summary`: Trích yếu, kiểu đoạn văn bản.
- `file`: Tập tin đính kèm.
- `is_public`: Công khai, kiểu `true/false`.

Nguyên tắc công khai:

- Cuộc họp phải `is_public = true` thì tài liệu mới có thể xuất hiện ngoài trang công khai.
- Tài liệu phải `is_public = true` thì người dân mới xem được.
- Tài liệu không công khai chỉ hiển thị với đại biểu được mời sau khi đăng nhập.

### 2.4 Danh sách đại biểu tham dự

Có 2 cách thêm đại biểu:

- `Chọn đại biểu`: chọn từ danh sách đại biểu riêng của module.
- `Chọn nhóm đại biểu`: chọn từ danh sách nhóm đại biểu.

Khi thêm đại biểu vào cuộc họp cần lưu snapshot:

- Họ tên đại biểu.
- Chức vụ.
- Đơn vị.
- Email/số điện thoại nếu có.

Mục đích là để danh sách điểm danh, giấy mời và báo cáo không bị thay đổi khi hồ sơ đại biểu được cập nhật sau này.

### 2.5 Danh sách chương trình biểu quyết

Mỗi chương trình biểu quyết gồm:

- `title`: Tên chương trình biểu quyết.
- `vote_type`: Loại biểu quyết.
- `ballot_mode`: Chế độ biểu quyết: `anonymous` (ẩn danh) hoặc `public_named` (công khai danh tính).
- `show_result_on_projector`: Có hiển thị kết quả tổng hợp trên màn chiếu hay không.
- `show_result_on_personal_device`: Có hiển thị kết quả tổng hợp trên thiết bị cá nhân hay không.
- `sort_order`: Thứ tự hiển thị chương trình biểu quyết.

Loại biểu quyết gồm 2 nhóm:

1. `agree_disagree_abstain`
   - Đồng ý.
   - Không đồng ý.
   - Không ý kiến.

2. `approve_reject_abstain`
   - Tán thành.
   - Không tán thành.
   - Không ý kiến.

Nguyên tắc:

- Biểu quyết chỉ thực hiện khi thư ký/điều hành kích hoạt.
- Đại biểu chỉ được biểu quyết nếu thuộc danh sách tham dự cuộc họp.
- Mỗi đại biểu chỉ có một phiếu trên một chương trình biểu quyết.
- Sau khi đóng biểu quyết thì không cho sửa phiếu nếu không có quyền đặc biệt.
- Với `anonymous`: không hiển thị danh tính người bỏ phiếu trong mọi màn hình nghiệp vụ thông thường.
- Với `public_named`: chỉ vai trò quản lý và chủ trì được xem chi tiết theo từng đại biểu; các vai trò khác chỉ xem số liệu tổng hợp nếu được bật hiển thị.

### 2.6 Tùy chọn gửi thông báo và giấy mời

Các tùy chọn cần có:

- Gửi giấy mời ngay sau khi ban hành.
- Lên lịch gửi giấy mời.
- Nhắc lịch họp theo lịch.
- Chủ động nhắc lịch do người quản lý bấm.

Để đơn giản lúc triển khai đầu tiên, có thể ưu tiên:

- Nút `Ban hành và gửi giấy mời ngay`.
- Nút `Nhắc lịch`.
- Lịch gửi/nhiều mốc nhắc để triển khai sau nếu cần.

---

## 3. Luồng hoạt động

### 3.1 Tạo và ban hành cuộc họp

1. Người được phân quyền vào trang quản trị.
2. Tạo cuộc họp với thông tin chung.
3. Thêm chương trình cuộc họp.
4. Thêm tài liệu.
5. Chọn đại biểu hoặc nhóm đại biểu.
6. Thêm chương trình biểu quyết nếu có.
7. Chọn gửi giấy mời ngay hoặc lên lịch gửi.
8. Ban hành cuộc họp.

### 3.2 Người dân xem cuộc họp công khai

1. Người dân vào trang chủ.
2. Hệ thống hiển thị danh sách cuộc họp có `is_public = true`.
3. Người dân vào chi tiết cuộc họp.
4. Người dân xem được thông tin cơ bản, tab `Chương trình`, tab `Tài liệu`.
5. Người dân chỉ xem được tài liệu có `is_public = true`.

### 3.3 Đại biểu xem cuộc họp sau đăng nhập

1. Đại biểu đăng nhập.
2. Hệ thống hiển thị các cuộc họp đại biểu được mời.
3. Đại biểu xem được cuộc họp công khai và cuộc họp không công khai nếu có trong danh sách tham dự.
4. Đại biểu xác nhận tham gia hoặc báo vắng có lý do.
5. Đại biểu xem tài liệu nội bộ của cuộc họp.
6. Đại biểu ghi chú cá nhân.
7. Đại biểu điểm danh khi đến giờ.
8. Đại biểu đăng ký thảo luận/chất vấn nếu chương trình cho phép.
9. Đại biểu biểu quyết khi chương trình biểu quyết được kích hoạt.

### 3.4 Thư ký/Điều hành vận hành cuộc họp

1. Xem danh sách đại biểu và trạng thái xác nhận tham gia.
2. Xác nhận điểm danh hoặc điểm danh thay cho đại biểu.
3. Bật/hiển thị/in QR code điểm danh.
4. Quản lý danh sách đăng ký thảo luận.
5. Quản lý danh sách đăng ký chất vấn.
6. Mở/đóng biểu quyết.
7. Quản lý chương trình họp.
8. Sử dụng tab `Màn chiếu` khi cần trình chiếu thông tin.
9. Cập nhật văn bản/nội dung kết luận.

### 3.5 Luồng hoạt động chi tiết giữa Backend và Frontend

Mục này mô tả luồng kỹ thuật để đội Backend (BE) và Frontend (FE) triển khai đồng bộ, giảm lệch nghiệp vụ khi chạy thật.

#### Giai đoạn A - Trước họp (chuẩn bị và ban hành)

1. FE quản trị gọi API danh mục (`meeting_types`, `meeting_locations`, `meeting_document_types`, `meeting_attendees`, `meeting_attendee_groups`) để tải dữ liệu form.
2. BE trả dữ liệu đã scope theo `organization_id`, lọc `status = active`, sắp xếp theo `sort_order` hoặc `name`.
3. FE nhập dữ liệu cuộc họp và gửi `store`:
   - Thông tin chung cuộc họp.
   - Danh sách chương trình.
   - Danh sách tài liệu.
   - Danh sách đại biểu tham dự.
   - Danh sách chương trình biểu quyết.
4. BE validate bằng FormRequest, xử lý nghiệp vụ trong Service:
   - Tạo `meetings`.
   - Lưu `meeting_agendas`, `meeting_documents`, `meeting_participants`, `meeting_vote_topics`.
   - Với tài liệu/file đính kèm: upload qua `MediaService`.
   - Bọc transaction cho luồng ghi nhiều bước.
5. FE hiển thị bản xem trước và trạng thái `draft`, cho phép chỉnh sửa trước khi ban hành.
6. Khi người dùng bấm ban hành, FE gọi action `changeStatus` hoặc action publish chuyên biệt.
7. BE chuyển trạng thái `draft -> published`, set `published_at`, tạo bản ghi `meeting_invitations` và kích hoạt gửi giấy mời theo lựa chọn (`now` hoặc `scheduled`).
8. FE hiển thị kết quả ban hành, số lượng giấy mời thành công/thất bại để điều hành xử lý tiếp.

#### Giai đoạn B - Cận giờ họp (xác nhận tham gia và điểm danh)

1. Đại biểu mở link giấy mời hoặc đăng nhập vào danh sách cuộc họp của tôi.
2. FE gọi `show` cuộc họp và API participant profile để xác định quyền/role (`delegate`, `chairperson`, `operator`).
3. BE kiểm tra tenant + membership:
   - Nếu không thuộc `meeting_participants` thì từ chối truy cập dữ liệu nội bộ.
   - Nếu hợp lệ thì trả dữ liệu theo role và theo cờ công khai.
4. Đại biểu bấm xác nhận tham gia/vắng mặt, FE gửi action cập nhật `response_status`.
5. BE lưu `response_status`, `absence_reason`, `responded_at` và trả dữ liệu mới để FE cập nhật realtime nhẹ (polling hoặc refresh theo chu kỳ).
6. Đến khung điểm danh, FE hiển thị nút điểm danh/quét mã theo cấu hình cuộc họp.
7. FE gửi check-in với `checkin_method = qr | button | manual`.
8. BE đối soát tài khoản đăng nhập với `meeting_participant_id`, chặn check-in trùng, cập nhật `meeting_attendances` và đồng bộ trạng thái có mặt.

#### Giai đoạn C - Trong họp (điều hành, thảo luận/chất vấn, biểu quyết)

1. FE điều hành tải dashboard phiên họp:
   - Tổng số mời, đã xác nhận, đã điểm danh.
   - Danh sách đăng ký thảo luận/chất vấn.
   - Danh sách chương trình biểu quyết.
2. BE tổng hợp số liệu từ các bảng `meeting_participants`, `meeting_attendances`, `meeting_discussion_registrations`, `meeting_vote_topics`.
3. Khi đại biểu đăng ký thảo luận/chất vấn, FE gửi `type`, `content`, `meeting_agenda_id`, file đính kèm (nếu có).
4. BE kiểm tra cờ cho phép theo agenda (`allow_discussion_registration` hoặc `allow_question_registration`), sau đó lưu đăng ký và `sort_order`.
5. Điều hành/chủ trì đổi trạng thái lượt phát biểu (`registered -> called -> completed`), FE gọi action tương ứng.
6. BE cập nhật trạng thái, thời điểm (`called_at`, `completed_at`) và trả danh sách mới để FE sắp xếp lại thứ tự hiển thị.
7. Khi mở biểu quyết, FE điều hành gọi action `open` cho `meeting_vote_topics`.
8. BE đổi trạng thái `opened`, ghi `opened_at`, chỉ cho đại biểu đủ điều kiện gửi phiếu.
9. FE đại biểu hiển thị modal biểu quyết; mỗi đại biểu gửi 1 phiếu.
10. BE ghi vào `meeting_vote_responses`, áp unique (`meeting_vote_topic_id + meeting_participant_id`) để chống bỏ phiếu trùng.
11. Khi đóng biểu quyết, FE gọi action `close`.
12. BE chốt `closed_at`, tổng hợp kết quả theo `vote_type` và trả:
    - Dữ liệu tổng hợp cho màn chiếu/thiết bị cá nhân theo cờ hiển thị.
    - Dữ liệu chi tiết theo người chỉ khi đúng quyền và đúng `ballot_mode`.

#### Giai đoạn D - Sau họp (kết luận và tra cứu)

1. FE điều hành nhập nội dung kết luận, đính kèm văn bản kết luận nếu có.
2. BE lưu vào `meeting_conclusions` (qua `MediaService` khi có file), cho phép lưu nháp hoặc công bố.
3. FE công khai kết luận theo trạng thái và quyền truy cập.
4. BE ghi log lượt xem cuộc họp/tài liệu vào `meeting_views`, đồng thời tăng `view_count`/`download_count`.
5. FE hiển thị lịch sử và số liệu tổng quan để phục vụ báo cáo.

#### Chuẩn phối hợp BE/FE cần thống nhất từ đầu

- FE luôn gửi `X-Organization-Id` cho endpoint tenant; BE bắt buộc scope theo tổ chức hiện tại.
- FE không tự suy diễn quyền; luôn đọc quyền từ payload API và ẩn/hiện nút thao tác theo quyền thực tế.
- BE trả response theo chuẩn `success`, `successResource`, `successCollection`, `error` để FE xử lý nhất quán.
- Các action nhạy cảm (ban hành, mở/đóng biểu quyết, điểm danh thay, công bố kết luận) phải có xác nhận 2 bước ở FE và log nghiệp vụ ở BE.
- Luồng reorder (chương trình, tài liệu, đăng ký, ghi chú) phải có cơ chế chống xung đột đồng thời như đã nêu tại phần dữ liệu.

---

## 4. Phân quyền chính

### 4.1 Người dân

Không cần đăng nhập.

Có quyền:

- Xem trang chủ danh sách cuộc họp công khai.
- Xem chi tiết cuộc họp công khai.
- Xem tab `Chương trình`.
- Xem tab `Tài liệu`.
- Xem thông tin cơ bản cuộc họp.
- Xem tài liệu được công khai.

Không có quyền:

- Xem tài liệu nội bộ.
- Xem danh sách đại biểu nội bộ.
- Điểm danh.
- Biểu quyết.
- Ghi chú.
- Đăng ký thảo luận/chất vấn.

### 4.2 Đại biểu

Có quyền:

- Giống người dân.
- Xem các cuộc họp mình tham gia, kể cả không công khai.
- Xem tài liệu nội bộ của cuộc họp mình tham gia.
- Cập nhật ghi chú cá nhân.
- Xác nhận tham gia.
- Báo vắng mặt có lý do trước cuộc họp.
- Điều chỉnh xác nhận trước khi cuộc họp diễn ra.
- Điểm danh bằng QR code hoặc nút điểm danh trong phần mềm.
- Đăng ký thảo luận nếu chương trình cho phép.
- Đăng ký chất vấn nếu chương trình cho phép.
- Biểu quyết khi chương trình biểu quyết được kích hoạt.

Giao diện đại biểu có thêm:

- Mục ghi chú cá nhân trong tab `Tài liệu`.
- Tab `Biểu quyết`.
- Tab `Thảo luận & Chất vấn` nếu cuộc họp có bật chức năng này.

### 4.3 Chủ trì

Có quyền:

- Giống đại biểu.
- Xem tab `Chủ trì`.

Tab `Chủ trì` hiển thị nhanh:

- Số lượng đại biểu đã xác nhận tham gia.
- Số lượng đại biểu báo vắng.
- Số lượng đại biểu đã điểm danh.
- Danh sách đăng ký phát biểu.
- Danh sách đăng ký chất vấn.
- Tỷ lệ biểu quyết từng chương trình.

### 4.4 Thư ký/Điều hành

Có quyền:

- Giống chủ trì.
- Xem tab `Điều hành`.
- Xem tab `Màn chiếu`.
- Xem tab `Kết luận`.

Tab `Điều hành` dùng để:

- Quản lý danh sách xác nhận tham gia.
- Quản lý và xác nhận điểm danh.
- Điểm danh thay cho đại biểu.
- Quản lý danh sách đăng ký thảo luận.
- Quản lý danh sách đăng ký chất vấn.
- Quản lý biểu quyết.
- Quản lý chương trình họp.

Tab `Kết luận` dùng để:

- Cập nhật nội dung kết luận.
- Đính kèm văn bản kết luận nếu có.

### 4.5 Quản lý

Có quyền:

- Tạo cuộc họp.
- Cập nhật cuộc họp.
- Ban hành cuộc họp.
- Quản lý các danh mục phục vụ cuộc họp.
- Quản lý đại biểu, nhóm đại biểu.
- Quản lý tài liệu.
- Quản lý địa điểm.

---

## 5. Hai trang chính của hệ thống

### 5.1 Trang người dùng/trang công khai

Trang này phục vụ người dân và đại biểu.

Người dân:

- Xem cuộc họp công khai.
- Xem chương trình công khai.
- Xem tài liệu công khai.

Đại biểu sau đăng nhập:

- Xem cuộc họp được mời.
- Xem tài liệu nội bộ.
- Ghi chú.
- Điểm danh.
- Biểu quyết.
- Đăng ký thảo luận/chất vấn.

### 5.2 Trang quản trị

Trang này phục vụ quản lý, thư ký/điều hành và quản trị hệ thống.

Chức năng:

- Quản trị tổ chức.
- Quản trị tài khoản.
- Quản lý danh mục.
- Quản lý cuộc họp.
- Quản lý tài liệu.
- Quản lý đại biểu.
- Quản lý điểm danh.
- Quản lý biểu quyết.
- Quản lý kết luận.

---

## 6. Bố trí tab ở trang chi tiết cuộc họp

### 6.1 Tab cho người dân

- `Chương trình`.
- `Tài liệu`.

### 6.2 Tab cho đại biểu

- `Chương trình`.
- `Tài liệu`.
- `Thảo luận & Chất vấn`.
- `Biểu quyết`.

Trong tab `Tài liệu`, đại biểu có thêm phần ghi chú cá nhân.

### 6.3 Tab cho chủ trì

- `Chương trình`.
- `Tài liệu`.
- `Thảo luận & Chất vấn`.
- `Biểu quyết`.
- `Chủ trì`.

### 6.4 Tab cho thư ký/điều hành

- `Chương trình`.
- `Tài liệu`.
- `Thảo luận & Chất vấn`.
- `Biểu quyết`.
- `Chủ trì`.
- `Điều hành`.
- `Màn chiếu`.
- `Kết luận`.

---

## 7. Một số chức năng cần lưu ý

### 7.1 Điểm danh

- Đại biểu chỉ được điểm danh khi đến giờ hoặc trong khoảng thời gian cho phép.
- Điểm danh bằng quét QR code.
- Điểm danh bằng nút điểm danh trong phần mềm.
- Thư ký/điều hành có thể điểm danh thay.
- QR code điểm danh có thể bật hiển thị hoặc in ra.

### 7.2 Xác nhận tham gia

- Đại biểu có thể xác nhận tham gia theo link giấy mời/thông báo.
- Đại biểu có thể báo vắng mặt trước cuộc họp.
- Nếu vắng mặt thì phải nhập lý do.
- Đại biểu có thể điều chỉnh xác nhận trước khi cuộc họp diễn ra.

### 7.3 Thảo luận và chất vấn

- Nếu đăng ký thảo luận thì đại biểu phải nhập nội dung thảo luận.
- Nếu đăng ký chất vấn thì đại biểu phải nhập nội dung chất vấn.
- Người đăng ký được đưa vào danh sách chờ.
- Chủ trì có thể gọi đại biểu thảo luận/chất vấn.
- Người điều hành đánh dấu `Đã thảo luận` hoặc `Đã chất vấn`.
- Đại biểu có thể đính kèm file khi đăng ký để chủ trì/điều hành mở trình chiếu ngay trong phiên họp.
- File đính kèm đăng ký thảo luận/chất vấn nên đi qua `MediaService` và giới hạn định dạng theo cấu hình.

### 7.4 Biểu quyết

- Biểu quyết chỉ được thực hiện khi được kích hoạt theo chương trình.
- Đại biểu chỉ được biểu quyết nếu thuộc danh sách tham dự.
- Hệ thống lưu kết quả theo từng đại biểu và tổng hợp tỷ lệ.
- Có 2 chế độ biểu quyết: `ẩn danh` và `công khai danh tính`.
- Kết quả tổng hợp có thể bật/tắt hiển thị độc lập cho `màn chiếu` và `thiết bị cá nhân`.
- Kết quả chi tiết theo từng người chỉ cho `quản lý` và `chủ trì` trong chế độ công khai danh tính.

### 7.5 Lượt xem

- Hệ thống lưu lượt xem mỗi cuộc họp.
- Hệ thống lưu lượt xem mỗi tài liệu.

### 7.6 Địa điểm

- Quản lý địa điểm là danh mục riêng.
- Địa điểm có thể kèm tọa độ.
- Có thể mở chỉ đường sang Google Maps.

### 7.7 Tự động phát âm thanh thông qua AI

Đây là chức năng nâng cao nhưng vẫn nằm trong định hướng giải pháp.

Có thể dùng để:

- Phát thông báo bắt đầu cuộc họp.
- Đọc nội dung chương trình.
- Gọi đại biểu thảo luận/chất vấn.
- Thông báo mở/đóng biểu quyết.

Nên triển khai sau khi các chức năng chính đã ổn định.

### 7.8 Ghi chú cá nhân

- Bảng ghi chú cá nhân không cần liên kết tài liệu.
- Chỉ cho phép ghi chú đoạn văn bản.
- Mỗi đại biểu có thể tạo nhiều ghi chú trong cùng một cuộc họp.
- Mỗi ghi chú cho phép đính kèm nhiều tập tin.
- Ghi chú có trường thứ tự để sắp xếp và kéo thả theo nhu cầu cá nhân của đại biểu.
- Ghi chú mặc định là dữ liệu cá nhân, chỉ người tạo xem được.

### 7.9 Chương trình họp phân cấp

- Chương trình cuộc họp cần hỗ trợ phân cấp (mục cha/mục con) và sắp xếp thứ tự.
- Khi hiển thị ở màn chiếu và thiết bị cá nhân phải thể hiện rõ cấp độ bằng thụt lề/đánh số.
- Nên hỗ trợ thao tác kéo thả để đổi thứ tự và thay đổi cấp.

### 7.10 Điều khiển hiển thị kết quả biểu quyết

- Thư ký/điều hành có thể bật/tắt hiển thị kết quả trên màn chiếu theo từng chương trình biểu quyết.
- Thư ký/điều hành có thể bật/tắt hiển thị kết quả trên thiết bị cá nhân độc lập với màn chiếu.
- Trường hợp cần giữ bí mật trong lúc biểu quyết, hệ thống cho mở biểu quyết nhưng chưa hiển thị kết quả đến khi chủ trì cho phép.

### 7.11 Xác nhận thao tác quan trọng ở frontend

- Với hành động quan trọng, frontend bắt buộc hiển thị hộp xác nhận trước khi gửi API.
- Danh sách tối thiểu gồm: ban hành cuộc họp, mở/đóng biểu quyết, xóa dữ liệu, điểm danh thay, gọi đại biểu thảo luận, công bố kết luận.
- Cần thông điệp xác nhận rõ ràng, có nêu hệ quả để tránh thao tác nhầm trong lúc điều hành trực tiếp.

### 7.12 Điểm danh quét mã theo tài khoản đăng nhập

- Luồng điểm danh quét mã chỉ cần đại biểu mở link và đăng nhập tài khoản.
- Sau đăng nhập, frontend lấy token phiên hiện tại để gửi yêu cầu điểm danh; backend đối soát tài khoản với đại biểu của cuộc họp rồi cập nhật `có mặt`.
- Không yêu cầu đại biểu nhập thêm thông tin tay nếu đã xác thực hợp lệ.
- Vẫn lưu `checkin_method = qr` để phục vụ báo cáo.

### 7.13 Quy tắc sắp xếp mặc định

- Chương trình họp (`meeting_agendas`): sắp theo `sort_order asc`, nếu trùng thì `start_time asc`, sau cùng `id asc`.
- Danh sách tài liệu (`meeting_documents`): sắp theo `sort_order asc`, nếu trùng thì `created_at desc`, sau cùng `id desc`.
- Chương trình biểu quyết (`meeting_vote_topics`): sắp theo `sort_order asc`, nếu trùng thì `created_at asc`, sau cùng `id asc`.
- Ghi chú cá nhân (`meeting_personal_notes`): sắp theo `sort_order asc`, nếu trùng thì `updated_at desc`, sau cùng `id desc`.
- Tập tin đính kèm ghi chú (`meeting_personal_note_attachments`): sắp theo `sort_order asc`, nếu trùng thì `created_at asc`, sau cùng `id asc`.
- Khuyến nghị frontend dùng cùng thứ tự này để tránh lệch giữa màn hình người dùng và dữ liệu từ API.

### 7.14 Quyền truy cập file ghi chú

- File đính kèm ghi chú là dữ liệu cá nhân theo đại biểu, không dùng chung giữa các đại biểu.
- `Delegate` chỉ được xem/tải/sửa/xóa file đính kèm thuộc chính ghi chú do mình tạo.
- `Chủ trì`, `thư ký/điều hành`, `quản lý` không tự động thấy file ghi chú cá nhân, trừ khi có cơ chế chia sẻ hoặc quyền đặc biệt được định nghĩa riêng.
- Mọi truy vấn file ghi chú phải đi qua kiểm tra quyền trên `meeting_personal_notes.meeting_participant_id`.

### 7.15 Ma trận quyền action cho file ghi chú

Các action chuẩn cần thống nhất giữa backend và frontend:

- `view_note_attachment`: xem danh sách file trong ghi chú.
- `download_note_attachment`: tải file đính kèm.
- `create_note_attachment`: thêm file đính kèm.
- `delete_note_attachment`: xóa file đính kèm.
- `reorder_note_attachment`: sắp xếp lại thứ tự file.

Nguyên tắc theo vai trò:

- `Delegate`: chỉ được thao tác các action trên ghi chú cá nhân của chính mình.
- `Chairperson`, `Operator`, `Manager`: mặc định không có quyền truy cập file ghi chú cá nhân của đại biểu khác.
- Nếu cần nghiệp vụ đặc biệt, phải bật quyền riêng theo chính sách tổ chức và có log kiểm tra.

---

## 8. Mô hình dữ liệu đơn giản đề xuất

Thiết kế bảng dưới đây giữ theo hướng đơn giản, đủ phục vụ triển khai nhanh. Các bảng nghiệp vụ chính cần có `organization_id` để scope theo đơn vị/phường/xã hiện tại.

### 8.1 Bảng `meeting_types`

Bảng danh mục loại cuộc họp.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `organization_id` | bigint nullable | Tổ chức sở hữu, nullable nếu dùng chung toàn hệ thống |
| `name` | string | Tên loại cuộc họp |
| `description` | text nullable | Mô tả |
| `status` | string | Trạng thái: `active`, `inactive` |
| `sort_order` | integer | Thứ tự hiển thị |
| `created_by` | bigint nullable | Người tạo |
| `updated_by` | bigint nullable | Người cập nhật |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.2 Bảng `meeting_locations`

Bảng danh mục địa điểm họp.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `organization_id` | bigint | Tổ chức sở hữu |
| `name` | string | Tên địa điểm/phòng họp |
| `address` | string nullable | Địa chỉ |
| `latitude` | decimal nullable | Vĩ độ |
| `longitude` | decimal nullable | Kinh độ |
| `google_maps_url` | string nullable | Link chỉ đường Google Maps |
| `description` | text nullable | Mô tả |
| `status` | string | Trạng thái: `active`, `inactive` |
| `sort_order` | integer | Thứ tự hiển thị |
| `created_by` | bigint nullable | Người tạo |
| `updated_by` | bigint nullable | Người cập nhật |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.3 Bảng `meeting_document_types`

Bảng danh mục loại văn bản/tài liệu.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `organization_id` | bigint nullable | Tổ chức sở hữu, nullable nếu dùng chung |
| `name` | string | Tên loại văn bản |
| `description` | text nullable | Mô tả |
| `status` | string | Trạng thái: `active`, `inactive` |
| `sort_order` | integer | Thứ tự hiển thị |
| `created_by` | bigint nullable | Người tạo |
| `updated_by` | bigint nullable | Người cập nhật |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.4 Bảng `meeting_attendee_groups`

Bảng nhóm đại biểu/người dự họp.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `organization_id` | bigint | Tổ chức sở hữu |
| `name` | string | Tên nhóm đại biểu |
| `description` | text nullable | Mô tả |
| `status` | string | Trạng thái: `active`, `inactive` |
| `sort_order` | integer | Thứ tự hiển thị |
| `created_by` | bigint nullable | Người tạo |
| `updated_by` | bigint nullable | Người cập nhật |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.5 Bảng `meeting_attendees`

Bảng danh sách đại biểu/người dự họp riêng của module.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `organization_id` | bigint | Tổ chức sở hữu |
| `meeting_attendee_group_id` | bigint nullable | Nhóm đại biểu mặc định |
| `user_id` | bigint nullable | Tài khoản hệ thống lõi nếu có |
| `name` | string | Họ tên đại biểu |
| `position_name` | string nullable | Chức vụ |
| `department_name` | string nullable | Đơn vị |
| `email` | string nullable | Email |
| `phone` | string nullable | Số điện thoại |
| `status` | string | Trạng thái: `active`, `inactive` |
| `note` | text nullable | Ghi chú |
| `created_by` | bigint nullable | Người tạo |
| `updated_by` | bigint nullable | Người cập nhật |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

Ràng buộc khuyến nghị:

- Nếu `user_id` có giá trị, nên unique theo `organization_id + user_id`.

### 8.6 Bảng `meetings`

Bảng thông tin chung cuộc họp.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `organization_id` | bigint | Tổ chức sở hữu |
| `meeting_type_id` | bigint | Loại cuộc họp |
| `meeting_location_id` | bigint nullable | Địa điểm họp |
| `title` | string | Tên cuộc họp |
| `is_public` | boolean | Công khai ngoài trang chủ |
| `chairperson_attendee_id` | bigint nullable | Người chủ trì |
| `chairperson_info` | text nullable | Thông tin chủ trì hiển thị trong cuộc họp |
| `operator_attendee_id` | bigint nullable | Thư ký/Điều hành |
| `operator_info` | text nullable | Thông tin thư ký/điều hành |
| `content` | longText nullable | Nội dung cuộc họp |
| `location_snapshot` | string nullable | Tên/địa chỉ địa điểm tại thời điểm tạo |
| `start_time` | datetime | Thời gian bắt đầu |
| `end_time` | datetime nullable | Thời gian kết thúc |
| `status` | string | Trạng thái: `draft`, `published`, `in_progress`, `completed`, `cancelled` |
| `qr_checkin_enabled` | boolean | Có bật QR điểm danh hay không |
| `checkin_starts_at` | datetime nullable | Thời điểm bắt đầu cho điểm danh |
| `checkin_ends_at` | datetime nullable | Thời điểm kết thúc cho điểm danh |
| `view_count` | unsigned integer | Lượt xem cuộc họp |
| `published_at` | datetime nullable | Thời điểm ban hành |
| `created_by` | bigint nullable | Người tạo |
| `updated_by` | bigint nullable | Người cập nhật |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.7 Bảng `meeting_agendas`

Bảng chương trình cuộc họp.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `start_time` | time nullable | Giờ bắt đầu |
| `end_time` | time nullable | Giờ kết thúc |
| `content` | text | Nội dung chương trình |
| `person_in_charge` | string nullable | Người phụ trách nhập dạng text |
| `allow_discussion_registration` | boolean | Cho đăng ký thảo luận |
| `allow_question_registration` | boolean | Cho đăng ký chất vấn |
| `parent_id` | bigint nullable | Chương trình cha nếu là mục con |
| `sort_order` | integer | Thứ tự hiển thị |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.8 Bảng `meeting_documents`

Bảng tài liệu đính kèm cuộc họp.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `meeting_agenda_id` | bigint nullable | Chương trình liên quan nếu có |
| `meeting_document_type_id` | bigint nullable | Loại văn bản |
| `title` | string | Tên văn bản |
| `document_number` | string nullable | Số ký hiệu |
| `summary` | text nullable | Trích yếu |
| `media_id` | bigint nullable | Tập tin đính kèm qua `MediaService` |
| `is_public` | boolean | Công khai tài liệu |
| `status` | string | Trạng thái: `draft`, `published` |
| `view_count` | unsigned integer | Lượt xem tài liệu |
| `download_count` | unsigned integer | Lượt tải tài liệu |
| `sort_order` | integer | Thứ tự hiển thị |
| `created_by` | bigint nullable | Người tạo |
| `updated_by` | bigint nullable | Người cập nhật |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

Nguyên tắc công khai:

- Người dân chỉ xem được tài liệu khi `meetings.is_public = true` và `meeting_documents.is_public = true`.

### 8.9 Bảng `meeting_participants`

Bảng danh sách đại biểu tham dự trong từng cuộc họp.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `meeting_attendee_id` | bigint | Đại biểu từ danh bạ module |
| `role` | string | Vai trò: `delegate`, `chairperson`, `operator`, `guest` |
| `display_name` | string | Họ tên snapshot |
| `position_name` | string nullable | Chức vụ snapshot |
| `department_name` | string nullable | Đơn vị snapshot |
| `email` | string nullable | Email snapshot |
| `phone` | string nullable | Số điện thoại snapshot |
| `response_status` | string | Trạng thái xác nhận: `pending`, `accepted`, `declined` |
| `absence_reason` | text nullable | Lý do vắng mặt |
| `responded_at` | datetime nullable | Thời điểm xác nhận |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

Ràng buộc khuyến nghị:

- Unique theo `meeting_id + meeting_attendee_id`.

### 8.10 Bảng `meeting_attendances`

Bảng điểm danh đại biểu.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `meeting_participant_id` | bigint | Đại biểu tham dự |
| `status` | string | Trạng thái: `pending`, `present`, `absent`, `late`, `excused` |
| `checkin_method` | string nullable | Cách điểm danh: `qr`, `button`, `manual` |
| `checked_in_at` | datetime nullable | Thời điểm điểm danh |
| `checked_in_by` | bigint nullable | Người điểm danh thay nếu có |
| `note` | text nullable | Ghi chú |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.11 Bảng `meeting_vote_topics`

Bảng chương trình biểu quyết.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `meeting_agenda_id` | bigint nullable | Chương trình liên quan nếu có |
| `title` | string | Tên chương trình biểu quyết |
| `vote_type` | string | Loại biểu quyết: `agree_disagree_abstain`, `approve_reject_abstain` |
| `ballot_mode` | string | Chế độ: `anonymous`, `public_named` |
| `show_result_on_projector` | boolean | Có hiển thị kết quả tổng hợp trên màn chiếu |
| `show_result_on_personal_device` | boolean | Có hiển thị kết quả tổng hợp trên thiết bị cá nhân |
| `sort_order` | integer | Thứ tự hiển thị chương trình biểu quyết |
| `status` | string | Trạng thái: `draft`, `opened`, `closed` |
| `opened_at` | datetime nullable | Thời điểm mở biểu quyết |
| `closed_at` | datetime nullable | Thời điểm đóng biểu quyết |
| `created_by` | bigint nullable | Người tạo |
| `updated_by` | bigint nullable | Người cập nhật |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.12 Bảng `meeting_vote_responses`

Bảng phiếu biểu quyết của đại biểu.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_vote_topic_id` | bigint | Chương trình biểu quyết |
| `meeting_participant_id` | bigint | Đại biểu biểu quyết |
| `option` | string | Lựa chọn: `agree`, `disagree`, `approve`, `reject`, `abstain` |
| `is_visible_to_managers` | boolean | Cờ nội bộ cho phép quản lý/chủ trì xem chi tiết theo người khi cần |
| `voted_at` | datetime | Thời điểm biểu quyết |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

Ràng buộc khuyến nghị:

- Unique theo `meeting_vote_topic_id + meeting_participant_id`.

### 8.13 Bảng `meeting_discussion_registrations`

Bảng đăng ký thảo luận/chất vấn.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `meeting_agenda_id` | bigint nullable | Chương trình liên quan |
| `meeting_participant_id` | bigint | Đại biểu đăng ký |
| `type` | string | Loại đăng ký: `discussion`, `question` |
| `content` | text | Nội dung thảo luận/chất vấn |
| `media_id` | bigint nullable | File đính kèm nội dung thảo luận/chất vấn qua `MediaService` |
| `status` | string | Trạng thái: `registered`, `called`, `completed`, `cancelled` |
| `called_at` | datetime nullable | Thời điểm được chủ trì gọi |
| `completed_at` | datetime nullable | Thời điểm đã thảo luận/chất vấn xong |
| `sort_order` | integer | Thứ tự gọi |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

Nguyên tắc:

- Nếu `type = discussion`, chỉ cho đăng ký khi chương trình bật `allow_discussion_registration`.
- Nếu `type = question`, chỉ cho đăng ký khi chương trình bật `allow_question_registration`.

### 8.14 Bảng `meeting_personal_notes`

Bảng ghi chú cá nhân của đại biểu.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `meeting_participant_id` | bigint | Đại biểu ghi chú |
| `content` | longText | Nội dung ghi chú |
| `sort_order` | integer | Thứ tự hiển thị ghi chú theo từng đại biểu trong cuộc họp |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

Nguyên tắc:

- Ghi chú mặc định chỉ người tạo xem được.
- Không bắt buộc liên kết ghi chú với tài liệu.
- Mỗi đại biểu có thể có nhiều ghi chú trong cùng một cuộc họp.
- Cho phép sắp xếp thủ công theo `sort_order`.

### 8.15 Bảng `meeting_personal_note_attachments`

Bảng tập tin đính kèm cho ghi chú cá nhân.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_personal_note_id` | bigint | Ghi chú cá nhân |
| `media_id` | bigint | Tập tin đính kèm qua `MediaService` |
| `sort_order` | integer | Thứ tự hiển thị tập tin trong ghi chú |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.16 Bảng `meeting_conclusions`

Bảng kết luận cuộc họp.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `title` | string | Tiêu đề kết luận |
| `content` | longText nullable | Nội dung kết luận |
| `media_id` | bigint nullable | Văn bản kết luận đính kèm nếu có |
| `status` | string | Trạng thái: `draft`, `published` |
| `created_by` | bigint nullable | Người tạo |
| `updated_by` | bigint nullable | Người cập nhật |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.17 Bảng `meeting_views`

Bảng lưu lượt xem cuộc họp và tài liệu.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `meeting_document_id` | bigint nullable | Tài liệu nếu là lượt xem tài liệu |
| `user_id` | bigint nullable | Người xem nếu đã đăng nhập |
| `ip_address` | string nullable | IP người xem |
| `user_agent` | text nullable | Thiết bị/trình duyệt |
| `viewed_at` | datetime | Thời điểm xem |

### 8.18 Bảng `meeting_invitations`

Bảng giấy mời theo đại biểu.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `meeting_participant_id` | bigint | Đại biểu nhận giấy mời |
| `send_type` | string | Kiểu gửi: `now`, `scheduled` |
| `scheduled_at` | datetime nullable | Thời điểm hẹn gửi |
| `sent_at` | datetime nullable | Thời điểm đã gửi |
| `status` | string | Trạng thái: `pending`, `sent`, `failed` |
| `error_message` | text nullable | Lỗi gửi nếu có |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.19 Bảng `meeting_reminders`

Bảng nhắc lịch họp.

| Trường | Kiểu | Mô tả |
| --- | --- | --- |
| `id` | bigint | Khóa chính |
| `meeting_id` | bigint | Cuộc họp |
| `reminder_type` | string | Kiểu nhắc: `manual`, `scheduled` |
| `scheduled_at` | datetime nullable | Thời điểm hẹn nhắc |
| `sent_at` | datetime nullable | Thời điểm đã nhắc |
| `message` | text nullable | Nội dung nhắc |
| `status` | string | Trạng thái: `pending`, `sent`, `failed` |
| `created_by` | bigint nullable | Người tạo nhắc |
| `created_at` | timestamp | Ngày tạo |
| `updated_at` | timestamp | Ngày cập nhật |

### 8.20 Quy tắc mặc định sinh `sort_order`

Khi tạo mới bản ghi, nếu frontend không truyền `sort_order` thì backend tự sinh theo nguyên tắc `max(sort_order) + 1` trong đúng phạm vi nghiệp vụ:

- `meeting_agendas`: phạm vi theo `meeting_id + parent_id` (cùng cấp chương trình).
- `meeting_documents`: phạm vi theo `meeting_id`.
- `meeting_vote_topics`: phạm vi theo `meeting_id`.
- `meeting_discussion_registrations`: phạm vi theo `meeting_id + meeting_agenda_id + type`.
- `meeting_personal_notes`: phạm vi theo `meeting_id + meeting_participant_id`.
- `meeting_personal_note_attachments`: phạm vi theo `meeting_personal_note_id`.

Nguyên tắc cập nhật:

- Khi kéo thả đổi thứ tự, backend cập nhật lại `sort_order` tuần tự (1..n) trong đúng phạm vi.
- Nên dùng transaction cho thao tác reorder nhiều dòng để tránh trùng hoặc mất thứ tự khi ghi đồng thời.

### 8.21 Xử lý đồng thời khi reorder

Để tránh xung đột khi nhiều người cùng sắp xếp:

- Reorder phải chạy trong transaction và khóa theo đúng phạm vi dữ liệu đang sắp xếp.
- Có thể dùng một trong hai cơ chế:
  - `Pessimistic lock`: khóa bản ghi trong phạm vi reorder (`SELECT ... FOR UPDATE`).
  - `Optimistic lock`: gửi `version/updated_at` từ frontend, backend từ chối nếu dữ liệu đã thay đổi.
- Khi phát hiện xung đột, trả thông báo rõ ràng để frontend yêu cầu người dùng tải lại danh sách và thực hiện lại thao tác.
- Sau khi reorder xong, luôn chuẩn hóa lại dãy `sort_order` liên tục từ `1..n`.

### 8.22 API contract tối thiểu (phase khởi động)

Mục tiêu là chốt sớm định dạng request/response để backend/frontend làm song song, giảm rework.

Nhóm endpoint cần chốt trước:

- `Meetings`: `stats`, `index`, `show`, `store`, `update`, `destroy`, `changeStatus`.
- `Meeting Agendas`: CRUD + `reorder`.
- `Meeting Documents`: CRUD + upload media + `reorder`.
- `Meeting Vote Topics`: CRUD + `reorder` + `open`/`close` + cấu hình hiển thị kết quả.
- `Meeting Vote Responses`: gửi phiếu biểu quyết + lấy thống kê tổng hợp.
- `Meeting Discussion Registrations`: đăng ký + cập nhật trạng thái + `reorder`.
- `Meeting Personal Notes`: CRUD + `reorder`.
- `Meeting Personal Note Attachments`: upload/delete/download + `reorder`.
- `Meeting Attendances`: check-in bằng `qr/button/manual`, điểm danh thay.

Chuẩn response đề xuất:

- Danh sách: trả theo `successCollection`.
- Chi tiết/tạo/sửa: trả theo `successResource`.
- Hành động nghiệp vụ (`open vote`, `close vote`, `reorder`, `check-in`): trả theo `success`.

Chuẩn request `reorder` đề xuất:

- Body dạng mảng cặp `id`, `sort_order`.
- Có thể gửi thêm trường kiểm tra đồng thời (`version` hoặc `updated_at`) theo phạm vi.

---

## 9. Lộ trình triển khai đơn giản

### Giai đoạn 1: Chức năng chính

- Danh mục loại cuộc họp.
- Danh mục địa điểm.
- Danh mục loại tài liệu.
- Danh mục nhóm đại biểu.
- Danh sách đại biểu.
- Tạo cuộc họp.
- Chương trình cuộc họp.
- Tài liệu đính kèm.
- Danh sách đại biểu tham dự.
- Trang công khai.
- Gửi giấy mời ngay.
- Xác nhận tham gia/vắng mặt.
- Điểm danh QR/nút bấm.
- Biểu quyết đơn giản.
- Ghi chú cá nhân.
- Kết luận cuộc họp.

### Giai đoạn 2: Vận hành đầy đủ hơn

- Lên lịch gửi giấy mời.
- Lên lịch nhắc họp.
- Tab `Chủ trì`.
- Tab `Điều hành`.
- Tab `Màn chiếu`.
- Điều phối thảo luận/chất vấn.
- Báo cáo điểm danh và biểu quyết.

### Giai đoạn 3: Nâng cao

- Tự động phát âm thanh qua AI.
- Tích hợp SMS/Zalo OA.
- Realtime nâng cao.
- Watermark tài liệu.
- Tìm kiếm toàn văn tài liệu.
- Phân quyền chi tiết hơn.

---

## 10. Kết luận

Giải pháp đơn giản nên xoay quanh một quy trình dễ hiểu:

**Tạo cuộc họp -> Thêm chương trình -> Thêm tài liệu -> Chọn đại biểu -> Ban hành/gửi giấy mời -> Đại biểu xác nhận -> Điểm danh -> Biểu quyết/thảo luận/chất vấn -> Cập nhật kết luận.**

Đây là phiên bản phù hợp hơn để triển khai nhanh cho UBND phường, xã vì:

- Màn hình ít hơn.
- Thao tác gần với nghiệp vụ giấy mời truyền thống.
- Người dân chỉ thấy nội dung công khai.
- Đại biểu chỉ thấy việc cần làm.
- Thư ký/điều hành có đủ công cụ để vận hành cuộc họp.
- Quản lý vẫn có trang quản trị để cấu hình và tạo cuộc họp.

Các chức năng nâng cao như AI âm thanh, màn chiếu nâng cao, nhắc lịch phức tạp và realtime toàn diện nên triển khai sau khi người dùng đã quen với hệ thống.
