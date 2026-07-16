# API Chính sách trợ cấp (Beneficiary Subsidy Policy)

> Cập nhật lần cuối: 16/07/2026 — tạo mới cùng module Beneficiary.

Danh mục mức trợ cấp theo quy định pháp luật. `organization_id = null` → áp dụng toàn TP/quốc gia (catalog chung); có giá trị → chỉ tổ chức đó dùng. **Không có** `bulk-status`/`{id}/status` — không có cột `status`, hiệu lực xác định bởi `effective_from`/`effective_to`.

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Base path:** `/api/beneficiary-subsidy-policies`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-subsidy-policies/stats` |
| **Response** | `{ "total": 6, "effective": 4 }`. |

---

## Danh sách chính sách trợ cấp

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-subsidy-policies` |
| **Query** | `beneficiary_type`, `sort_by` (id \| amount \| effective_from \| created_at), `sort_order`, `limit`. |
| **Response** | Paginated collection (`SubsidyPolicyResource`). |

---

## Chi tiết chính sách trợ cấp

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-subsidy-policies/{id}` |
| **Response** | `SubsidyPolicyResource`. |

---

## Tạo chính sách trợ cấp

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiary-subsidy-policies` |
| **Body** | `beneficiary_type` (nullable, 1 trong 12 nhóm `BeneficiaryTypeEnum`), `relationship_type` (nullable, áp dụng cho thân nhân), `amount` (required, numeric, >= 0), `unit` (nullable, mặc định `VND/tháng`), `legal_basis` (required, max 255), `effective_from` (required date), `effective_to` (nullable date, phải sau `effective_from`). |
| **Response** | 201, `SubsidyPolicyResource`. |

---

## Cập nhật chính sách trợ cấp

| | |
|---|---|
| **Method** | PUT |
| **Path** | `/api/beneficiary-subsidy-policies/{id}` |
| **Body** | Giống tạo (tất cả optional). Lưu ý: sửa `amount` trực tiếp **không** cập nhật các `subsidy_grants` đã cấp theo policy này — dùng `renew` (bên dưới) khi cần đổi mức và giữ lịch sử. |

---

## Xóa chính sách trợ cấp

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/beneficiary-subsidy-policies/{id}` |
| **Lưu ý** | FK từ `subsidy_grants.beneficiary_subsidy_policy_id` không có `onDelete` (mặc định `RESTRICT`) — nếu đã có `subsidy_grants` tham chiếu, MySQL sẽ từ chối xóa (lỗi 500, chưa có validate thân thiện ở tầng Service). |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/beneficiary-subsidy-policies/bulk-delete` |
| **Body** | `ids` (array, required, min 1). |

---

## Ban hành chính sách mới thay thế (renew)

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiary-subsidy-policies/{id}/renew` |
| **Permission** | `beneficiary-subsidy-policies.renew` |
| **UrlParam** | `id` — chính sách cũ. |
| **Body** | Giống tạo chính sách mới: `amount` (required), `legal_basis` (required), `effective_from` (required), `beneficiary_type`/`relationship_type` (nullable — nếu bỏ trống sẽ giữ nguyên theo policy cũ). |
| **Side-effect (1 transaction)** | 1) Đóng `effective_to` của policy cũ = ngày trước `effective_from` mới. 2) Tạo policy mới. 3) Với **mọi** `subsidy_grants` đang `active` thuộc policy cũ: đóng `granted_to`, chuyển `status = terminated`, và tạo grant mới nối tiếp theo policy mới (không sửa `amount` bản ghi cũ — giữ nguyên lịch sử). |
| **Response** | 201, `SubsidyPolicyResource` (chính sách mới). |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-subsidy-policies/export` |
| **Cột xuất** | STT, Loại đối tượng, Mức trợ cấp, Đơn vị, Căn cứ pháp lý, Ngày hiệu lực, Ngày hết hiệu lực, Ngày tạo, Ngày cập nhật, ID. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiary-subsidy-policies/import` |
| **Body** | `file` (required, xlsx/xls/csv, max 10MB). |
| **Cột bắt buộc** | `amount`, `legal_basis`, `effective_from`. **Không bắt buộc**: `beneficiary_type`, `unit`. |

---

## Response mẫu (SubsidyPolicyResource)

```json
{
  "id": 1,
  "beneficiary_type": "war_invalid",
  "beneficiary_type_label": "Thương binh, người hưởng chính sách như thương binh",
  "relationship_type": null,
  "relationship_type_label": null,
  "amount": "3500000.00",
  "unit": "VND/tháng",
  "legal_basis": "Nghị định 75/2021/NĐ-CP",
  "effective_from": "01/07/2021",
  "effective_to": null,
  "is_effective": true,
  "created_at": "14:09:30 16/07/2026",
  "updated_at": "14:09:30 16/07/2026"
}
```
