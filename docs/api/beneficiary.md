# API Người có công (Beneficiary)

> Ngày tạo: 10:00:00 16/07/2026
> Cập nhật lần cuối: 09:55:00 26/07/2026 — thêm `residential_area_id` (tổ dân phố là trường riêng của người có công); `store`/`update` nhận 3 mảng con `classifications`/`dependents`/`documents` theo cơ chế **thay thế toàn bộ**; bỏ nhánh tạo hộ lồng; response thêm `dependents`. Bản trước còn mô tả trợ cấp / lịch sử trạng thái — đã bỏ từ 25/07.

Quản lý hồ sơ người có công: thống kê, danh sách, chi tiết, CRUD, xóa/đổi trạng thái hàng loạt, đổi trạng thái, xuất/nhập Excel. Không có endpoint công khai (dữ liệu cá nhân nhạy cảm).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** mọi endpoint chỉ thao tác hồ sơ thuộc tổ chức hiện tại, route dùng `ensure.route.org` — thao tác theo ID thuộc org khác trả 404.

**Base path:** `/api/beneficiaries`

---

## 1. Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiaries/stats` |
| **Query** | Cùng bộ với danh sách (`search`, `status`, `type`, `household_id`, `residential_area_id`, `from_date`, `to_date`) |
| **Response** | `{ "total": 50, "pending": 5, "active": 40, "deceased": 5 }` |

Truyền y hệt filter đang áp ở bảng để KPI khớp với danh sách đang hiển thị.

---

## 2. Danh sách

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiaries` |
| **Query** | `search`, `status`, `type`, `household_id`, `residential_area_id`, `from_date`, `to_date`, `sort_by`, `sort_order`, `limit` |

| Tham số | Ý nghĩa |
|---|---|
| `search` | Quét **6 cột**: họ tên / CCCD / SĐT của **người có công**, và họ tên / CCCD / SĐT của **thân nhân liên kết**. Gõ một số CCCD bất kỳ là ra hồ sơ liên quan, không cần biết nó của ai. |
| `status` | `pending` \| `active` \| `deceased` \| `moved_out` \| `suspended` |
| `type` | Loại đối tượng (`BeneficiaryTypeEnum`, 12 nhóm). Hồ sơ kiêm nhiều loại vẫn khớp khi lọc theo bất kỳ loại nào của nó. |
| `household_id` | Hộ gia đình |
| `residential_area_id` | Tổ dân phố / thôn (trường riêng của người có công, không suy qua hộ) |
| `from_date` / `to_date` | Khoảng `created_at` (Y-m-d) |
| `sort_by` | `id` \| `full_name` \| `date_of_birth` \| `status` \| `created_at` \| `updated_at` (sắp tiếng Việt qua `VietnameseSort`) |
| `sort_order` | `asc` \| `desc` (mặc định `desc`) |
| `limit` | Số bản ghi/trang; `-1` = không phân trang |

Eager load: `household`, `residentialArea`, `creator`, `editor`. Đếm: `dependents_count`, `documents_count`.

**Không** trả `classifications` / `dependents` / `documents` chi tiết — lấy ở endpoint chi tiết.

---

## 3. Chi tiết

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiaries/{id}` |

Trả đầy đủ: `household`, `residential_area`, `classifications[]` (kèm `decision_files`), `dependents[]` (kèm thông tin thân nhân lồng), `documents[]` (kèm `files`), `created_by`, `updated_by`.

### Thân nhân chính và tọa độ bản đồ

Mỗi hồ sơ có **tối đa 1 thân nhân chính** (`dependents[].is_primary`), cũng được trả riêng ở
`primary_dependent` cho tiện.

Ba khóa `map_latitude` / `map_longitude` / `map_source` là **tọa độ để chấm lên bản đồ**:

| `map_source` | Nghĩa |
|---|---|
| `self` | Lấy tọa độ của chính người có công |
| `primary_dependent` | Người có công **đã mất** → lấy theo thân nhân chính |

Hồ sơ đã mất thì tọa độ của người đã khuất không còn ý nghĩa thực địa, nhưng cán bộ vẫn cần một
điểm để đến thăm viếng / chi trả cho thân nhân. Chưa gán thân nhân chính, hoặc thân nhân chính chưa
có tọa độ → giữ tọa độ gốc (`map_source = self`).

`latitude` / `longitude` **luôn là dữ liệu gốc**, không bị ghi đè — FE dựng bản đồ thì dùng `map_*`,
form nhập liệu thì dùng cặp gốc. Nên hiển thị nguồn khi `map_source = primary_dependent` để người
dùng không thắc mắc vì sao một người đã mất lại có vị trí.

```json
{
  "success": true,
  "data": {
    "id": 1,
    "household_id": 3,
    "household": { "id": 3, "head_name": "Trần Văn A" },
    "residential_area_id": 5,
    "residential_area": { "id": 5, "name": "Tổ 5" },
    "full_name": "Trần Văn B",
    "date_of_birth": "20/05/1950",
    "birth_year": null,
    "gender": "male",
    "gender_label": "Nam",
    "id_number": "049123456789",
    "status": "active",
    "status_label": "Đang hưởng",
    "death_date": null,
    "address": "12 Trần Phú, Hải Châu",
    "latitude": "16.0678000",
    "longitude": "108.2208000",
    "phone": "0905123456",
    "note": null,

    "classifications": [
      {
        "id": 7,
        "type": "war_invalid",
        "type_label": "Thương binh, người hưởng chính sách như thương binh",
        "decision_no": "123/QĐ-UBND",
        "decision_date": "20/05/2010",
        "issued_by": "UBND TP Đà Nẵng",
        "is_primary": true,
        "decision_files": [
          { "id": 11, "name": "qd.pdf", "url": "https://.../qd.pdf", "size": 20480 }
        ]
      }
    ],
    "dependents": [
      {
        "id": 4,
        "beneficiary_id": 1,
        "dependent_id": 12,
        "dependent": {
          "id": 12, "full_name": "Trần Thị C", "date_of_birth": "10/02/1980",
          "id_number": "049...", "phone": "0905..."
        },
        "relationship_type": "child",
        "relationship_type_label": "Con",
        "is_primary": true,
        "note": "Con ruột"
      }
    ],
    "primary_dependent": { "id": 4, "dependent_id": 12, "is_primary": true, "…": "như phần tử trong dependents[]" },
    "map_latitude": "16.0678000",
    "map_longitude": "108.2208000",
    "map_source": "self",
    "documents": [
      {
        "id": 9,
        "beneficiary_id": 1,
        "name": "Giấy chứng nhận thương binh",
        "note": "Bản sao công chứng",
        "files": [
          { "id": 21, "name": "gcn.pdf", "url": "https://.../gcn.pdf", "size": 51200 }
        ]
      }
    ],

    "dependents_count": 1,
    "documents_count": 1,
    "created_by": { "id": 2, "name": "Cán bộ A" },
    "updated_by": { "id": 2, "name": "Cán bộ A" },
    "created_at": "09:00:00 15/01/2026",
    "updated_at": "09:00:00 15/01/2026"
  }
}
```

---

## 4. Tạo hồ sơ

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/beneficiaries` |
| **Status** | 201 |

```json
{
  "full_name": "Trần Văn B",
  "gender": "male",
  "household_id": 3,
  "residential_area_id": 5,
  "date_of_birth": "1950-05-20",
  "birth_year": null,
  "id_number": "049123456789",
  "status": "pending",
  "address": "12 Trần Phú, Hải Châu",
  "latitude": 16.0678,
  "longitude": 108.2208,
  "phone": "0905123456",
  "note": null,

  "classifications": [
    { "type": "war_invalid", "decision_no": "123/QĐ", "decision_date": "2010-05-20",
      "issued_by": "UBND TP Đà Nẵng", "is_primary": true }
  ],
  "dependents": [
    { "dependent_id": 12, "relationship_type": "child", "is_primary": true, "note": "Con ruột" }
  ],
  "documents": [
    { "name": "Giấy chứng nhận thương binh", "note": "Bản sao" }
  ]
}
```

**Bắt buộc:** `full_name`, `gender`. `status` mặc định `pending`.

**Quan hệ là ID, không nhúng đối tượng:** `household_id`, `residential_area_id` phải tạo trước qua resource của nó (`/api/beneficiary-households`, `/api/beneficiary-residential-areas`). Thân nhân cũng vậy — tạo qua `/api/beneficiary-dependents` rồi liên kết bằng `dependent_id`.

**Không nhận `id`** trong phần tử của 3 mảng con → 422.

**`classifications`:** chỉ `type` bắt buộc; `decision_no`/`decision_date`/`issued_by` bổ sung sau khi có đủ giấy tờ. Tối đa 1 phần tử `is_primary = true` (không bắt buộc phải có).

**`dependents`:** `dependent_id` + `relationship_type` bắt buộc. `is_primary` đánh dấu **thân nhân chính** — tối đa 1 phần tử, gửi 2 trở lên → 422 tại field `dependents`. Nên gán cho hồ sơ có khả năng chuyển sang trạng thái đã mất, để bản đồ và đầu mối liên hệ không bị mất theo.

---

## 5. Cập nhật hồ sơ

| | |
|---|---|
| **Method** | PUT |
| **Path** | `/api/beneficiaries/{id}` |

Các cột thuộc chính hồ sơ cập nhật như bình thường. **Ba mảng con theo cơ chế THAY THẾ TOÀN BỘ:**

| Gửi gì | Kết quả |
|---|---|
| Không gửi khóa | Giữ nguyên danh sách hiện có |
| Gửi mảng có phần tử | Xóa sạch danh sách cũ rồi tạo lại theo mảng gửi lên |
| Gửi `[]` | Xóa sạch danh sách đó |

Không có `*_deleted`. `PUT` idempotent: gửi lại đúng payload vẫn ra đúng một trạng thái.

> ⚠️ **Gửi `documents` hoặc `classifications` sẽ XÓA file đính kèm** của các dòng cũ (giấy tờ scan, file quyết định), vì dòng mới có `id` mới. Màn hình không sửa hai danh sách này thì **đừng gửi hai khóa đó**.

---

## 6. Xóa / hàng loạt / đổi trạng thái

| Việc | Method | Path | Body |
|---|---|---|---|
| Xóa 1 hồ sơ | DELETE | `/api/beneficiaries/{id}` | — |
| Xóa hàng loạt | DELETE | `/api/beneficiaries/bulk-delete` | `{ "ids": [1,2,3] }` |
| Đổi trạng thái hàng loạt | PATCH | `/api/beneficiaries/bulk-status` | `{ "ids": [1,2], "status": "suspended" }` |
| Đổi trạng thái 1 hồ sơ | PATCH | `/api/beneficiaries/{id}/status` | `{ "status": "deceased", "death_date": "2026-01-20" }` |

`death_date` chỉ set được qua `PATCH /{id}/status` (khi `status = deceased`) hoặc qua import — **không** nằm trong body `store`/`update`.

Đổi trạng thái **không ghi lịch sử** (bảng audit đã bỏ từ 25/07).

---

## 7. File quyết định của loại đối tượng

| Việc | Method | Path |
|---|---|---|
| Đính file | POST | `/api/beneficiaries/{beneficiary}/classifications/{classification}/files` |
| Xóa file | DELETE | `/api/beneficiaries/{beneficiary}/classifications/{classification}/files/{media}` |

Body upload: `files[]` (multipart, ≥1 file, mỗi file ≤ 10MB). Dùng chung permission `beneficiaries.update`.

Vì là multipart nên **không** gửi kèm được trong body JSON của `store`/`update` — phải lưu hồ sơ trước để lấy `classifications[].id`, rồi mới upload.

---

## 8. Xuất / Nhập Excel

| Việc | Method | Path |
|---|---|---|
| Xuất | GET | `/api/beneficiaries/export` (nhận cùng bộ query như danh sách) |
| Nhập | POST | `/api/beneficiaries/import` — `file` (xlsx/xls/csv, ≤10MB) |
| Tải file mẫu | GET | `/api/beneficiaries/import-template` |

**Cột export:** STT, Họ tên, Ngày sinh, Năm sinh, Giới tính, CCCD/CMND, CCCD chủ hộ, Tổ dân phố, Trạng thái, Ngày mất, Địa chỉ, Vĩ độ, Kinh độ, SĐT, Ghi chú, Thân nhân chính, **Loại đối tượng**, **Thân nhân**, **Giấy tờ**, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID.

Ba cột in đậm là **liệt kê tham chiếu** (ngăn cách `; `), import **bỏ qua**. Cột "Thân nhân" có dạng `Tên (Quan hệ)`.

**Cột import bắt buộc:** Họ tên, Giới tính. Còn lại tùy chọn. Quan hệ nhập bằng **tên/mã**: "CCCD chủ hộ" → hộ, "Tổ dân phố" → tên tổ. Không khớp thì để trống, **không chặn dòng**.

Giới tính và Trạng thái nhận cả value gốc (`male`, `pending`) lẫn nhãn tiếng Việt (`Nam`, `Chờ công nhận`) → export ra sửa rồi nhập lại được.

**Kết quả import** trả `data.error_file = { name, mime, base64 }` — file Excel tổng hợp lỗi (STT | Hàng số | Cột | Lỗi | Giá trị), `null` khi không có lỗi. Dòng lỗi bị bỏ qua, dòng hợp lệ vẫn nhập.

---

## 9. Phân quyền

| Endpoint | Permission |
|---|---|
| `stats`, `index`, `show`, `store`, `update`, `destroy`, `bulkDestroy`, `bulkUpdateStatus`, `changeStatus`, `export`, `import` | `beneficiaries.{action}` |
| `import-template` | `beneficiaries.import` (dùng chung) |
| File quyết định | `beneficiaries.update` |

**Quyền phụ cho mảng lồng** — gửi `documents`/`dependents` trong `store`/`update` cần thêm:

| Payload | Quyền |
|---|---|
| `documents` khác rỗng | `beneficiary-documents.store` |
| `documents` khi hồ sơ đang có tài liệu (dòng cũ bị xóa) | `beneficiary-documents.destroy` |
| `dependents` khác rỗng | `beneficiary-dependents.storeRelation` |
| `dependents` khi hồ sơ đang có quan hệ (dòng cũ bị xóa) | `beneficiary-dependents.destroyRelation` |

Thiếu quyền → **403 toàn request**, không ghi gì cả. `classifications` không cần quyền phụ.

---

## Liên quan

- [Hộ gia đình](beneficiary-household.md) · [Thân nhân](beneficiary-dependent.md)
- Giấy tờ hồ sơ: `/api/beneficiary-documents` · Tổ dân phố: `/api/beneficiary-residential-areas`
- Enum cho dropdown: `GET /api/beneficiary-enums`
- Thống kê dashboard: `/api/beneficiary-statistics`
- **Hướng dẫn tích hợp chi tiết cho FE:** [nguoi-co-cong-huong-dan-frontend_095245_26072026.md](../answer/nguoi-co-cong-huong-dan-frontend_095245_26072026.md)
