# Hướng dẫn Frontend — Module Người có công (theo API)

> Ngày tạo: 15:27:05 25/07/2026
> Cập nhật lần cuối: 15:27:05 25/07/2026

Tài liệu mô tả **danh sách màn hình** và **cách gọi API** để FE dựng module Người có công (bản đơn giản hóa: hồ sơ cơ bản + giấy tờ + dashboard). Bám theo tài liệu Scribe (`/docs`) và [docs/database/Beneficiary.md](../database/Beneficiary.md), [docs/modules/Beneficiary/README.md](../modules/Beneficiary/README.md).

---

## 0. Quy ước chung (đọc trước)

### 0.1 Base & xác thực
- Base URL: `/api`.
- **Auth**: Sanctum Bearer token — mọi request gắn header `Authorization: Bearer <token>`.
- **Tenant (bắt buộc)**: mọi endpoint gắn header **`X-Organization-Id: <id phường/xã>`**. Thiếu header này → 422. Dữ liệu tự động scope theo tổ chức, FE không truyền `organization_id` trong body.

### 0.2 Định dạng response
Mọi response bọc chuẩn:
```json
{ "success": true, "message": "…", "data": { … } }
```
Lỗi:
```json
{ "success": false, "message": "…", "errors": { "field": ["…"] } }   // 422 validation
```
FE luôn đọc `data` (không phải root). Danh sách có phân trang trả `data` là mảng + `meta`/`links` (Laravel paginator).

### 0.3 Bộ lọc & phân trang (áp cho mọi `index`)
Query params chung: `search`, `from_date`, `to_date` (Y-m-d), `sort_by`, `sort_order` (`asc|desc`), `limit` (số/trang). Riêng từng resource có thêm filter (ghi ở mục tương ứng).

### 0.4 Ngày giờ
Response format sẵn: ngày `dd/MM/yyyy`, giờ `HH:mm:ss dd/MM/yyyy`. Request gửi `Y-m-d` cho ngày.

### 0.5 Enum cho dropdown
Gọi **1 lần** khi vào module, cache lại:
```
GET /api/beneficiary-enums
→ data: { beneficiary_type[], beneficiary_status[], gender[], dependent_relationship[] }
    mỗi phần tử: { value, label }
```
Dùng để render dropdown giới tính, loại đối tượng (12 nhóm), trạng thái, loại quan hệ. **Không hardcode** value/label.

### 0.6 Upload file
File (giấy tờ, ảnh quyết định) gửi bằng **`multipart/form-data`**, KHÔNG nhét trong JSON. Trường mảng file đặt tên `files[]`. Xem mục Giấy tờ & Chi tiết NCC.

### 0.7 Permission (ẩn/hiện nút theo quyền)
`{resource}.{action}` — vd `beneficiaries.store`, `beneficiaries.import`, `beneficiary-documents.destroy`, `beneficiary-statistics.view`. FE ẩn nút nếu user không có quyền (backend vẫn chặn 403).

---

## 1. Bản đồ màn hình

| # | Màn hình | Route FE gợi ý | API chính |
|---|---|---|---|
| 1 | **Dashboard thống kê** | `/nguoi-co-cong` | `beneficiary-statistics/overview` |
| 2 | Danh sách Người có công | `/nguoi-co-cong/ho-so` | `GET beneficiaries` |
| 3 | Tạo / Sửa Người có công | `/nguoi-co-cong/ho-so/{id}` | `POST/PUT beneficiaries` |
| 4 | Chi tiết Người có công | `/nguoi-co-cong/ho-so/{id}/xem` | `GET beneficiaries/{id}` (+ file quyết định, giấy tờ) |
| 5 | Hộ gia đình | `/nguoi-co-cong/ho-gia-dinh` | `beneficiary-households` |
| 6 | Tổ dân phố / Thôn | `/nguoi-co-cong/to-dan-pho` | `beneficiary-residential-areas` |
| 7 | Thân nhân | `/nguoi-co-cong/than-nhan` | `beneficiary-dependents` (+ relations) |
| 8 | Giấy tờ hồ sơ | tab trong Chi tiết NCC hoặc `/nguoi-co-cong/giay-to` | `beneficiary-documents` |
| — | Import / Export | modal/nút trong các màn danh sách | `.../import`, `.../export`, `.../import-template` |

---

## 2. Màn hình Dashboard thống kê

**Mục đích**: KPI cards + các biểu đồ. Load 1 lần bằng `overview` (gộp tất cả), hoặc gọi từng endpoint khi cần refresh 1 chart.

```
GET /api/beneficiary-statistics/overview?year=2026
```
Response `data`:
```json
{
  "summary": {
    "total_beneficiaries": 114, "active_beneficiaries": 70, "pending_beneficiaries": 10,
    "deceased_beneficiaries": 20, "suspended_beneficiaries": 8, "moved_out_beneficiaries": 6,
    "total_dependents": 109, "total_households": 110, "total_residential_areas": 105, "total_documents": 105
  },
  "by_type":            [ { "key": "war_invalid", "label": "Thương binh…", "total": 18 }, … ],   // 12 nhóm
  "by_status":          [ { "key": "active", "label": "Đang hưởng", "total": 70 }, … ],
  "by_residential_area":[ { "key": 1, "label": "Tổ 1", "total": 6 }, …, { "key": null, "label": "Chưa gán tổ dân phố", "total": 3 } ],
  "by_gender":          [ { "key": "male", "label": "Nam", "total": 80 }, … ],
  "by_age_group":       [ { "key": "under_60", "label": "Dưới 60", "total": 5 }, { "key": "60_69", … }, … ],
  "by_relationship":    [ { "key": "child", "label": "Con", "total": 30 }, … ],
  "new_by_month":       { "year": 2026, "data": [ { "key": 1, "label": "Tháng 1", "total": 8 }, … 12 phần tử ] }
}
```

**Gợi ý biểu đồ** — mọi breakdown là mảng `{ key, label, total }`, map thẳng vào chart lib:
| Dữ liệu | Loại chart |
|---|---|
| `summary.*` | KPI cards (số lớn) |
| `by_type` | Bar ngang (nhãn dài) hoặc Donut |
| `by_status`, `by_gender` | Pie / Donut |
| `by_residential_area`, `by_age_group`, `by_relationship` | Bar dọc |
| `new_by_month.data` | Line / Area (trục X = `label`, Y = `total`) |

Endpoint lẻ (refresh 1 chart, cùng permission `beneficiary-statistics.view`):
`by-type`, `by-status`, `by-residential-area`, `households-by-area`, `by-gender`, `by-age-group`, `by-relationship`, `new-by-month?year=`. Response `data` = mảng breakdown tương ứng (riêng `new-by-month` = `{ year, data }`).

---

## 3. Màn hình Danh sách Người có công

```
GET /api/beneficiaries?search=&status=&household_id=&from_date=&to_date=&sort_by=created_at&sort_order=desc&limit=10&page=1
```
- **Filter**: `search` (họ tên/CCCD), `status` (dropdown `beneficiary_status`), `household_id`.
- **sort_by** hợp lệ: `id, full_name, date_of_birth, status, created_at, updated_at`.

Mỗi item (`BeneficiaryResource`):
```json
{
  "id": 1, "household_id": 3,
  "household": { "id": 3, "head_name": "Nguyễn Văn A" },   // khi eager-load
  "full_name": "Trần Văn B", "date_of_birth": "20/05/1950", "birth_year": "1950",
  "gender": "male", "gender_label": "Nam",
  "id_number": "049…", "status": "active", "status_label": "Đang hưởng",
  "death_date": null, "address": "…", "latitude": 16.06, "longitude": 108.22, "phone": "…", "note": "…",
  "dependents_count": 2, "documents_count": 3,
  "created_by": { … }, "updated_by": { … }, "created_at": "…", "updated_at": "…"
}
```

**Card thống kê nhanh trên đầu danh sách**:
```
GET /api/beneficiaries/stats?search=&status=   → data: { total, pending, active, deceased }
```

**Thao tác hàng loạt**:
```
DELETE /api/beneficiaries/bulk-delete     body: { "ids": [1,2,3] }
PATCH  /api/beneficiaries/bulk-status      body: { "ids": [1,2,3], "status": "suspended" }
```

**Đổi trạng thái đơn** (nút trong dòng / chi tiết):
```
PATCH /api/beneficiaries/{id}/status   body: { "status": "deceased", "death_date": "2026-07-25" }
```
> `death_date` bắt buộc khi `status = deceased`. **Không còn** trường `reason`.

---

## 4. Màn hình Tạo / Sửa Người có công

### 4.1 Tạo — `POST /api/beneficiaries`
Body (JSON):
```json
{
  "full_name": "Trần Văn B",          // bắt buộc
  "gender": "male",                    // bắt buộc (enum gender)
  "date_of_birth": "1950-05-20",       // hoặc dùng birth_year khi chỉ nhớ năm
  "birth_year": "1950",
  "id_number": "049123456789",
  "status": "pending",                 // mặc định pending nếu bỏ trống
  "household_id": 3,                   // chọn hộ có sẵn
  "address": "…", "latitude": 16.06, "longitude": 108.22, "phone": "…", "note": "…",

  "classifications": [                 // 1 người nhiều loại; chỉ `type` bắt buộc
    { "type": "war_invalid", "decision_no": "QD-1", "decision_date": "2020-01-01", "issued_by": "Sở LĐTBXH", "is_primary": true }
  ],

  "household": { "head_name": "…", "address": "…" },   // (tùy chọn) TẠO HỘ MỚI — loại trừ với household_id
  "dependents": [                                       // (tùy chọn) tạo kèm thân nhân + quan hệ
    { "full_name": "Lê Thị C", "gender": "female", "relationship_type": "child" }
  ]
}
```
**Lưu ý form**:
- `classifications` là mảng động (thêm/bớt dòng). Chỉ được **tối đa 1** dòng `is_primary = true` (nếu >1 → 422 field `classifications`).
- `household` và `household_id` **loại trừ nhau** — chọn hộ có sẵn HOẶC nhập hộ mới, không cả hai.
- `household`/`dependents` chỉ dùng ở **màn Tạo** (lối tắt hồ sơ mới); màn Sửa không có.

### 4.2 Sửa — `PUT /api/beneficiaries/{id}`
- Không có `household`/`dependents`.
- **Đồng bộ classifications kiểu coarse**: dòng có `id` → cập nhật; không `id` → tạo mới; dòng **vắng mặt vẫn giữ nguyên**; muốn xóa phải liệt kê id vào `classifications_deleted`:
```json
{ "classifications": [ { "id": 5, "type": "war_invalid", "is_primary": true }, { "type": "disease_invalid" } ],
  "classifications_deleted": [7] }
```

### 4.3 Đính kèm FILE quyết định cho từng phân loại (multipart, riêng)
File KHÔNG đi trong JSON. Sau khi có classification (`id`), upload:
```
POST /api/beneficiaries/{beneficiary}/classifications/{classification}/files
Content-Type: multipart/form-data
Body: files[] = <File>, files[] = <File>          // nhiều file, mỗi file ≤ 10MB
→ data: BeneficiaryClassificationResource (kèm decision_files[])
```
Xóa 1 file:
```
DELETE /api/beneficiaries/{beneficiary}/classifications/{classification}/files/{media}
```
`decision_files` trong response: `[{ id, name, url, size }]`.

Ví dụ (axios):
```js
const fd = new FormData();
files.forEach(f => fd.append('files[]', f));
await api.post(`/beneficiaries/${bId}/classifications/${cId}/files`, fd,
  { headers: { 'X-Organization-Id': orgId } }); // axios tự set multipart boundary
```

---

## 5. Màn hình Chi tiết Người có công

```
GET /api/beneficiaries/{id}
```
Response gồm thông tin cơ bản + `classifications[]` (mỗi cái có `decision_files[]`) + `documents[]` (mỗi cái có `files[]`) + counts. Bố cục gợi ý: tab **Thông tin** · **Phân loại & Quyết định** (upload file mục 4.3) · **Thân nhân** (mục 7) · **Giấy tờ hồ sơ** (mục 8).

---

## 6. Màn hình Hộ gia đình

```
GET   /api/beneficiary-households?search=&residential_area_id=&sort_by=&…
GET   /api/beneficiary-households/stats     → { total, total_members }
GET   /api/beneficiary-households/{id}
POST  /api/beneficiary-households
PUT   /api/beneficiary-households/{id}
DELETE/api/beneficiary-households/{id}
DELETE/api/beneficiary-households/bulk-delete   { ids: [] }
```
Body tạo/sửa:
```json
{ "head_name": "Nguyễn Văn A",          // bắt buộc
  "head_id_number": "049…",             // CCCD chủ hộ, unique/tổ chức
  "residential_area_id": 1, "address": "…", "latitude": 16.06, "longitude": 108.22, "phone": "…", "note": "…",
  "beneficiary_ids": [1,2], "dependent_ids": [3] }   // (tùy chọn, chỉ khi tạo) gán thành viên ngay
```
- **Không còn** `household_code`. Tìm kiếm theo tên chủ hộ / CCCD chủ hộ.
- `member_count` là số tự động (chỉ đọc).
- `residential_area_id` chọn từ dropdown Tổ dân phố (mục 6b).

## 6b. Màn hình Tổ dân phố / Thôn

```
GET /api/beneficiary-residential-areas?search=&…      (item: { id, name, note, household_count })
GET /api/beneficiary-residential-areas/stats           → { total, total_households }
POST/PUT/DELETE + bulk-delete tương tự.
```
Body: `{ "name": "Tổ 5", "note": "…" }` (chỉ `name` bắt buộc). Dùng làm nguồn dropdown cho Hộ & Thân nhân (gọi `?limit=1000` hoặc endpoint list).

---

## 7. Màn hình Thân nhân

```
GET   /api/beneficiary-dependents?search=&household_id=&residential_area_id=&sort_by=&…
GET   /api/beneficiary-dependents/stats     → { total, linked }
GET   /api/beneficiary-dependents/{id}
POST/PUT/DELETE + bulk-delete
```
Body tạo/sửa:
```json
{ "full_name": "Lê Thị C", "gender": "female",     // bắt buộc
  "date_of_birth": "2010-03-01", "id_number": "…",
  "household_id": 3, "residential_area_id": 1,
  "phone": "…", "latitude": 16.06, "longitude": 108.22, "note": "…" }
```
Item response có `household{id,head_name}`, `residential_area{id,name}`, `relations[]`.

**Quan hệ với Người có công** (N-N):
```
POST   /api/beneficiary-dependents/{id}/relations        { "beneficiary_id": 1, "relationship_type": "child", "note": "" }
DELETE /api/beneficiary-dependents/{id}/relations/{relationId}
```
> Pivot **chỉ còn** `relationship_type` + `note` (bỏ eligible/status). `relationship_type` từ dropdown `dependent_relationship`.

---

## 8. Màn hình Giấy tờ hồ sơ

Mỗi bản ghi = **1 Tên giấy tờ + nhiều tập tin**. Dùng multipart.
```
GET    /api/beneficiary-documents?search=&beneficiary_id=&sort_by=&…
GET    /api/beneficiary-documents/{id}
POST   /api/beneficiary-documents          (multipart)
PUT    /api/beneficiary-documents/{id}     (multipart)
DELETE /api/beneficiary-documents/{id}
DELETE /api/beneficiary-documents/bulk-delete   { ids: [] }
```
**Tạo** (multipart): `beneficiary_id` (bắt buộc), `name` (bắt buộc), `files[]` (nhiều file ≤10MB, tùy chọn).
**Sửa** (multipart): `name`, thêm `files[]`, và **xóa file cũ** qua `files_deleted[]` (mảng media id).
Item response:
```json
{ "id": 1, "beneficiary_id": 1, "name": "Giấy chứng nhận thương binh", "note": null,
  "files": [ { "id": 10, "name": "gcn.pdf", "url": "https://…", "size": 12345 } ] }
```
Ví dụ tạo (axios):
```js
const fd = new FormData();
fd.append('beneficiary_id', bId);
fd.append('name', 'Giấy chứng nhận thương binh');
files.forEach(f => fd.append('files[]', f));
await api.post('/beneficiary-documents', fd, { headers: { 'X-Organization-Id': orgId } });
```
> Resource này **không có** export/import (đính kèm file không phù hợp file phẳng).

---

## 9. Luồng Export (tải Excel)

```
GET /api/beneficiaries/export?<cùng filter index>
GET /api/beneficiary-households/export
GET /api/beneficiary-residential-areas/export
GET /api/beneficiary-dependents/export
```
Response là **file nhị phân** (.xlsx). FE gọi với `responseType: 'blob'` rồi tạo link tải:
```js
const res = await api.get('/beneficiaries/export', { params: filters, responseType: 'blob',
  headers: { 'X-Organization-Id': orgId } });
const url = URL.createObjectURL(res.data);
const a = Object.assign(document.createElement('a'), { href: url, download: 'nguoi-co-cong.xlsx' });
a.click(); URL.revokeObjectURL(url);
```
> File export có thêm cột liệt kê quan hệ (Thân nhân, Loại đối tượng, Giấy tờ… ngăn cách `; `) — chỉ để đọc.

---

## 10. Luồng Import (nhập Excel) + file lỗi

### 10.1 Tải file mẫu trước
```
GET /api/beneficiaries/import-template            (blob .xlsx — có dropdown/ghi chú giá trị enum, dấu * cột bắt buộc)
GET /api/beneficiary-households/import-template
GET /api/beneficiary-residential-areas/import-template
GET /api/beneficiary-dependents/import-template
```
Tải như mục 9 (responseType blob).

### 10.2 Import
```
POST /api/beneficiaries/import   (multipart)   Body: file = <File .xlsx/.xls/.csv, ≤10MB>
```
Response (JSON — **các dòng hợp lệ vẫn được nhập**, chỉ bỏ qua dòng lỗi):
```json
{ "success": true,
  "message": "Import người có công hoàn tất — đã bỏ qua 2 dòng lỗi, …",
  "data": {
    "failed_count": 2,
    "errors": [
      { "row": 3, "column": "Giới tính", "errors": ["Giới tính không được để trống."], "values": { "Họ tên": "…" } }
    ],
    "error_file": {                       // null khi failed_count = 0
      "name": "loi-import-nguoi-co-cong.xlsx",
      "mime": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      "base64": "UEsDBBQ…"
    }
  }
}
```
**FE xử lý lỗi**:
- Nếu `failed_count > 0`: hiện bảng lỗi từ `errors` (cột Hàng số / Cột / Lỗi), **và** nút "Tải file lỗi" từ `error_file` (file Excel: STT | Hàng số | Cột | Lỗi | Giá trị).
- Decode base64 → download:
```js
function downloadBase64(file) {
  const bytes = Uint8Array.from(atob(file.base64), c => c.charCodeAt(0));
  const url = URL.createObjectURL(new Blob([bytes], { type: file.mime }));
  const a = Object.assign(document.createElement('a'), { href: url, download: file.name });
  a.click(); URL.revokeObjectURL(url);
}
if (res.data.data.error_file) downloadBase64(res.data.data.error_file);
```
> Import chỉ gọi **1 lần**/file (dòng hợp lệ đã nhập). Import liên kết danh mục bằng **tên**: cột "CCCD chủ hộ" (tra hộ), "Tổ dân phố" (tra theo tên). Ràng buộc tối thiểu — chỉ vài cột chính bắt buộc (đánh dấu `*` trong file mẫu).

---

## 11. Bảng trường Request (tóm tắt bắt buộc)

| Resource | Bắt buộc | Tùy chọn chính |
|---|---|---|
| Tổ dân phố | `name` | `note` |
| Hộ gia đình | `head_name` | `head_id_number`, `residential_area_id`, `address`, `latitude`, `longitude`, `phone`, `note` |
| Người có công | `full_name`, `gender` | `date_of_birth`/`birth_year`, `id_number`, `status`, `household_id`, `address`, tọa độ, `phone`, `note`, `classifications[]` |
| Phân loại | `type` | `decision_no`, `decision_date`, `issued_by`, `is_primary`, file `decision_documents` |
| Thân nhân | `full_name`, `gender` | `household_id`, `residential_area_id`, `id_number`, `phone`, tọa độ, `note` |
| Quan hệ | `beneficiary_id`, `relationship_type` | `note` |
| Giấy tờ | `beneficiary_id`, `name` | `note`, `files[]` |

---

## 12. Checklist tích hợp FE

- [ ] Gắn `Authorization` + `X-Organization-Id` cho **mọi** request (interceptor).
- [ ] Cache `beneficiary-enums` khi vào module; render mọi dropdown từ đó.
- [ ] Danh sách: phân trang + filter + `stats` card + bulk actions + export/import.
- [ ] Form NCC: classifications động (1 primary), household xor household_id, chỉ Tạo mới có `household`/`dependents`.
- [ ] Upload file: multipart `files[]`; hiển thị `url` để xem/tải; xóa qua endpoint/`files_deleted`.
- [ ] Import: tải template → upload → hiện lỗi inline + nút tải `error_file` (decode base64).
- [ ] Export/template: `responseType: blob`.
- [ ] Dashboard: overview → KPI cards + charts từ mảng `{key,label,total}`.
- [ ] Ẩn/hiện nút theo permission `{resource}.{action}`.
- [ ] Ngày: gửi `Y-m-d`, hiển thị theo format server trả.

---

*Tham chiếu API đầy đủ (request/response mẫu, thử trực tiếp): trang Scribe `/docs`. Thay đổi so với bản cũ: [docs/changelogs/2026-07-25-beneficiary-simplify-fe.md](../changelogs/2026-07-25-beneficiary-simplify-fe.md).*
