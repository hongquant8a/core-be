# [BE→FE] Đơn giản hóa module Người có công

> Ngày tạo: 14:26:28 25/07/2026
> Cập nhật lần cuối: 14:26:28 25/07/2026

Module Người có công được đơn giản hóa về **thông tin cơ bản + giấy tờ đính kèm**. Đây là **breaking change** với FE.

## Endpoint bị GỠ

- `beneficiary-subsidy-policies/*` (mức trợ cấp) — toàn bộ.
- `beneficiary-subsidy-grants/*` (cấp trợ cấp) — toàn bộ.
- `beneficiary-visit-schedules/*` (lịch viếng thăm) — toàn bộ.
- `beneficiary/notification-config/*` — toàn bộ (module không còn nhắc lịch).
- `GET /beneficiaries/{id}/status-histories` và `GET /beneficiary-dependents/{id}/status-histories` (bỏ audit trạng thái).

## Trường bị BỎ trong response/request

| Resource | Trường bỏ |
|---|---|
| Tổ dân phố | `code` (thêm `note`) |
| Hộ gia đình | `household_code` |
| Người có công | `injury_rate`, `recognition_decision_no`, `recognition_date` |
| Thân nhân | `is_alive`, `death_date`, `eligibility_status` (thêm `phone`, `latitude`, `longitude`, `residential_area_id` + object `residential_area`) |
| Quan hệ (relation) | `eligible_from`, `eligible_until`, `status`, `status_label` |
| `PATCH /beneficiaries/{id}/status` | không còn nhận `reason` |
| `POST /beneficiary-dependents/{id}/relations` | không còn `eligible_from` |
| Enum (`beneficiary-enums`) | bỏ `dependent_eligibility`, `dependent_relation_status`, `subsidy_status`, `document_type`, `visit_occasion`, `schedule_status` (còn `beneficiary_type`, `beneficiary_status`, `gender`, `dependent_relationship`) |

## Endpoint MỚI

- **`beneficiary-documents`** (`index, show, store, update, destroy, bulk-delete`) — quản lý giấy tờ hồ sơ: mỗi bản ghi gồm `name` (Tên giấy tờ) + nhiều tập tin. Tạo/sửa gửi multipart `files[]`; xóa file qua `files_deleted[]` (mảng media id) khi `PUT`.
- **File quyết định công nhận cho phân loại**:
  - `POST /beneficiaries/{beneficiary}/classifications/{classification}/files` — multipart `files[]`.
  - `DELETE /beneficiaries/{beneficiary}/classifications/{classification}/files/{media}`.
  - `BeneficiaryClassificationResource` nay có mảng `decision_files: [{id,name,url,size}]` (khi eager-load).

## Thay đổi khác

- `BeneficiaryResource` bỏ `active_subsidy_grants_count`, thêm `documents`, `documents_count`.
- Import: cột tra hộ đổi từ **Mã hộ** → **CCCD chủ hộ** (`head_id_number`); import thân nhân/hộ bỏ các cột đã xóa, thêm cột mới (SĐT, tọa độ, Tổ dân phố cho thân nhân; Ghi chú cho tổ dân phố).

## Dashboard thống kê (endpoint mới)

- `beneficiary-statistics/*` (permission `beneficiary-statistics.view`): `overview`, `by-type`, `by-status`, `by-residential-area`, `households-by-area`, `by-gender`, `by-age-group`, `by-relationship`, `new-by-month?year=`. Mỗi breakdown trả `{key,label,total}`; `overview` kèm `summary` (KPI).

## Export bổ sung quan hệ

File export (hộ, người có công, thân nhân, tổ dân phố) nay có thêm cột liệt kê quan hệ xung quanh, quan hệ 1-N/N-N ngăn cách bởi `; ` (VD "Thân nhân", "Loại đối tượng", "Giấy tờ", "Người có công liên kết", "Danh sách hộ"). Các cột này **chỉ để đọc** — khi import lại sẽ bị bỏ qua. Import vẫn liên kết danh mục 1-1 bằng tên (Tổ dân phố) / CCCD chủ hộ với ràng buộc tối thiểu.

Tài liệu chi tiết: [docs/database/Beneficiary.md](../database/Beneficiary.md), [docs/modules/Beneficiary/README.md](../modules/Beneficiary/README.md).
