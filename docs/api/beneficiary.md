# API Người có công (Beneficiary)

> Cập nhật lần cuối: 16/07/2026 — tạo mới cùng module Beneficiary (migration `2026_07_16_*`).

Quản lý hồ sơ người có công: thống kê, danh sách, chi tiết, CRUD, xóa/cập nhật trạng thái hàng loạt, đổi trạng thái (kèm ghi lịch sử + tự dừng trợ cấp), xuất/nhập Excel, lịch sử thay đổi trạng thái. Không có endpoint công khai (dữ liệu cá nhân nhạy cảm).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint chỉ thao tác hồ sơ thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`), route dùng middleware `ensure.route.org` — thao tác theo ID thuộc org khác trả 404.

**Base path:** `/api/beneficiaries`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiaries/stats` |
| **Query** | `search` (họ tên/CCCD), `status`, `from_date`, `to_date`. |
| **Response** | `{ "total": 50, "pending": 5, "active": 40, "deceased": 5 }`. |

---

## Danh sách người có công

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiaries` |
| **Query** | `search` (họ tên/CCCD), `status` (pending \| active \| deceased \| moved_out \| suspended), `household_id`, `from_date`, `to_date`, `sort_by` (id \| full_name \| date_of_birth \| status \| created_at), `sort_order`, `limit`. |
| **Response** | Paginated collection (`BeneficiaryResource`), có `dependents_count`, `active_subsidy_grants_count`. |

---

## Chi tiết người có công

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiaries/{id}` |
| **Response** | `BeneficiaryResource` — kèm `household`, `classifications[]`. |

---

## Tạo hồ sơ người có công

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiaries` |
| **Body** | `household_id` (nullable, exists), `full_name` (required, max 255), `date_of_birth` (nullable date), `gender` (required: male \| female \| other), `id_number` (nullable), `injury_rate` (nullable, 0-100), `recognition_decision_no`, `recognition_date`, `status` (nullable, mặc định `pending`), `address`, `phone`, `note`, `classifications[]` (mảng, mỗi phần tử: `type` — 1 trong 12 nhóm `BeneficiaryTypeEnum`, `decision_no`, `decision_date`, `issued_by`, `is_primary`). |
| **Validate riêng** | Nếu có `classifications`, bắt buộc đúng **1** phần tử `is_primary = true` (lỗi field `classifications` nếu không đúng 1). |
| **Response** | 201, `BeneficiaryResource`. |

---

## Cập nhật hồ sơ người có công

| | |
|---|---|
| **Method** | PUT |
| **Path** | `/api/beneficiaries/{id}` |
| **Body** | Giống tạo (không có `classifications` — sửa phân loại chưa hỗ trợ qua endpoint này). |
| **Response** | `BeneficiaryResource`. |

---

## Xóa hồ sơ người có công

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/beneficiaries/{id}` |
| **Lưu ý** | Xóa dây chuyền `beneficiary_classifications` và các quan hệ pivot `beneficiary_dependent_relations`. **Không** xóa/cảnh báo các `subsidy_grants`/`status_histories`/`visit_schedules` liên quan (quan hệ polymorphic, không có FK ràng buộc) — các bản ghi này sẽ mồ côi. |
| **Response** | `{ "success": true, "message": "Xóa hồ sơ người có công thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/beneficiaries/bulk-delete` |
| **Body** | `ids` (array, required, min 1). |

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/beneficiaries/bulk-status` |
| **Body** | `ids` (array), `status` (required). |

---

## Đổi trạng thái

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/beneficiaries/{id}/status` |
| **Body** | `status` (required), `reason` (nullable), `death_date` (bắt buộc nếu `status = deceased`). |
| **Side-effect** | Ghi 1 dòng `beneficiary_status_histories`. Nếu chuyển `deceased`/`moved_out`: tự động `terminated` toàn bộ `subsidy_grants` đang `active` của người này. |
| **Response** | `BeneficiaryResource` đã cập nhật. |

---

## Lịch sử thay đổi trạng thái

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiaries/{id}/status-histories` |
| **Query** | `from_date`, `to_date`, `limit`. |
| **Response** | Paginated collection (`StatusHistoryResource`): `old_status`, `new_status`, `reason`, `changed_by`, `changed_at`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiaries/export` |
| **Cột xuất** | STT, Họ tên, Ngày sinh, Giới tính, CCCD/CMND, Mã hộ, Trạng thái, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiaries/import` |
| **Body** | `file` (required, xlsx/xls/csv, max 10MB). |
| **Cột bắt buộc** | `full_name`, `gender`. **Không bắt buộc**: `date_of_birth`, `id_number`, `status` (mặc định `pending`). |

---

## Response mẫu (BeneficiaryResource)

```json
{
  "id": 22,
  "household_id": 3,
  "household": { "id": 3, "household_code": "HGD-00001", "head_name": "Nguyễn Văn A" },
  "full_name": "Trần Văn B",
  "date_of_birth": "20/05/1950",
  "gender": "male",
  "gender_label": "Nam",
  "id_number": "049123456789",
  "injury_rate": 61,
  "recognition_decision_no": "QD-123/2020",
  "recognition_date": "15/07/2020",
  "status": "active",
  "status_label": "Đang hưởng",
  "death_date": null,
  "address": null,
  "phone": null,
  "note": null,
  "classifications": [
    { "id": 5, "type": "war_invalid", "type_label": "Thương binh, người hưởng chính sách như thương binh", "decision_no": "QD-123/2020", "decision_date": "15/07/2020", "issued_by": "Sở LĐTBXH TP Đà Nẵng", "is_primary": true }
  ],
  "dependents_count": 2,
  "active_subsidy_grants_count": 1,
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "14:09:30 16/07/2026",
  "updated_at": "14:09:30 16/07/2026"
}
```

`classifications`, `dependents_count`, `active_subsidy_grants_count` chỉ có khi endpoint eager-load/`withCount` quan hệ đó (`index`/`show` đã load sẵn).

---

*Xem thêm:* [beneficiary-household.md](beneficiary-household.md), [beneficiary-dependent.md](beneficiary-dependent.md), [beneficiary-subsidy-policy.md](beneficiary-subsidy-policy.md), [beneficiary-subsidy-grant.md](beneficiary-subsidy-grant.md), [beneficiary-visit-schedule.md](beneficiary-visit-schedule.md). Cấu hình nhắc lịch viếng thăm dùng chung route pattern ở [notification-config.md](notification-config.md), base path `/api/beneficiary/notification-config`, `module_key=beneficiary`.
