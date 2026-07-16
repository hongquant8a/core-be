# ADR-001: Giữ `beneficiary_subsidy_policies`/`beneficiary_subsidy_grants` trong module Beneficiary

> Ngày tạo: 12:00:00 16/07/2026
> Cập nhật lần cuối: 12:00:00 16/07/2026
> Trạng thái: `accepted`

---

## Bối cảnh

Module Beneficiary (Người có công theo hộ gia đình & thân nhân) cần một engine "cấp/dừng trợ cấp theo chính sách hiệu lực theo thời gian" (`beneficiary_subsidy_policies` + `beneficiary_subsidy_grants`). Về bản chất, cơ chế này — danh mục mức trợ cấp có `effective_from`/`effective_to`, lịch sử cấp phát có thể đóng bản ghi cũ và nối tiếp bản ghi mới khi chính sách đổi — không đặc thù riêng cho người có công. TP Đà Nẵng (Sở LĐTBXH) nhiều khả năng sẽ cần cơ chế tương tự cho các nhóm đối tượng khác (Hộ nghèo/cận nghèo, Bảo trợ xã hội, Người khuyết tật, Người cao tuổi...) trong tương lai.

Câu hỏi đặt ra khi thiết kế: xây engine này dùng chung ngay từ đầu (module/`Core` riêng), hay giữ trong phạm vi module `Beneficiary` cho bản triển khai đầu tiên?

---

## Quyết định

**Chúng tôi chọn:** giữ `beneficiary_subsidy_policies` và `beneficiary_subsidy_grants` trong module `Beneficiary` (namespace `App\Modules\Beneficiary`, bảng có tiền tố `beneficiary_`), không tách thành engine/module dùng chung ở bản triển khai đầu tiên.

---

## Lý do

1. **Chưa có use case thứ 2 xác nhận.** Tại thời điểm triển khai, chỉ module Beneficiary cần cơ chế này. Tách sớm là xây trừu tượng đầu cơ (speculative abstraction) khi chưa biết chắc API dùng chung sẽ trông như thế nào — vi phạm nguyên tắc Simplicity First của dự án (`CLAUDE.md` §2: "No abstractions for single-use code").
2. **Chi phí tách sau thấp.** Nếu module thứ 2 (VD Hộ nghèo) xuất hiện và cần dùng lại, việc tách `SubsidyPolicy`/`SubsidyGrant` sang `Core` hoặc module riêng chủ yếu là di chuyển namespace + đổi tên bảng (migration `RENAME TABLE`) — không phải thiết kế lại logic nghiệp vụ (đóng bản ghi cũ + tạo bản ghi mới nối tiếp, validate hiệu lực policy).
3. **Giữ đúng convention đặt tên hiện tại.** Module dùng chung hiện có trong dự án (`Core`, `app/Services/Notification/`) đều phục vụ ≥ 2 module thật đang chạy (Meeting, TaskAssignment, Scheduling). Subsidy chưa đạt điều kiện đó.

---

## Hệ quả

**Lợi:**
- Giảm thời gian triển khai bản đầu, không cần thiết kế API/quyền hạn cho một engine dùng chung chưa có consumer thứ 2.
- Đúng tinh thần Simplicity First — dễ review, dễ hiểu, không có lớp trừu tượng thừa.

**Bất lợi / Trade-off:**
- Nếu module thứ 2 cần subsidy engine, sẽ phải làm thêm 1 đợt refactor (di chuyển namespace, đổi tên bảng, cập nhật FK ở mọi module đang dùng).
- Trong lúc chờ tách, không có nơi tập trung để chuẩn hoá cách tính "hiệu lực chính sách" nếu có module thứ 2 làm theo cách khác.

**Cần làm thêm:**
- Khi có module thứ 2 cần dùng lại cơ chế subsidy: quay lại ADR này, đổi trạng thái sang `superseded by ADR-{NNN}`, và tạo ADR mới mô tả kiến trúc tách.

---

## Đã xem xét nhưng không chọn

| Phương án | Lý do không chọn |
|---|---|
| Tách `Subsidy` thành module/engine dùng chung ngay từ đầu (`App\Modules\Subsidy`, bảng không có tiền tố `beneficiary_`) | Chưa có module thứ 2 xác nhận cần dùng — thiết kế API dùng chung khi chỉ có 1 consumer dễ đoán sai nhu cầu thực tế, phải sửa lại khi có consumer thật. |
| Đặt trong `Core` (`App\Modules\Core\Models\SubsidyPolicy`) | `Core` hiện chỉ chứa hạ tầng nền tảng dùng chung thực sự (users, orgs, roles, permissions, settings, notification, media) — subsidy là nghiệp vụ domain-specific (an sinh xã hội), không phải hạ tầng, không phù hợp đặt trong `Core` dù có dùng chung sau này. |
