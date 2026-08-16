# API — Module Người có công (v2)

> Ngày tạo: 13:45:00 15/08/2026  
> Cập nhật lần cuối: 16:42:12 16/08/2026 — bổ sung endpoint `dashboard` + permission riêng `beneficiaries.dashboard`

Mọi endpoint nằm trong nhóm `auth:sanctum`. Trừ `/beneficiary-enums`, tất cả đều cần header
`X-Organization-Id` **và** middleware `ensure.route.org`.

Response theo khuôn chung `RespondsWithJson`: `{ "success": bool, "message": string, "data": ... }`.

---

## 1. Hồ sơ người có công — `/api/beneficiaries`

| Method | Route | Permission | Ghi chú |
|---|---|---|---|
| GET | `/stats` | `beneficiaries.stats` | Số liệu nhẹ cho badge đầu màn danh sách |
| GET | `/dashboard` | `beneficiaries.dashboard` | Trang thống kê: 6 KPI, 8 biểu đồ, 3 bảng (quyền RIÊNG, tách khỏi `.stats`) |
| GET | `/` | `beneficiaries.index` | |
| GET | `/{id}` | `beneficiaries.show` | Trả kèm 3 danh sách con |
| POST | `/` | `beneficiaries.store` | Chỉ bản chính |
| PUT hoặc POST + `_method=PUT` | `/{id}` | `beneficiaries.update` | |
| DELETE | `/{id}` | `beneficiaries.destroy` | Xoá mềm |
| DELETE | `/bulk-delete` | `beneficiaries.bulkDestroy` | Body `{"ids":[...]}` |
| POST | `/save-full` | `beneficiaries.store` | Tạo mới trọn gói |
| POST | `/{id}/save-full` | `beneficiaries.update` | Cập nhật trọn gói |
| GET | `/export` | `beneficiaries.export` | |
| POST | `/import` | `beneficiaries.import` | |
| GET | `/import-template` | `beneficiaries.import` | Dùng chung permission |

> **KHÔNG có** `PATCH /{id}/status` và `PATCH /bulk-status` — hồ sơ người có công không có
> trạng thái nghiệp vụ. Muốn ẩn một hồ sơ thì xoá mềm.

### Bộ lọc `index`

`search` (họ tên / CCCD / SĐT) · `residential_area_id` · `beneficiary_type_id` (lọc qua bảng
nối) · `relationship_id` (hồ sơ có thân nhân với quan hệ này) · `gender` · `birth_year_from` ·
`birth_year_to` · `from_date` / `to_date` (theo `created_at`) · `sort_by` ∈ {`id`, `full_name`,
`birth_date`, `birth_year`, `created_at`, `updated_at`} · `sort_order` · `limit` (`-1` = không
phân trang).

### `stats` trả gì

```json
{
  "total": 120,
  "new_in_30_days": 8,
  "with_coordinates": 100,
  "without_coordinates": 20,
  "by_gender":            { "male": 90, "female": 30 },
  "by_residential_area":  { "Tổ dân phố 1": 40 },
  "by_type":              { "Thương binh": 55 }
}
```

Không đếm theo trạng thái. `without_coordinates` là con số cán bộ cần để biết còn bao nhiêu hồ
sơ chưa chấm được lên bản đồ.

`stats` là số liệu **nhẹ** cho badge đầu màn danh sách. Trang thống kê đầy đủ dùng `dashboard`
bên dưới — permission **RIÊNG** `beneficiaries.dashboard`, KHÔNG dùng chung `.stats`.

### `dashboard` trả gì

Phục vụ trang thống kê: gộp trong một request. Nhận filter `from_date`, `to_date`,
`residential_area_id`. Permission **riêng** `beneficiaries.dashboard`.

```jsonc
{
  "kpis": {                        // 6 chỉ số tổng
    "total": 120, "new_in_30_days": 8,
    "total_type_relations": 140, "total_dependents": 210,
    "with_coordinates_percent": 83.3, "incomplete_count": 25
  },
  "charts": {                      // 8 biểu đồ, format [{label, value}] trừ tháp tuổi
    "by_gender": [ { "label": "Nam", "value": 90 } ],
    "by_type": [ { "label": "Thương binh", "value": 55 } ],
    "by_residential_area": [ { "label": "Tổ dân phố 1", "value": 40 } ],   // Top 10 + "Khác"
    "by_age_group": [ { "label": "75-89", "value": 30 } ],                 // gồm rổ "Không rõ"
    "age_gender_pyramid": [ { "age_group": "75-89", "male": 20, "female": 10, "other": 0, "unknown": 0 } ],
    "created_trend_12m": [ { "label": "08/2026", "value": 8 } ],           // 12 mốc, theo created_at
    "dependents_by_relationship": [ { "label": "Con", "value": 80 } ],
    "data_quality": [ { "label": "Đủ toạ độ", "value": 100 } ]             // 4 chỉ số độc lập
  },
  "tables": {                      // 3 bảng tổng hợp
    "area_type_matrix": { "types": ["Thương binh"], "rows": [ { "area": "Tổ dân phố 1", "counts": { "Thương binh": 20 }, "total": 20 } ] },
    "type_summary": [ { "name": "Thương binh", "total": 55, "percent": 45.8 } ],
    "incomplete_profiles": [ { "id": 12, "full_name": "Nguyễn Văn A", "residential_area": "Tổ dân phố 1", "missing": ["Toạ độ"] } ]
  }
}
```

Hướng dẫn FE dựng trang: [../changelogs/2026-08-16-beneficiary-dashboard-fe.md](../changelogs/2026-08-16-beneficiary-dashboard-fe.md).
Biểu đồ `created_trend_12m` đếm theo `created_at` (tiến độ NHẬP LIỆU) và luôn phủ 12 tháng gần
nhất, KHÔNG chịu tác động `from_date`/`to_date`.

### `show` trả gì

```json
{
  "id": 1,
  "full_name": "Nguyễn Văn A",
  "birth_date": "15/03/1950",
  "birth_year": 1950,
  "gender": "male",
  "gender_label": "Nam",
  "id_number": "048050001234",
  "phone": "0905123456",
  "residential_area_id": 3,
  "residential_area": { "id": 3, "name": "Tổ dân phố 5", "status": "active", "...": "..." },
  "address": "12 Trần Phú",
  "latitude": "16.0678000",
  "longitude": "108.2208000",
  "note": null,
  "type_relations": [ { "id": 5, "beneficiary_type_id": 3, "beneficiary_type": {...}, "is_primary": true, "attachments": [...] } ],
  "dependents":     [ { "id": 7, "full_name": "Trần Thị B", "relationship": {...}, "is_primary": true, "...": "..." } ],
  "documents":      [ { "id": 9, "name": "Quyết định trợ cấp", "files": [...] } ],
  "created_at": "10:30:00 15/08/2026",
  "updated_at": "10:35:12 15/08/2026",
  "lock_version": "2026-08-15T10:35:12+07:00"
}
```

**`lock_version` là token khoá lạc quan — giữ nguyên chuỗi ISO8601, KHÔNG format lại.**
`updated_at` là bản hiển thị, hai field khác nhau có chủ đích.

---

## 2. `save-full` — lưu trọn gói

`POST /api/beneficiaries/save-full` hoặc `/api/beneficiaries/{id}/save-full`,
`Content-Type: multipart/form-data`.

### Payload

```
id                          (bỏ trống khi tạo mới — hoặc dùng route không có {id})
lock_version                (BẮT BUỘC khi cập nhật)
full_name, birth_date, birth_year, gender, id_number, phone,
residential_area_id, address, latitude, longitude, note

type_relations_json   '[{"id":null,"beneficiary_type_id":3,"is_primary":true,
                         "sync_attachments":true,"keep_media_ids":[]}]'
dependents_json       '[{"id":12,"full_name":"Trần Thị B","relationship_id":2,"is_primary":true}]'
documents_json        '[{"id":null,"name":"Quyết định trợ cấp",
                         "sync_attachments":true,"keep_media_ids":[7,8]}]'

type_relations_files[0][]   (tệp mới của dòng đối tượng thứ 0)
documents_files[0][]        (tệp mới của dòng tài liệu thứ 0)
```

### Ba quy tắc bắt buộc nhớ

**1. Danh sách con gửi bằng chuỗi JSON, không phải mảng lồng FormData.**
`max_input_vars` (mặc định 1000) cắt phần **đuôi** payload mà không báo lỗi. Phần bị cắt có thể
là vài phần tử cuối của `keep_media_ids[]` — số dòng vẫn khớp, validate vẫn pass, nhưng những
media id bị cắt rơi vào danh sách xoá và **bị xoá vĩnh viễn khỏi đĩa**.

**2. `"[]"` = xoá hết dòng con. KHÔNG gửi field = giữ nguyên.** Hai trạng thái khác nhau.

**3. CẤM gọi `save-full` từ màn hình có phân trang.** `whereNotIn` xoá mềm sạch phần chưa load
và response vẫn 200. Backend không chặn được — màn hình có phân trang phải dùng sub-resource
CRUD lẻ.

### Quản lý tệp đính kèm

| Muốn gì | Gửi gì |
|---|---|
| Không đụng tệp cũ | Bỏ `sync_attachments` |
| Giữ một số, xoá phần còn lại | `sync_attachments: true` + `keep_media_ids: [id...]` |
| Xoá hết tệp cũ | `sync_attachments: true` + `keep_media_ids: []` |
| Thêm tệp mới | `<field>_files[<chỉ số dòng>][]` |

Trần tổng tệp mỗi request: **90**. Vượt sẽ nhận 422 chứ không bị PHP cắt im lặng.

### Lỗi 409

```json
{ "success": false,
  "message": "Bản ghi đã được người khác cập nhật. Vui lòng tải lại trang.",
  "error_code": "STALE_RECORD" }
```

FE phải gọi lại `show` để lấy `lock_version` mới, không được tự retry.

---

## 3. Sub-resource (route lồng)

`beneficiary_id` luôn lấy từ URL, **không** nhận từ body — đó là cơ chế chặn IDOR.
Route dùng `scopeBindings()` nên `{typeRelation}` bắt buộc thuộc về `{beneficiary}`.

| Resource | Prefix | Permission |
|---|---|---|
| Đối tượng | `/api/beneficiaries/{beneficiary}/type-relations` | `beneficiary-type-relations.*` |
| Thân nhân | `/api/beneficiaries/{beneficiary}/dependents` | `beneficiary-dependents.*` |
| Tài liệu | `/api/beneficiaries/{beneficiary}/documents` | `beneficiary-documents.*` |

Mỗi bộ có 6 action: `index`, `show`, `store`, `update`, `destroy`, `bulkDestroy` (`DELETE
/bulk-delete`). Không có `stats` / `export` / `import` / `changeStatus`.

**Đối tượng và Tài liệu cập nhật bằng `POST` + `_method=PUT`** (có tệp — PHP không parse
multipart trên PUT). **Thân nhân dùng `PUT` thẳng** vì không có tệp.

Mọi Resource dòng con trả thêm `parent_lock_version` — dùng nó khi màn sub-resource cần biết
bản chính đã đổi chưa.

### `is_primary`

"Nhiều nhất một", không phải "đúng một". Set `is_primary = true` cho một dòng thì các dòng còn
lại tự về `false`. Cho phép **không có** dòng nào là chính. Xoá dòng đang là chính thì **không**
tự thăng dòng khác lên — cán bộ chọn lại.

---

## 4. Danh mục

| Resource | Prefix |
|---|---|
| Tổ dân phố/Thôn | `/api/beneficiary-residential-areas` |
| Loại đối tượng | `/api/beneficiary-types` |
| Mối quan hệ | `/api/beneficiary-relationships` |

Bộ action mỗi danh mục: `stats`, `index`, `show`, `store`, `update`, `destroy`,
`bulkDestroy` (`DELETE /bulk-delete`), `bulkUpdateStatus` (`PATCH /bulk-status`),
`changeStatus` (`PATCH /{id}/status`), `reorder` (`PATCH /reorder`), `export`, `import`,
`importTemplate`.

### Trường

`name` (bắt buộc, **duy nhất trong tổ chức**) · `note` · `sort_order` · `status`.
**Không có `code`.**

### Trạng thái — điều FE phải nhớ

**Dropdown chọn danh mục phải truyền `status=active`.** Endpoint **không** tự lọc vì màn quản
trị cần thấy cả mục đã ngừng dùng. Quên tham số này thì cán bộ chọn được mục đã ngừng dùng và
BE trả 422 khi lưu.

| | `active` | `inactive` |
|---|---|---|
| Hiện trong dropdown | Có | **Không** |
| Hồ sơ cũ đang tham chiếu | Giữ nguyên | **Giữ nguyên, hiển thị bình thường** |
| Import Excel khớp theo tên | Có | **Không** — để trống, không chặn dòng |

### `reorder`

```
PATCH /api/beneficiary-types/reorder
{ "items": [ {"id": 3, "sort_order": 1}, {"id": 1, "sort_order": 2} ] }
```

Mặc định `index` sắp xếp theo `sort_order ASC, name ASC`.

### Lỗi 409 khi xoá

```json
{ "success": false,
  "message": "Không thể xoá \"Thương binh\" vì đang có 55 bản ghi sử dụng. Nếu chỉ muốn ẩn khỏi danh sách chọn khi nhập hồ sơ mới, hãy chuyển sang trạng thái \"Ngừng sử dụng\".",
  "error_code": "CATALOG_IN_USE",
  "errors": { "name": "Thương binh", "usage_count": 55 } }
```

FE nên hiện nút "Chuyển sang Ngừng sử dụng" ngay trong dialog lỗi này.

Resource danh mục trả `usage_count` khi service gọi `withCount` — dùng để hiện trước cho cán bộ
biết mục nào xoá được.

---

## 5. Enum — `/api/beneficiary-enums`

Không `ensure.route.org`, không `permission:` (vẫn cần header `X-Organization-Id` vì middleware
`set.permissions.team` bắt buộc cho toàn nhóm auth).

```json
{ "success": true, "data": {
  "gender": [ {"value":"male","label":"Nam"}, {"value":"female","label":"Nữ"}, {"value":"other","label":"Khác"} ],
  "catalog_status": [ {"value":"active","label":"Đang sử dụng"}, {"value":"inactive","label":"Ngừng sử dụng"} ]
}}
```

`catalog_status` **chỉ áp cho ba bảng danh mục**. Loại đối tượng và Mối quan hệ không ở đây —
chúng là danh mục DB.

---

## 6. Export / Import

### Export hồ sơ

Cột: ID · Họ và tên · Ngày sinh · Năm sinh · Giới tính · CCCD/CMND · Số điện thoại · Tổ dân
phố/Thôn · Địa chỉ · Vĩ độ · Kinh độ · Ghi chú · **Danh sách loại đối tượng** · **Danh sách
thân nhân** · **Danh sách tài liệu** · Người tạo · Người cập nhật · Ngày tạo · Ngày cập nhật.

Ba cột "Danh sách ..." gộp quan hệ 1–n vào một ô ngăn bởi `'; '`, đánh dấu `(chính)` cho dòng
`is_primary`. Chúng **chỉ để đọc đối chiếu — import bỏ qua**.

### Import hồ sơ

Chỉ **Họ và tên** bắt buộc. Mọi cột khác `nullable`.

- **Tổ dân phố/Thôn nhập bằng TÊN**, tra ngược không phân biệt hoa/thường và bỏ khoảng trắng
  thừa. Không khớp (hoặc mục đã `inactive`) thì để trống, **không chặn dòng**.
- Giới tính nhận cả `male` lẫn `Nam` — round-trip Export → Import chạy được.
- Trùng CCCD với hồ sơ đã xoá mềm → **khôi phục** hồ sơ đó, không phải lỗi.
- Dòng lỗi bị bỏ qua, dòng hợp lệ vẫn nhập. Lỗi trả về ở `data.error_file` (file Excel base64,
  cột STT | Hàng số | Cột | Lỗi | Giá trị).

### Import danh mục

Cột: Tên (bắt buộc) · Ghi chú · Thứ tự · Trạng thái. Nhập lại tên đã có là **cập nhật** mục đó,
không phải lỗi trùng.

---

## 7. Danh sách permission

```
beneficiaries.{stats,dashboard,index,show,store,update,destroy,bulkDestroy,export,import}
beneficiary-type-relations.{index,show,store,update,destroy,bulkDestroy}
beneficiary-dependents.{index,show,store,update,destroy,bulkDestroy}
beneficiary-documents.{index,show,store,update,destroy,bulkDestroy}
beneficiary-residential-areas.{stats,index,show,store,update,destroy,bulkDestroy,bulkUpdateStatus,changeStatus,export,import}
beneficiary-types.{...như trên}
beneficiary-relationships.{...như trên}
```

`save-full` → dùng `beneficiaries.store` / `.update`.
`import-template` → dùng `.import`. `reorder` → dùng `.update`.
Không có permission riêng cho ba action này.
