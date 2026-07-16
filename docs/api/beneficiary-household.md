# API Hộ gia đình (Beneficiary Household)

> Cập nhật lần cuối: 16/07/2026 — tạo mới cùng module Beneficiary.

Quản lý hộ gia đình có người có công. **Không có** endpoint `bulk-status`/`{id}/status` — bảng `beneficiary_households` không có cột `status` (không có vòng đời trạng thái theo thiết kế).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Base path:** `/api/beneficiary-households`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-households/stats` |
| **Response** | `{ "total": 20, "total_members": 45 }`. |

---

## Danh sách hộ gia đình

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-households` |
| **Query** | `search` (tên chủ hộ/mã hộ), `residential_area_id`, `from_date`, `to_date`, `sort_by` (id \| head_name \| household_code \| member_count \| created_at), `sort_order`, `limit`. |
| **Response** | Paginated collection (`HouseholdResource`). |

---

## Chi tiết hộ gia đình

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-households/{id}` |
| **Response** | `HouseholdResource` kèm `residential_area`. |

---

## Tạo hộ gia đình

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiary-households` |
| **Body** | `residential_area_id` (nullable, exists), `household_code` (nullable — để trống tự sinh `{SLUG_ORG}-HGD-{seq}`), `head_name` (required, max 255), `head_id_number`, `address` (required, max 255), `phone`, `note`, `beneficiary_ids[]` (nullable, exists `beneficiaries`), `dependent_ids[]` (nullable, exists `beneficiary_dependents`). |
| **Side-effect** | Nếu có `beneficiary_ids`/`dependent_ids`: gán `household_id` cho các bản ghi đó ngay trong cùng transaction — `member_count` tự cập nhật qua Observer. |
| **Response** | 201, `HouseholdResource`. |

---

## Cập nhật hộ gia đình

| | |
|---|---|
| **Method** | PUT |
| **Path** | `/api/beneficiary-households/{id}` |
| **Body** | `residential_area_id`, `household_code`, `head_name`, `head_id_number`, `address`, `phone`, `note` (tất cả optional). Không nhận `beneficiary_ids`/`dependent_ids` ở đây — gán/tháo thành viên làm qua `PUT /api/beneficiaries/{id}` hoặc `PUT /api/beneficiary-dependents/{id}` (đổi `household_id`). |
| **Response** | `HouseholdResource`. |

---

## Xóa hộ gia đình

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/beneficiary-households/{id}` |
| **Lưu ý** | **Không xóa dây chuyền** thành viên — `beneficiaries.household_id`/`beneficiary_dependents.household_id` tự chuyển `NULL`. |
| **Response** | `{ "success": true, "message": "Xóa hộ gia đình thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/beneficiary-households/bulk-delete` |
| **Body** | `ids` (array, required, min 1). |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-households/export` |
| **Cột xuất** | STT, Mã hộ, Chủ hộ, CCCD chủ hộ, Tổ dân phố, Địa chỉ, SĐT, Số thành viên, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiary-households/import` |
| **Body** | `file` (required, xlsx/xls/csv, max 10MB). |
| **Cột bắt buộc** | `head_name`, `address`. **Không bắt buộc**: `household_code`, `head_id_number`, `phone`. |

---

## Response mẫu (HouseholdResource)

```json
{
  "id": 3,
  "residential_area_id": 1,
  "residential_area": { "id": 1, "name": "Tổ 5" },
  "household_code": "HGD-00001",
  "head_name": "Nguyễn Văn A",
  "head_id_number": "049123456789",
  "address": "12 Trần Phú, Hải Châu",
  "phone": "0905123456",
  "member_count": 2,
  "note": null,
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "14:09:30 16/07/2026",
  "updated_at": "14:09:30 16/07/2026"
}
```

`member_count` là trường denormalized — chỉ được ghi qua `HouseholdObserver`, không tính `COUNT()` runtime.
