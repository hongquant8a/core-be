# Hướng dẫn tích hợp Frontend — Module Người có công

> Ngày tạo: 09:52:45 26/07/2026
> Cập nhật lần cuối: 09:52:45 26/07/2026

Hướng dẫn đầy đủ để FE dựng module Người có công theo API hiện tại. Thay thế bản
`module-nguoi-co-cong-huong-dan-frontend_152705_25072026.md` (bản đó viết trước khi có tổ dân phố
riêng và trước khi chuẩn hóa payload `store`/`update`).

**Đọc kèm:** [docs/api/beneficiary.md](../api/beneficiary.md) · [beneficiary-household.md](../api/beneficiary-household.md) · [beneficiary-dependent.md](../api/beneficiary-dependent.md)

---

## 0. Ba điều dễ sai nhất — đọc trước khi code

1. **Mảng con là THAY THẾ TOÀN BỘ, không phải patch.** Gửi `documents: [...]` trong `PUT` là xóa
   sạch tài liệu cũ rồi tạo lại. Không gửi khóa = giữ nguyên. Không có `*_deleted`.
2. **Gửi `documents`/`classifications` là mất file đính kèm** của dòng cũ. Form không sửa hai danh
   sách đó thì **đừng đưa khóa vào payload**.
3. **Quan hệ luôn là ID.** Không nhúng object hộ/thân nhân — tạo trước ở resource của nó rồi truyền
   `household_id` / `residential_area_id` / `dependent_id`.

---

## 1. Bản đồ endpoint

| Màn hình | Endpoint |
|---|---|
| Người có công | `/api/beneficiaries` |
| Hộ gia đình | `/api/beneficiary-households` |
| Thân nhân | `/api/beneficiary-dependents` |
| Tổ dân phố / thôn | `/api/beneficiary-residential-areas` |
| Giấy tờ hồ sơ | `/api/beneficiary-documents` |
| Dashboard thống kê | `/api/beneficiary-statistics` |
| Enum cho dropdown | `/api/beneficiary-enums` |

Mọi request cần `Authorization: Bearer {token}` **và** `X-Organization-Id: {id}`.

Bốn resource đầu đều có đủ: `stats`, `index`, `show`, `store`, `update`, `destroy`, `bulk-delete`,
`export`, `import`, `import-template`. Riêng người có công có thêm `bulk-status` và `{id}/status`
(ba resource kia không có cột `status`).

---

## 2. Nạp enum một lần khi vào module

```js
const { data } = await $api('/api/beneficiary-enums')
// data = {
//   beneficiary_status: [{ value: 'pending', label: 'Chờ công nhận' }, ...],
//   beneficiary_type:   [{ value: 'war_invalid', label: 'Thương binh, ...' }, ...],  // 12 nhóm
//   gender:             [{ value: 'male', label: 'Nam' }, ...],
//   dependent_relationship: [{ value: 'wife', label: 'Vợ' }, ...],   // 11 quan hệ
// }
```

**Không hardcode** value/label. Endpoint này không gắn permission riêng nên user nào cũng gọi được.

`dependent_relationship` có 11 giá trị: `wife` (Vợ), `husband` (Chồng), `child` (Con),
`grandchild` (Cháu), `father` (Cha), `mother` (Mẹ), `older_brother` (Anh), `older_sister` (Chị),
`younger_sibling` (Em), `foster_parent` (Người nuôi dưỡng), `guardian` (Người giám hộ).

> ⚠️ **`spouse` không còn tồn tại** — đã tách thành `wife`/`husband` từ 26/07/2026. FE nào còn
> hardcode danh sách quan hệ (kể cả `spouse`) phải bỏ và chuyển sang đọc từ endpoint enum.

---

## 3. Màn danh sách người có công

```
GET /api/beneficiaries?search=&status=&type=&household_id=&residential_area_id=
    &from_date=&to_date=&sort_by=created_at&sort_order=desc&limit=20
```

**Ô tìm kiếm quét 6 cột:** họ tên / CCCD / SĐT của **người có công** + họ tên / CCCD / SĐT của
**thân nhân liên kết**. Cán bộ gõ một cái tên hoặc một số CCCD bất kỳ là ra hồ sơ liên quan, không
cần biết mảnh thông tin đó thuộc về ai. Placeholder nên ghi rõ điều này, ví dụ:
_"Tìm theo tên, CCCD, SĐT của người có công hoặc thân nhân"_.

**Bộ lọc `type`** = loại đối tượng, lấy value từ enum `beneficiary_type`. Một người kiêm nhiều loại
(vừa thương binh vừa nạn nhân chất độc da cam) sẽ khớp khi lọc theo **bất kỳ** loại nào của họ.

Mỗi dòng trả về:

```json
{
  "id": 1,
  "full_name": "Trần Văn B",
  "date_of_birth": "20/05/1950",
  "birth_year": null,
  "gender": "male", "gender_label": "Nam",
  "id_number": "049123456789",
  "status": "active", "status_label": "Đang hưởng",
  "death_date": null,
  "household_id": 3, "household": { "id": 3, "head_name": "Trần Văn A" },
  "residential_area_id": 5, "residential_area": { "id": 5, "name": "Tổ 5" },
  "address": "...", "phone": "...",
  "dependents_count": 2,
  "documents_count": 5,
  "created_by": { "id": 2, "name": "Cán bộ A" },
  "created_at": "09:00:00 15/01/2026"
}
```

**Lưu ý khi dựng bảng:**

- Ngày đã format sẵn `d/m/Y` (và `H:i:s d/m/Y` cho mốc thời gian) — **đừng parse lại**.
- Dùng `*_label` để hiển thị, dùng `status`/`gender` thô để so sánh và lọc.
- Tuổi: ưu tiên `date_of_birth`, rơi về `birth_year` khi null (nhiều cụ chỉ nhớ năm).
- `dependents_count` / `documents_count` để hiện badge; muốn chi tiết phải vào màn chi tiết.
- **`classifications` KHÔNG có ở danh sách.** Muốn cột "Loại đối tượng" ở bảng thì báo BE bổ sung
  (đang chờ chốt: load toàn bộ `classifications` hay chỉ loại chính).

**Thống kê nhanh phía trên bảng:** `GET /api/beneficiaries/stats` nhận **cùng bộ filter** (kể cả
`search`, `type`, `residential_area_id`), trả `{ total, pending, active, deceased }`. Gọi song song
với `index`, truyền y hệt filter để KPI khớp bảng.

---

## 4. Màn chi tiết

`GET /api/beneficiaries/{id}` trả **một lần đủ dùng** — không cần gọi API phụ:

- `classifications[]` — kèm `type_label` và `decision_files[]` (id, name, url, size)
- `dependents[]` — kèm `relationship_type_label` và object `dependent` lồng (họ tên, ngày sinh, CCCD, SĐT)
- `documents[]` — kèm `files[]` (id, name, url, size)
- `household`, `residential_area`, `created_by`, `updated_by`

Xem cấu trúc JSON đầy đủ ở [docs/api/beneficiary.md §3](../api/beneficiary.md#3-chi-tiết).

### 4.1 Tọa độ bản đồ — dùng `map_*`, không dùng `latitude`/`longitude`

`index` và `show` trả thêm `map_latitude`, `map_longitude`, `map_source`:

| `map_source` | Nghĩa |
|---|---|
| `self` | Tọa độ của chính người có công |
| `primary_dependent` | Người có công **đã mất** → lấy theo **thân nhân chính** |

Người đã khuất thì tọa độ của họ không còn ý nghĩa thực địa, nhưng cán bộ vẫn cần một điểm để đến
thăm viếng / chi trả cho thân nhân. Chưa gán thân nhân chính, hoặc thân nhân chính chưa có tọa độ →
giữ tọa độ gốc.

- **Bản đồ, marker, cụm điểm** → đọc `map_latitude` / `map_longitude`
- **Form nhập / sửa tọa độ** → đọc `latitude` / `longitude` (dữ liệu gốc, không bị ghi đè)

Khi `map_source === 'primary_dependent'`, hiển thị chú thích kiểu _"Vị trí theo thân nhân chính:
Nguyễn Thị B"_ (tên lấy từ `primary_dependent.dependent.full_name`). Không có chú thích thì người
dùng sẽ thắc mắc vì sao một người đã mất lại có vị trí trên bản đồ.

---

## 5. Form tạo hồ sơ

```js
POST /api/beneficiaries
{
  // Thông tin cơ bản — bắt buộc: full_name, gender
  "full_name": "Trần Văn B",
  "gender": "male",
  "date_of_birth": "1950-05-20",   // hoặc dùng birth_year nếu chỉ biết năm
  "birth_year": null,
  "id_number": "049123456789",
  "status": "pending",              // bỏ trống → pending
  "address": "12 Trần Phú, Hải Châu",
  "latitude": 16.0678, "longitude": 108.2208,
  "phone": "0905123456",
  "note": null,

  // Quan hệ = ID
  "household_id": 3,
  "residential_area_id": 5,

  // Ba mảng con
  "classifications": [
    { "type": "war_invalid", "decision_no": "123/QĐ", "decision_date": "2010-05-20",
      "issued_by": "UBND TP Đà Nẵng", "is_primary": true }
  ],
  "dependents": [
    { "dependent_id": 12, "relationship_type": "child", "note": "Con ruột" }
  ],
  "documents": [
    { "name": "Giấy chứng nhận thương binh", "note": "Bản sao" }
  ]
}
```

### 5.1 Chọn hộ gia đình

Autocomplete từ `GET /api/beneficiary-households?search=&limit=20`, hiển thị `head_name` +
`head_id_number`, submit `household_id`.

Muốn tạo hộ mới ngay trong form: mở drawer riêng → `POST /api/beneficiary-households` → lấy `id` từ
response → set vào `household_id`. **Không** gửi object `household` lồng nữa (đã bỏ).

### 5.2 Chọn tổ dân phố

Autocomplete từ `GET /api/beneficiary-residential-areas?search=&limit=20`, submit `residential_area_id`.

**Đây là trường riêng của người có công**, độc lập với tổ dân phố của hộ. Chọn hộ **không** tự điền
tổ dân phố. Gợi ý UX: khi user chọn hộ, prefill `residential_area_id` theo tổ dân phố của hộ đó (đọc
từ response autocomplete) rồi cho sửa lại — đa số trùng, thiểu số ở nơi khác với hộ khẩu.

### 5.3 Loại đối tượng (`classifications`)

Bảng nhập nhiều dòng. Mỗi dòng:

| Trường | Bắt buộc | Ghi chú |
|---|---|---|
| `type` | ✅ | dropdown từ `beneficiary_type` (12 nhóm) |
| `decision_no`, `decision_date`, `issued_by` | ❌ | bổ sung sau khi có đủ giấy tờ |
| `is_primary` | ❌ | radio, **tối đa 1 dòng** trong cả bảng |

Một người có thể vừa là thương binh vừa là nạn nhân chất độc da cam → cho thêm nhiều dòng.
Gửi >1 dòng `is_primary: true` → 422 tại field `classifications`.

### 5.4 Thân nhân (`dependents`) — chọn, không nhập

Đây là **liên kết** thân nhân đã tồn tại, không phải tạo mới:

1. Autocomplete `GET /api/beneficiary-dependents?search=&limit=20` → chọn → lấy `dependent_id`
2. Chọn `relationship_type` từ enum `dependent_relationship`
3. `note` tùy chọn

Thân nhân chưa có trong hệ thống thì tạo trước bằng `POST /api/beneficiary-dependents` (drawer riêng),
rồi liên kết. Component `BeneficiaryDependentLinkDrawer` hiện có tái dùng được.

**Cột "Thân nhân chính" (`is_primary`)** — radio, **tối đa 1 dòng** trong bảng. Gửi 2 dòng cùng
`is_primary: true` → 422 tại field `dependents`.

Đây là đầu mối liên hệ của hồ sơ và là nguồn tọa độ bản đồ khi người có công đã mất (xem §4.1).
Nên nhắc cán bộ gán khi đổi trạng thái sang "Đã mất" mà hồ sơ chưa có thân nhân chính.

### 5.5 Tài liệu (`documents`) — metadata trong JSON, file upload riêng

Payload chỉ nhận `name` (bắt buộc) + `note`. **File không gửi trong JSON được.** Luồng đúng:

```js
// 1. Lưu hồ sơ kèm metadata tài liệu
const res = await $api('/api/beneficiaries', { method: 'POST', body: payload })

// 2. Đọc id từng tài liệu trong response
const docIds = res.data.documents.map(d => d.id)

// 3. Upload file cho từng tài liệu (multipart)
const fd = new FormData()
fd.append('name', 'Giấy chứng nhận')       // hoặc PUT /api/beneficiary-documents/{id}
fd.append('beneficiary_id', res.data.id)
files.forEach(f => fd.append('files[]', f))
await $api('/api/beneficiary-documents', { method: 'POST', body: fd })
```

Mỗi file ≤ 10MB.

### 5.6 File quyết định của loại đối tượng

Tương tự: lưu hồ sơ trước, đọc `classifications[].id`, rồi

```
POST /api/beneficiaries/{beneficiary}/classifications/{classification}/files   // files[] multipart
DELETE /api/beneficiaries/{beneficiary}/classifications/{classification}/files/{media}
```

Dùng chung permission `beneficiaries.update`.

---

## 6. Form sửa hồ sơ — phần quan trọng nhất

`PUT /api/beneficiaries/{id}`. Cột thường cập nhật như bình thường. **Ba mảng con hoạt động khác:**

| Gửi gì | BE làm gì |
|---|---|
| Không có khóa trong payload | Giữ nguyên danh sách |
| `[{...}, {...}]` | **Xóa sạch** danh sách cũ → tạo lại theo mảng gửi lên |
| `[]` | Xóa sạch danh sách đó |

Không có `*_deleted`. Không gửi `id` trong phần tử (gửi → 422).

### 6.1 Quy tắc vàng: chỉ gửi mảng mà form thực sự sửa

```js
const payload = { full_name, gender, household_id, residential_area_id, /* ... */ }

// CHỈ thêm khóa khi tab/section tương ứng có thay đổi
if (classificationsTouched) payload.classifications = classificationRows
if (dependentsTouched)      payload.dependents = dependentRows
if (documentsTouched)       payload.documents = documentRows

await $api(`/api/beneficiaries/${id}`, { method: 'PUT', body: payload })
```

Gửi thừa `documents` khi user chỉ sửa số điện thoại = **xóa toàn bộ file scan** của hồ sơ đó.

### 6.2 Vì sao lại thiết kế vậy

`PUT` idempotent: user bấm đúp nút Lưu, hoặc FE retry khi mạng chập, kết quả vẫn đúng một trạng thái —
không nhân đôi bản ghi. Đổi lại, FE phải kỷ luật về việc khóa nào được đưa vào payload.

### 6.3 File đính kèm khi thay danh sách

Tài liệu và loại đối tượng có file gắn theo `id` của dòng. Thay danh sách → dòng mới `id` mới →
**file cũ mất**. Nếu form cho phép sửa danh sách tài liệu, hãy:

- Cảnh báo user trước khi lưu ("Thay đổi danh sách tài liệu sẽ xóa các tệp đã tải lên"), hoặc
- Tách hẳn: form hồ sơ **không** đụng `documents`, quản lý tài liệu ở tab riêng qua
  `/api/beneficiary-documents` (`POST`/`PUT`/`DELETE` từng cái, có `files_deleted` để xóa lẻ từng file).

**Khuyến nghị: chọn cách thứ hai.** An toàn hơn và đúng với việc tài liệu vốn là resource độc lập.

---

## 7. Phân quyền — ẩn UI trước, đừng để 403 sau

Gửi `documents`/`dependents` trong payload cần **quyền của resource đó**, không phải chỉ
`beneficiaries.update`:

| Payload | Quyền cần |
|---|---|
| `documents` khác rỗng | `beneficiary-documents.store` |
| `documents` khi hồ sơ đang có tài liệu | `beneficiary-documents.destroy` |
| `dependents` khác rỗng | `beneficiary-dependents.storeRelation` |
| `dependents` khi hồ sơ đang có quan hệ | `beneficiary-dependents.destroyRelation` |

Thiếu quyền → **403 toàn request**, không ghi gì cả (kể cả phần thông tin cơ bản đã gõ).

→ **Ẩn hoặc khóa** tab tài liệu / tab thân nhân khi user không đủ quyền, thay vì để họ nhập xong rồi
mất trắng. `classifications` không cần quyền phụ.

---

## 8. Đổi trạng thái

```
PATCH /api/beneficiaries/{id}/status     { "status": "deceased", "death_date": "2026-01-20" }
PATCH /api/beneficiaries/bulk-status     { "ids": [1,2,3], "status": "suspended" }
```

`death_date` **chỉ** set được qua đây (hoặc import) — không nằm trong body `store`/`update`. Khi user
chọn `deceased` thì bắt buộc hiện ô nhập ngày mất.

Năm trạng thái: `pending` (chờ công nhận), `active` (đang hưởng), `deceased` (đã mất),
`moved_out` (chuyển đi), `suspended` (tạm dừng). Không ghi lịch sử đổi trạng thái.

---

## 9. Xuất / Nhập Excel

### Xuất

`GET /api/beneficiaries/export` nhận **cùng bộ query như danh sách** → truyền y hệt filter đang áp
để file khớp bảng người dùng đang xem.

23 cột, trong đó ba cột **Loại đối tượng / Thân nhân / Giấy tờ** là liệt kê tham chiếu (ngăn cách
`; `, riêng Thân nhân dạng `Tên (Quan hệ)`) — chỉ để đọc, import bỏ qua.

### Nhập

```js
const fd = new FormData()
fd.append('file', file)                                  // xlsx/xls/csv, ≤10MB
const res = await $api('/api/beneficiaries/import', { method: 'POST', body: fd })

// res.data = { failed_count, errors: [...], error_file: { name, mime, base64 } | null }
if (res.data.error_file) {
  // Tải file Excel tổng hợp lỗi cho cán bộ đối chiếu
  downloadBase64(res.data.error_file.base64, res.data.error_file.name, res.data.error_file.mime)
}
```

Dòng lỗi bị bỏ qua, **dòng hợp lệ vẫn được nhập** — nên đừng gọi import lại toàn bộ file, chỉ nhập
lại các dòng đã sửa.

Luôn kèm nút **"Tải file mẫu"** → `GET /api/beneficiaries/import-template`. File mẫu có sẵn dấu `*`
ở cột bắt buộc, dropdown chọn nhanh cho cột enum, và hàng ví dụ in nghiêng (nhắc cán bộ xóa).

Cột bắt buộc: **Họ tên, Giới tính**. Quan hệ nhập bằng tên/mã ("CCCD chủ hộ", "Tổ dân phố"), không
khớp thì để trống chứ không chặn dòng. Giới tính/Trạng thái nhận cả `male`/`pending` lẫn
`Nam`/`Chờ công nhận`.

Bốn resource đều có bộ export/import/import-template giống hệt nhau — FE viết một component dùng chung.

---

## 10. Dashboard thống kê

`GET /api/beneficiary-statistics/overview` trả một lần đủ:

| Khối | Dùng cho |
|---|---|
| `summary` | KPI cards (`total_beneficiaries`, `active_beneficiaries`, `total_dependents`, `total_households`, `total_residential_areas`) |
| `by_type`, `by_status`, `by_gender`, `by_age_group`, `by_relationship` | pie / bar |
| `by_residential_area`, `households_by_area` | bar theo địa bàn |
| `new_by_month` | line (nhận `?year=`) |

Mỗi breakdown là mảng `{ key, label, total }` — dựng chart thẳng, không phải map lại.

Có endpoint lẻ cho từng khối (`/by-type`, `/by-status`, …) nếu cần refresh riêng.

**`by_residential_area` đọc `beneficiaries.residential_area_id`** (trường riêng của người có công),
không suy qua hộ. Click vào một cột → mở danh sách bằng
`GET /api/beneficiaries?residential_area_id={key}`. Phần tử có `key: null` là nhóm "Chưa gán tổ dân phố".

---

## 11. Checklist chuyển đổi từ bản cũ

- [ ] Bỏ luồng tạo hộ lồng trong form người có công → chọn `household_id` hoặc mở drawer tạo hộ riêng
- [ ] Đổi editor thân nhân: **chọn** thân nhân có sẵn + quan hệ, thay vì nhập thông tin thân nhân
- [ ] Bỏ mọi tham chiếu tới `*_deleted` — không còn tồn tại
- [ ] Bỏ `id` khỏi phần tử của 3 mảng con
- [ ] Chỉ đưa khóa `classifications`/`dependents`/`documents` vào payload khi form thực sự sửa
- [ ] Thêm select tổ dân phố vào form + cột "Tổ dân phố" ở bảng + filter `residential_area_id`
- [ ] Đọc `data.dependents[]` ở màn chi tiết thay vì gọi API phụ
- [ ] Sửa đường dẫn tài liệu: `/api/beneficiary-documents?beneficiary_id=` (route lồng
      `/api/beneficiaries/{id}/documents` **không tồn tại**)
- [ ] Ẩn tab tài liệu / thân nhân khi thiếu permission tương ứng
- [ ] Xử lý `error_file` base64 sau khi import

---

## 12. Những endpoint FE hay gọi nhầm

| FE từng gọi | Thực tế |
|---|---|
| `GET /api/beneficiaries/{id}/documents` | Không tồn tại → dùng `GET /api/beneficiary-documents?beneficiary_id={id}` hoặc đọc `data.documents[]` ở chi tiết |
| `POST /api/beneficiaries/{id}/documents` | Không tồn tại → `POST /api/beneficiary-documents` (multipart, có `beneficiary_id`) |
| `GET /api/beneficiary-dependents?beneficiary_id=` | Filter này **không có** → đọc `data.dependents[]` ở `GET /api/beneficiaries/{id}` |
| Trường `eligible_from` trên pivot quan hệ | Không tồn tại — pivot chỉ có `relationship_type` + `note` |
| `relationship_type: 'spouse'` | Đã tách thành `wife` / `husband` → 422 nếu vẫn gửi `spouse` |
| Cột `code` của tổ dân phố | Không tồn tại — bảng chỉ có `name` + `note` |
