# API Trợ cấp (Beneficiary Subsidy Grant) — ⚠️ ĐÃ GỠ BỎ

> Ngày tạo: 10:00:00 16/07/2026
> Cập nhật lần cuối: 09:55:00 26/07/2026 — đánh dấu đã gỡ bỏ.

> ## ⚠️ Chức năng này KHÔNG CÒN TỒN TẠI
>
> Toàn bộ engine trợ cấp đã bị gỡ khi đơn giản hóa module ngày 25/07/2026: bảng, model, controller,
> route và permission đều đã xóa. **Mọi endpoint mô tả bên dưới trả 404.**
>
> Tài liệu giữ lại làm tham chiếu lịch sử. FE còn module nào gọi các endpoint này thì phải gỡ.

Cấp & dừng trợ cấp cho người có công/thân nhân. Chỉ có **3 action**: `index`, `store`, `changeStatus` — không có `update`/`destroy`/`bulkDestroy`/`export`/`import`. Lý do: grant chỉ phát sinh qua hành động nghiệp vụ (cấp trợ cấp), không phải danh mục CRUD tự do; sửa mức trợ cấp phải qua `POST /api/beneficiary-subsidy-policies/{id}/renew` để giữ lịch sử.

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Base path:** `/api/beneficiary-subsidy-grants`

---

## Danh sách trợ cấp

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-subsidy-grants` |
| **Query** | `subject_type` (giá trị morph alias — xem bên dưới), `subject_id`, `status` (active \| terminated \| suspended), `from_date`/`to_date` (theo `granted_from`), `sort_by` (id \| amount \| granted_from \| created_at), `sort_order`, `limit`. |
| **Response** | Paginated collection (`SubsidyGrantResource`). |

---

## Cấp trợ cấp

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiary-subsidy-grants` |
| **Body** | `subject_type` (required: `beneficiary` \| `dependent` — giá trị thân thiện, Service tự map sang morph class thật), `subject_id` (required, ID của Beneficiary/Dependent tương ứng), `beneficiary_subsidy_policy_id` (required, exists), `amount` (nullable — để trống sẽ tự lấy theo `policy.amount`), `granted_from` (required date). |
| **Validate** | Chặn nếu `policy.effective_to` đã qua (lỗi field `beneficiary_subsidy_policy_id`, không phải lỗi 500). |
| **Response** | 201, `SubsidyGrantResource`. |

---

## Đổi trạng thái trợ cấp

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/beneficiary-subsidy-grants/{id}/status` |
| **Body** | `status` (required: `active` \| `terminated` \| `suspended`), `termination_reason` (**required nếu `status = terminated`**). |
| **Side-effect** | Khi `terminated`: tự set `granted_to = now()`. |
| **Response** | `SubsidyGrantResource`. |

---

## Response mẫu (SubsidyGrantResource)

```json
{
  "id": 10,
  "subject_type": "beneficiary",
  "subject_id": 22,
  "subject": { "id": 22, "name": "Trần Văn B" },
  "beneficiary_subsidy_policy_id": 1,
  "policy": { "id": 1, "legal_basis": "Nghị định 75/2021/NĐ-CP" },
  "amount": "3500000.00",
  "granted_from": "01/01/2024",
  "granted_to": null,
  "status": "active",
  "status_label": "Đang chi trả",
  "termination_reason": null,
  "created_at": "14:09:30 16/07/2026",
  "updated_at": "14:09:30 16/07/2026"
}
```

`subject_type` trong response là **morph alias** đã đăng ký (`beneficiary`, `beneficiary_dependent`), không phải tên class PHP đầy đủ — do dự án dùng `Relation::enforceMorphMap()` toàn hệ thống.
