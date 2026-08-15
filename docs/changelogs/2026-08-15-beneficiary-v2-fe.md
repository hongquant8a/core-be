# Người có công v2 — BREAKING CHANGE toàn bộ module (FE)

> Ngày tạo: 14:00:00 15/08/2026  
> Cập nhật lần cuối: 14:00:00 15/08/2026

**Mức độ:** phá vỡ tương thích hoàn toàn. Module cũ đã bị xoá khỏi backend, mọi endpoint v1 trả
404. FE (`src/modules/nguoicocong/`) phải dựng lại theo API mới.

Tài liệu API đầy đủ: [api/beneficiary.md](../api/beneficiary.md).
Lý do từng quyết định: [answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md](../answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md).

---

## 1. Endpoint bị xoá hẳn

| v1 | Thay bằng |
|---|---|
| `/api/beneficiary-households/*` | **Không còn** — bỏ khái niệm hộ gia đình |
| `/api/beneficiary-statistics` | `/api/beneficiaries/stats` |
| `/api/beneficiary-dependents/*` (route phẳng) | `/api/beneficiaries/{id}/dependents/*` (route lồng) |
| `/api/beneficiary-documents/*` (route phẳng) | `/api/beneficiaries/{id}/documents/*` (route lồng) |
| Trợ cấp, lịch thăm hỏi, báo cáo biến động | **Không còn** — ngoài phạm vi v2 |

Màn hình FE phải gỡ: `household/`, `subsidy-grant/`, `subsidy-policy/`, `visit-schedule/`,
`beneficiary-report/`.

Phần bản đồ (`map/`, `map-studio/`, `poi-category/`, `map-import-log/`) **không phụ thuộc** các
endpoint đã xoá — giữ nguyên, chỉ đổi nguồn toạ độ sang `latitude` / `longitude` của
`/api/beneficiaries`.

---

## 2. Bốn thay đổi phải sửa code

### 2.1. Không còn cột & bộ lọc "Trạng thái" ở màn hồ sơ

`beneficiaries` **không có** cột `status`. Gỡ khỏi màn danh sách hồ sơ và 3 danh sách con:

- Cột "Trạng thái" trong bảng
- Bộ lọc `status`
- Nút đổi trạng thái, nút đổi trạng thái hàng loạt

Endpoint `PATCH /beneficiaries/{id}/status` và `PATCH /beneficiaries/bulk-status` **không tồn
tại**. Muốn ẩn một hồ sơ thì xoá mềm (`DELETE`).

**Màn danh mục thì ngược lại** — có đủ cột, bộ lọc và nút đổi trạng thái.

### 2.2. Loại đối tượng và Mối quan hệ chuyển từ enum sang danh mục DB

v1 đọc hai thứ này từ `/beneficiary-enums`. v2 phải gọi:

- `GET /api/beneficiary-types?status=active`
- `GET /api/beneficiary-relationships?status=active`

`/beneficiary-enums` giờ chỉ còn `gender` và `catalog_status`.

### 2.3. Dropdown danh mục PHẢI truyền `status=active`

Endpoint danh mục **không tự lọc** — màn quản trị cần thấy cả mục `inactive`.

```js
// Dropdown chọn khi nhập hồ sơ
GET /api/beneficiary-types?status=active&limit=-1

// Màn quản trị danh mục — không truyền status
GET /api/beneficiary-types?limit=20
```

Quên `status=active` thì cán bộ chọn được mục đã ngừng dùng và BE trả 422 lúc lưu.

### 2.4. Thân nhân giờ thuộc về một hồ sơ

v1 cho một thân nhân dùng chung nhiều hồ sơ qua bảng nối. v2 là quan hệ 1–n: thân nhân là dòng
con trực thuộc.

Hệ quả cho FE:
- Không còn màn "Danh sách thân nhân" toàn hệ thống — thân nhân chỉ xem trong ngữ cảnh một hồ sơ
- Không còn thao tác "gắn thân nhân có sẵn vào hồ sơ"
- Trùng CCCD thân nhân giữa hai hồ sơ là **hợp lệ**, đừng cảnh báo

---

## 3. Điểm giữ nguyên (không phải sửa)

- **Tệp đính kèm vẫn có URL trực tiếp** — Resource trả `url`, dùng `<img src>` / `<embed>` như
  cũ. Không cần fetch kèm token rồi dựng blob URL.
- Khuôn response `{ success, message, data }` không đổi.
- `created_by` / `updated_by` vẫn là object `{ id, name, avatar }`.
- Format thời gian: chỉ ngày `d/m/Y`, có giờ `H:i:s d/m/Y`.

---

## 4. Hai cơ chế mới FE phải xử lý

### 4.1. Optimistic lock (`lock_version`)

Mọi response của bản chính trả thêm `lock_version` (chuỗi ISO8601). **Giữ nguyên chuỗi, không
format lại** — format `d/m/Y` mất phần giây và sẽ 409 vĩnh viễn.

Khi cập nhật (`update` hoặc `save-full`) phải gửi lại `lock_version` vừa nhận. Nếu người khác đã
sửa trong lúc đó:

```json
{ "success": false, "message": "Bản ghi đã được người khác cập nhật. Vui lòng tải lại trang.",
  "error_code": "STALE_RECORD" }
```

FE hiện dialog yêu cầu tải lại, **không tự retry** — retry sẽ ghi đè mất thay đổi của người kia.

Dòng con trả `parent_lock_version` để màn sub-resource biết bản chính đã đổi chưa.

### 4.2. `save-full` — form trọn gói

```
POST /api/beneficiaries/{id}/save-full   (multipart/form-data)

lock_version, full_name, birth_date, ...

type_relations_json   '[{"id":null,"beneficiary_type_id":3,"is_primary":true,
                         "sync_attachments":true,"keep_media_ids":[]}]'
dependents_json       '[{"id":12,"full_name":"Trần Thị B","relationship_id":2}]'
documents_json        '[{"id":null,"name":"Quyết định trợ cấp",
                         "sync_attachments":true,"keep_media_ids":[7,8]}]'

type_relations_files[0][]
documents_files[0][]
```

**Ba điều bắt buộc nhớ:**

1. **Danh sách con gửi bằng chuỗi JSON**, không phải mảng lồng FormData. `max_input_vars` cắt
   phần đuôi payload mà không báo lỗi — media id bị cắt sẽ bị xoá vĩnh viễn khỏi đĩa.
2. **`"[]"` = xoá hết. Không gửi field = giữ nguyên.** Hai trạng thái khác nhau.
3. **CẤM gọi `save-full` từ màn hình có phân trang.** BE xoá mềm mọi dòng con không có trong
   payload và vẫn trả 200 — backend không chặn được. Màn có phân trang phải dùng sub-resource
   CRUD lẻ.

Quản lý tệp: bỏ `sync_attachments` = giữ nguyên tệp cũ; `sync_attachments: true` +
`keep_media_ids` = giữ đúng những id đó, xoá phần còn lại. Trần tổng tệp mỗi request: **90**.

---

## 5. Hai lỗi 409 mới

| `error_code` | Khi nào | FE làm gì |
|---|---|---|
| `STALE_RECORD` | `lock_version` lệch | Dialog "tải lại trang", không retry |
| `CATALOG_IN_USE` | Xoá mục danh mục đang được dùng | Dialog kèm nút "Chuyển sang Ngừng sử dụng" |

`CATALOG_IN_USE` trả kèm `errors.usage_count` — nên hiện luôn "đang có N bản ghi sử dụng".
Resource danh mục cũng trả `usage_count` ở `index` để disable sẵn nút xoá.

---

## 6. Đường dẫn mới, tóm tắt

```
/api/beneficiaries                                    stats, index, show, store, update,
                                                      destroy, bulk-delete, save-full,
                                                      export, import, import-template
/api/beneficiaries/{id}/type-relations                6 action  (Đối tượng, có tệp)
/api/beneficiaries/{id}/dependents                    6 action  (Thân nhân, không tệp)
/api/beneficiaries/{id}/documents                     6 action  (Tài liệu, có tệp)
/api/beneficiary-residential-areas                    danh mục đầy đủ + reorder + status
/api/beneficiary-types                                danh mục đầy đủ + reorder + status
/api/beneficiary-relationships                        danh mục đầy đủ + reorder + status
/api/beneficiary-enums                                gender, catalog_status
```

Đối tượng và Tài liệu cập nhật bằng `POST` + `_method=PUT` (có tệp). Thân nhân dùng `PUT` thẳng.
