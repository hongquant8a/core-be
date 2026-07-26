# API Thân nhân (Beneficiary Dependent)

> Ngày tạo: 10:00:00 16/07/2026
> Cập nhật lần cuối: 09:55:00 26/07/2026 — bỏ mô tả `eligibility_status` và `status` pivot (đã xóa khi đơn giản hóa 25/07); nêu rõ quan hệ liên kết được từ cả hai chiều.

Quản lý thân nhân và quan hệ với người có công (N-N qua `beneficiary_dependent_relations`). **Không có** `bulk-status`/`{id}/status` — bảng `beneficiary_dependents` không có cột `status` (không có vòng đời trạng thái theo thiết kế). Pivot chỉ giữ `relationship_type` + `note`.

> **Quan hệ liên kết được từ hai chiều, cùng ghi vào một bảng:**
> - Từ phía thân nhân: `POST /api/beneficiary-dependents/{id}/relations` (chọn `beneficiary_id`) — permission `beneficiary-dependents.storeRelation`.
> - Từ phía người có công: mảng `dependents[]` trong body `POST`/`PUT /api/beneficiaries` (chọn `dependent_id`) — **thay thế toàn bộ** danh sách quan hệ của hồ sơ đó, xem [beneficiary.md](beneficiary.md#5-cập-nhật-hồ-sơ).
>
> Lọc danh sách thân nhân theo một người có công: dùng `GET /api/beneficiaries/{id}` rồi đọc `data.dependents[]` (endpoint `beneficiary-dependents` **không** nhận filter `beneficiary_id`).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Base path:** `/api/beneficiary-dependents`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-dependents/stats` |
| **Response** | `{ "total": 30, "alive": 28, "deceased": 2 }`. |

---

## Danh sách thân nhân

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-dependents` |
| **Query** | `search` (họ tên/CCCD), `household_id`, `from_date`, `to_date`, `sort_by` (id \| full_name \| date_of_birth \| created_at), `sort_order`, `limit`. |
| **Response** | Paginated collection (`DependentResource`), có `beneficiaries_count`. |

---

## Chi tiết thân nhân

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-dependents/{id}` |
| **Response** | `DependentResource` kèm `household`, `relations[]` (danh sách quan hệ với từng người có công). |

---

## Tạo thân nhân

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiary-dependents` |
| **Body** | `household_id` (nullable, exists), `full_name` (required), `date_of_birth` (nullable date), `gender` (required: male \| female \| other), `id_number` (nullable), `is_alive` (boolean, mặc định `true`), `death_date` (bắt buộc nếu `is_alive=false`), `eligibility_status` (nullable: `studying` \| `disabled_no_work_capacity` \| `normal`), `note`. |
| **Response** | 201, `DependentResource`. Chưa gắn Beneficiary nào — tạo quan hệ bằng endpoint riêng bên dưới. |

---

## Cập nhật thân nhân

| | |
|---|---|
| **Method** | PUT |
| **Path** | `/api/beneficiary-dependents/{id}` |
| **Body** | Giống tạo (tất cả optional). |
| **Side-effect quan trọng** | Nếu `is_alive` chuyển từ `true` → `false`: **toàn bộ quan hệ pivot đang `active`** của thân nhân này tự động chuyển `expired` (chưa hỗ trợ truy lĩnh ở bản này). |
| **Response** | `DependentResource`. |

---

## Xóa thân nhân

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/beneficiary-dependents/{id}` |
| **Lưu ý** | Xóa dây chuyền toàn bộ `beneficiary_dependent_relations` liên quan (cascade). |
| **Response** | `{ "success": true, "message": "Xóa thân nhân thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/beneficiary-dependents/bulk-delete` |
| **Body** | `ids` (array, required, min 1). |

---

## Thêm quan hệ với người có công

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiary-dependents/{id}/relations` |
| **Permission** | `beneficiary-dependents.storeRelation` |
| **Body** | `beneficiary_id` (required, exists `beneficiaries`), `relationship_type` (required — xem bảng dưới), `note` (tùy chọn). |
| **Response** | 201, `DependentRelationResource`. |

**Giá trị `relationship_type`** (thân nhân là _gì_ của người có công) — lấy động qua `GET /api/beneficiary-enums` → `dependent_relationship`, đừng hardcode:

| Value | Nhãn | | Value | Nhãn |
|---|---|---|---|---|
| `wife` | Vợ | | `older_brother` | Anh |
| `husband` | Chồng | | `older_sister` | Chị |
| `child` | Con | | `younger_sibling` | Em |
| `grandchild` | Cháu | | `foster_parent` | Người nuôi dưỡng |
| `father` | Cha | | `guardian` | Người giám hộ |
| `mother` | Mẹ | | | |

> `spouse` (Vợ/Chồng) đã **tách** thành `wife`/`husband` từ 26/07/2026; dữ liệu cũ được migration
> chuyển theo giới tính thân nhân. Không còn `eligible_from` và `status` trên pivot (bỏ từ 25/07).

---

## Xóa quan hệ với người có công

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/beneficiary-dependents/{id}/relations/{relation}` |
| **Permission** | `beneficiary-dependents.destroyRelation` |
| **UrlParam** | `relation` — ID bản ghi `beneficiary_dependent_relations` (không phải ID beneficiary). |
| **Response** | `{ "success": true, "message": "Xóa quan hệ thành công!" }`. |

---

## Lịch sử thay đổi trạng thái

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-dependents/{id}/status-histories` |
| **Query** | `from_date`, `to_date`, `limit`. |
| **Response** | Paginated collection (`StatusHistoryResource`) — ghi khi Job hàng ngày (`CheckDependentEligibilityCommand`) phát hiện quan hệ hết tuổi hưởng và tự chuyển `expired` (qua `BeneficiaryDependentRelationObserver`, không phải hành động API trực tiếp). |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-dependents/export` |
| **Cột xuất** | STT, Họ tên, Ngày sinh, Giới tính, CCCD/CMND, Mã hộ, Tình trạng sống, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiary-dependents/import` |
| **Body** | `file` (required, xlsx/xls/csv, max 10MB). |
| **Cột bắt buộc** | `full_name`, `gender`. **Không bắt buộc**: `date_of_birth`, `id_number`. |

---

## Response mẫu (DependentResource)

```json
{
  "id": 8,
  "household_id": 3,
  "household": { "id": 3, "household_code": "HGD-00001", "head_name": "Nguyễn Văn A" },
  "full_name": "Lê Thị C",
  "date_of_birth": "01/03/2010",
  "gender": "female",
  "gender_label": "Nữ",
  "id_number": null,
  "is_alive": true,
  "death_date": null,
  "eligibility_status": "studying",
  "eligibility_status_label": "Đang đi học",
  "note": null,
  "relations": [
    {
      "id": 4,
      "beneficiary_id": 22,
      "beneficiary": { "id": 22, "full_name": "Trần Văn B" },
      "relationship_type": "child",
      "relationship_type_label": "Con",
      "eligible_from": "01/01/2020",
      "eligible_until": null,
      "status": "active",
      "status_label": "Đang hưởng",
      "note": null
    }
  ],
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "14:09:30 16/07/2026",
  "updated_at": "14:09:30 16/07/2026"
}
```
