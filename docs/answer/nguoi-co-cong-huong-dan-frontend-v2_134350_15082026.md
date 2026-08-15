# Hướng dẫn Frontend triển khai module Người có công (v2)

> Ngày tạo: 13:43:50 15/08/2026  
> Cập nhật lần cuối: 13:43:50 15/08/2026

**Trạng thái BE:** đã xong, đã test (35 test pass), đã sinh Scribe. FE chưa đụng đến — toàn bộ
`core-fe/src/modules/nguoicocong/` đang gọi API v1 đã bị xoá và sẽ nhận 404.

**Đọc kèm:**
[api/beneficiary.md](../api/beneficiary.md) (chi tiết endpoint) ·
[changelogs/2026-08-15-beneficiary-v2-fe.md](../changelogs/2026-08-15-beneficiary-v2-fe.md) (tóm tắt breaking change) ·
[modules/Beneficiary/README.md](../modules/Beneficiary/README.md) (vì sao BE thiết kế như vậy)

---

## 1. Việc phải làm, tóm tắt trong một bảng

| Thư mục FE hiện tại | Làm gì |
|---|---|
| `beneficiary/` | **Viết lại** — model đổi hoàn toàn |
| `dependent/` | **Xoá** — thân nhân giờ là dòng con, không có màn riêng toàn hệ thống |
| `document/` | **Xoá** — tài liệu giờ là dòng con |
| `household/` | **Xoá** — bỏ khái niệm hộ gia đình |
| `residential-area/` | **Viết lại** theo khuôn danh mục mới (có `sort_order`, bỏ `code`) |
| `subsidy-grant/`, `subsidy-policy/`, `visit-schedule/` | **Xoá** — ngoài phạm vi v2 |
| `beneficiary-report/` | **Xoá** — BE không còn endpoint báo cáo |
| `map/`, `map-studio/`, `poi-category/`, `map-import-log/` | **Giữ nguyên** — chỉ đổi nguồn toạ độ |
| `_mock/` | **Xoá handler** của các resource đã bỏ |
| — | **Dựng mới**: `beneficiary-type/`, `relationship/` (2 danh mục mới) |

Ba màn hình mới cần có:

1. **Danh sách hồ sơ** — bảng có phân trang, bộ lọc, xuất/nhập Excel
2. **Form hồ sơ trọn gói** — một trang nhập đủ thông tin cá nhân + 3 danh sách con
3. **Ba màn quản trị danh mục** — cùng một khuôn, khác endpoint

---

## 2. CASL subject — đã xác minh, không còn phải đoán

File `configs/permissions.js` hiện tại ghi *"⚠️ đang là suy đoán theo quy ước các module khác…
Chưa xác minh với BE"*. Nay đã đối chiếu mã BE (`Auth\Services\CaslAbilityConverter`):

```php
protected static function resourceToSubject(string $resource): string
{
    return collect(explode('-', $resource))
        ->map(fn (string $part) => ucfirst(strtolower($part)))
        ->implode('');
}
```

Tách theo dấu `-`, viết hoa chữ đầu từng phần, nối lại. **Suy đoán cũ là đúng.** Bộ subject của
v2:

```js
// src/modules/nguoicocong/configs/permissions.js
export const SUBJECTS = {
  BENEFICIARY:       'Beneficiaries',                  // /api/beneficiaries
  TYPE_RELATION:     'BeneficiaryTypeRelations',       // .../type-relations
  DEPENDENT:         'BeneficiaryDependents',          // .../dependents
  DOCUMENT:          'BeneficiaryDocuments',           // .../documents
  RESIDENTIAL_AREA:  'BeneficiaryResidentialAreas',    // /api/beneficiary-residential-areas
  BENEFICIARY_TYPE:  'BeneficiaryTypes',               // /api/beneficiary-types
  RELATIONSHIP:      'BeneficiaryRelationships',       // /api/beneficiary-relationships
}
```

**Gỡ khỏi file:** `HOUSEHOLD`, `SUBSIDY_POLICY`, `SUBSIDY_GRANT`, `VISIT_SCHEDULE` — BE không
còn permission tương ứng, giữ lại chỉ gây nhầm.

`buildPermissions()` giữ nguyên nhưng **bỏ hai action cho hồ sơ**: `beneficiaries` không có
`changeStatus` / `bulkUpdateStatus`. Nếu template chung sinh sẵn hai key đó, đừng dùng chúng để
gate nút — nút sẽ hiện rồi bấm vào nhận 404.

---

## 3. Cấu trúc thư mục đề xuất

```
src/modules/nguoicocong/
├── configs/
│   ├── permissions.js            ← SUBJECTS mới (§2)
│   └── datePicker.js             ← giữ
├── router/navigation.js          ← gỡ mục đã xoá, thêm 2 danh mục mới
├── beneficiary/                  ← VIẾT LẠI
│   ├── configs/index.js
│   ├── router/routes.js
│   ├── services/BeneficiaryService.js
│   ├── stores/useBeneficiaryStore.js
│   ├── views/
│   │   ├── BeneficiaryList.vue
│   │   └── BeneficiaryForm.vue           ← form trọn gói
│   └── components/
│       ├── TypeRelationEditor.vue        ← danh sách Đối tượng (có tệp)
│       ├── DependentEditor.vue           ← danh sách Thân nhân
│       └── DocumentEditor.vue            ← danh sách Tài liệu (có tệp)
├── catalog/                      ← DỰNG MỚI — dùng chung cho cả 3 danh mục
│   ├── services/CatalogService.js
│   ├── stores/useCatalogStore.js
│   ├── views/CatalogList.vue
│   └── configs/catalogs.js       ← 3 định nghĩa: endpoint + subject + nhãn
└── map/, map-studio/, poi-category/       ← giữ nguyên
```

**Ba danh mục dùng chung một bộ màn hình.** Chúng giống hệt nhau (`name`, `note`, `sort_order`,
`status`), khác đúng endpoint + subject + nhãn — đúng như BE cũng dùng chung một
`BeneficiaryCatalogService`. Viết ba bộ giống nhau chỉ tạo ba chỗ phải sửa khi đổi.

---

## 4. Tầng service

### 4.1. Hồ sơ

```js
// beneficiary/services/BeneficiaryService.js
import { ApiService } from '@/services/api-service'
import { $api } from '@/utils/api'

const API_BASE = '/api/beneficiaries'

class BeneficiaryService extends ApiService {
  constructor() {
    super(API_BASE)
  }

  /**
   * Lưu trọn gói. Danh sách con gửi dưới dạng CHUỖI JSON, tệp gửi phẳng theo chỉ số dòng.
   * Xem §6 để hiểu vì sao không gửi mảng lồng.
   */
  async saveFull(id, payload, files = {}) {
    const form = new FormData()

    // --- Trường phẳng của bản chính ---
    for (const [key, value] of Object.entries(payload.fields ?? {})) {
      if (value !== undefined && value !== null && value !== '')
        form.append(key, value)
    }

    if (id) form.append('lock_version', payload.lockVersion)

    // --- Ba danh sách con: JSON.stringify, KHÔNG phải mảng lồng ---
    // Gửi "[]" = xoá hết. KHÔNG append key = giữ nguyên. Hai trạng thái khác nhau.
    for (const key of ['type_relations', 'dependents', 'documents']) {
      if (payload[key] !== undefined)
        form.append(`${key}_json`, JSON.stringify(payload[key]))
    }

    // --- Tệp mới, khớp theo CHỈ SỐ dòng của mảng đã stringify ở trên ---
    for (const [field, rows] of Object.entries(files)) {
      rows.forEach((fileList, index) => {
        for (const file of fileList ?? []) form.append(`${field}[${index}][]`, file)
      })
    }

    const url = id ? `${API_BASE}/${id}/save-full` : `${API_BASE}/save-full`

    return $api(url, { method: 'POST', body: form })
  }
}

export const beneficiaryService = new BeneficiaryService()
```

**Bỏ khỏi service cũ:** `updateStatus()`, `uploadClassificationFiles()`,
`deleteClassificationFile()` — cả ba endpoint không còn.

### 4.2. Danh mục — một service, ba instance

```js
// catalog/configs/catalogs.js
import { SUBJECTS } from '@/modules/nguoicocong/configs/permissions'

export const CATALOGS = {
  residentialArea: {
    key: 'residentialArea',
    api: '/api/beneficiary-residential-areas',
    subject: SUBJECTS.RESIDENTIAL_AREA,
    label: 'Tổ dân phố/Thôn',
    routeName: 'beneficiary-residential-areas',
  },
  beneficiaryType: {
    key: 'beneficiaryType',
    api: '/api/beneficiary-types',
    subject: SUBJECTS.BENEFICIARY_TYPE,
    label: 'Loại đối tượng',
    routeName: 'beneficiary-types',
  },
  relationship: {
    key: 'relationship',
    api: '/api/beneficiary-relationships',
    subject: SUBJECTS.RELATIONSHIP,
    label: 'Mối quan hệ',
    routeName: 'beneficiary-relationships',
  },
}
```

```js
// catalog/services/CatalogService.js
import { ApiService } from '@/services/api-service'
import { $api } from '@/utils/api'

class CatalogService extends ApiService {
  async reorder(items) {
    return $api(`${this.baseEndpoint}/reorder`, { method: 'PATCH', body: { items } })
  }

  async changeStatus(id, status) {
    return $api(`${this.baseEndpoint}/${id}/status`, { method: 'PATCH', body: { status } })
  }

  /** Dropdown: CHỈ lấy mục đang dùng. Xem §7.2 — endpoint không tự lọc. */
  async activeOptions() {
    return this.getList({ status: 'active', limit: -1 })
  }
}

export const catalogServices = Object.fromEntries(
  Object.entries(CATALOGS).map(([key, cfg]) => [key, new CatalogService(cfg.api)]),
)
```

`bulkDelete`, `bulkStatus`, `export`, `import`, `importTemplate` đã có sẵn ở `ApiService` — không
viết lại.

### 4.3. Sub-resource (khi màn có phân trang)

```js
// beneficiary/services/SubResourceService.js
import { ApiService } from '@/services/api-service'

/** Route lồng: /api/beneficiaries/{id}/{resource} */
export function subResourceService(beneficiaryId, resource) {
  return new ApiService(`/api/beneficiaries/${beneficiaryId}/${resource}`)
}
```

Dùng khi màn hình con **có phân trang** (xem §6, ràng buộc bắt buộc). `resource` ∈
`type-relations` | `dependents` | `documents`.

---

## 5. Store

Giữ đúng khuôn setup store hiện có (`defineStore` + `ref` + hàm), chỉ đổi tập filter và bỏ
`stats` theo trạng thái:

```js
const listParams = ref({
  page: 1,
  limit: 10,
  search: '',
  residential_area_id: undefined,
  beneficiary_type_id: undefined,
  relationship_id: undefined,
  gender: undefined,
  birth_year_from: undefined,
  birth_year_to: undefined,
  from_date: undefined,
  to_date: undefined,
  sort_by: undefined,
  sort_order: undefined,
  // KHÔNG có `status` — hồ sơ không có cột trạng thái
})

const stats = ref({
  total: 0,
  new_in_30_days: 0,
  with_coordinates: 0,
  without_coordinates: 0,
  by_gender: {},
  by_residential_area: {},
  by_type: {},
})
```

`unwrapPaginatedList` và `isApiSuccess` dùng nguyên như cũ — khuôn response BE không đổi.

### Store danh mục dùng chung

```js
// catalog/stores/useCatalogStore.js
export const useCatalogStore = defineStore('beneficiaryCatalog', () => {
  // Cache option cho dropdown, theo từng danh mục. Form hồ sơ mở lên gọi 3 lần —
  // cache để chuyển tab không gọi lại.
  const options = ref({ residentialArea: [], beneficiaryType: [], relationship: [] })

  async function loadOptions(key, { force = false } = {}) {
    if (!force && options.value[key].length) return options.value[key]

    const response = await catalogServices[key].activeOptions()
    const { items } = unwrapPaginatedList(response)

    options.value[key] = items

    return items
  }

  /** Gọi sau khi cán bộ sửa danh mục — nếu không, dropdown còn dữ liệu cũ. */
  function invalidate(key) {
    options.value[key] = []
  }

  return { options, loadOptions, invalidate }
})
```

---

## 6. Form trọn gói (`save-full`) — phần khó nhất

Một trang nhập: thông tin cá nhân + 3 danh sách con, bấm Lưu một lần.

### 6.1. Payload

```js
await beneficiaryService.saveFull(
  route.params.id,                       // null = tạo mới
  {
    lockVersion: form.lock_version,      // BẮT BUỘC khi cập nhật
    fields: {
      full_name: form.full_name,
      birth_date: form.birth_date,       // 'Y-m-d'
      birth_year: form.birth_year,
      gender: form.gender,
      id_number: form.id_number,
      phone: form.phone,
      residential_area_id: form.residential_area_id,
      address: form.address,
      latitude: form.latitude,
      longitude: form.longitude,
      note: form.note,
    },
    type_relations: typeRows.map(r => ({
      id: r.id,                          // null = dòng mới
      beneficiary_type_id: r.beneficiary_type_id,
      is_primary: r.is_primary,
      sync_attachments: r.touchedFiles,  // chỉ bật khi người dùng ĐỘNG vào tệp
      keep_media_ids: r.keptMediaIds,
    })),
    dependents: dependentRows.map(r => ({ id: r.id, full_name: r.full_name, /* ... */ })),
    documents: documentRows.map(r => ({
      id: r.id, name: r.name,
      sync_attachments: r.touchedFiles,
      keep_media_ids: r.keptMediaIds,
    })),
  },
  {
    // Khớp theo CHỈ SỐ dòng của mảng tương ứng ở trên
    type_relations_files: typeRows.map(r => r.newFiles),
    documents_files: documentRows.map(r => r.newFiles),
  },
)
```

### 6.2. Ba điều sai là mất dữ liệu, không phải lỗi hiển thị

**a. Danh sách con phải `JSON.stringify`, không được gửi mảng lồng FormData.**

`max_input_vars` của PHP (mặc định 1000) cắt phần **đuôi** payload và **không báo lỗi**. Phần bị
cắt thường là vài phần tử cuối của `keep_media_ids[]` — số dòng vẫn khớp, validate vẫn pass,
nhưng những media id bị cắt rơi vào danh sách xoá và **bị xoá vĩnh viễn khỏi đĩa**. Đếm số dòng
không phát hiện được. JSON chiếm đúng 1 input var mỗi mảng nên không còn gì để cắt.

**b. `"[]"` và "không gửi field" là hai chuyện khác nhau.**

| Gửi | BE hiểu |
|---|---|
| `type_relations_json: "[]"` | Xoá hết dòng Đối tượng |
| Không append `type_relations_json` | Không quản lý — giữ nguyên trong DB |

Nếu form chỉ cho sửa thông tin cá nhân, **đừng gửi ba key JSON** — gửi mảng rỗng sẽ xoá sạch dữ
liệu con.

**c. CẤM gọi `save-full` từ màn hình có phân trang.**

BE xoá mềm mọi dòng con không có trong payload và **vẫn trả 200**. Frontend chỉ giữ một trang
trong state thì toàn bộ phần chưa load bị xoá. **Backend không chặn được điều này** — đây là quy
ước duy nhất mà vi phạm nó không tạo ra lỗi. Màn hình con có phân trang phải dùng sub-resource
CRUD lẻ (§4.3).

### 6.3. Quản lý tệp đính kèm

| Người dùng làm gì | Gửi gì |
|---|---|
| Không đụng vào tệp | Bỏ `sync_attachments` (hoặc `false`) |
| Xoá vài tệp cũ | `sync_attachments: true` + `keep_media_ids: [id còn giữ]` |
| Xoá hết tệp cũ | `sync_attachments: true` + `keep_media_ids: []` |
| Thêm tệp mới | `type_relations_files[<index>][]` |

**`sync_attachments` chỉ bật khi người dùng thực sự động vào vùng tệp.** Bật mặc định thì mở form
lên rồi bấm Lưu là mất sạch tệp — `keep_media_ids` khi đó thường rỗng.

Component editor nên giữ state mỗi dòng:

```js
{
  id: 12,
  existingMedia: [{ id: 7, file_name: 'qd.pdf', url: '...' }],  // từ BE
  keptMediaIds: [7],        // bỏ tệp nào thì xoá id khỏi mảng này
  newFiles: [File, File],   // input file
  touchedFiles: false,      // set true khi xoá tệp cũ HOẶC chọn tệp mới
}
```

Trần tổng tệp mỗi request: **90**. Vượt sẽ nhận 422 (`errors.files`) chứ không bị cắt im lặng —
nên đếm và chặn ngay ở FE cho thông báo dễ hiểu hơn.

### 6.4. Optimistic lock

Mọi response của bản chính trả `lock_version` — **chuỗi ISO8601, giữ nguyên, không format lại**.
Format `d/m/Y` mất phần giây và sẽ 409 vĩnh viễn. `updated_at` là bản để hiển thị; hai field khác
nhau có chủ đích.

```js
try {
  const res = await beneficiaryService.saveFull(id, payload, files)
  form.lock_version = res.data.lock_version   // cập nhật token cho lần lưu kế tiếp
}
catch (e) {
  if (e?.data?.error_code === 'STALE_RECORD') {
    // Người khác đã sửa. KHÔNG tự retry — retry là ghi đè mất thay đổi của họ.
    showReloadDialog(e.data.message)
  }
}
```

Dòng con trả thêm `parent_lock_version` — dùng khi màn sub-resource cần biết bản chính đã đổi
chưa.

---

## 7. Ba màn danh mục

### 7.1. Bộ chức năng

Khác hồ sơ, danh mục **có đủ nhóm trạng thái**: cột Trạng thái, bộ lọc `status`, nút đổi trạng
thái đơn và hàng loạt, cộng `PATCH /reorder` để kéo-thả thứ tự hiển thị.

Trường: `name` (bắt buộc, **duy nhất trong tổ chức**), `note`, `sort_order`, `status`.
**Không có `code`** — bỏ hẳn khỏi form và bảng.

### 7.2. Dropdown phải tự truyền `status=active`

```js
// ĐÚNG — dropdown khi nhập hồ sơ
catalogServices.beneficiaryType.getList({ status: 'active', limit: -1 })

// ĐÚNG — màn quản trị danh mục, thấy cả mục đã ngừng dùng
catalogServices.beneficiaryType.getList({ page: 1, limit: 20 })
```

Endpoint **cố ý không tự lọc**: màn quản trị cần thấy mục `inactive` để bật lại. Quên
`status=active` thì cán bộ chọn được mục đã ngừng dùng, và BE trả 422 lúc lưu hồ sơ.

### 7.3. `inactive` không phải là "đã xoá"

| | `active` | `inactive` |
|---|---|---|
| Hiện trong dropdown chọn | Có | **Không** |
| Hồ sơ cũ đang tham chiếu | Giữ nguyên | **Giữ nguyên, hiển thị bình thường** |
| Import Excel khớp theo tên | Có | **Không** — ô để trống, dòng vẫn nhập |

Màn chi tiết hồ sơ **không được** ẩn hay gạch tên tổ dân phố chỉ vì nó `inactive`. Dữ liệu cũ là
hợp lệ.

### 7.4. Xoá bị chặn — hiện lối thoát ngay trong dialog

```json
{ "success": false,
  "message": "Không thể xoá \"Thương binh\" vì đang có 55 bản ghi sử dụng. Nếu chỉ muốn ẩn khỏi danh sách chọn khi nhập hồ sơ mới, hãy chuyển sang trạng thái \"Ngừng sử dụng\".",
  "error_code": "CATALOG_IN_USE",
  "errors": { "name": "Thương binh", "usage_count": 55 } }
```

Dialog lỗi nên có sẵn nút **"Chuyển sang Ngừng sử dụng"** gọi thẳng `changeStatus(id,
'inactive')`. Không có nút đó thì cán bộ bế tắc, tưởng dữ liệu kẹt vĩnh viễn.

Resource danh mục còn trả `usage_count` ở `index` — dùng để **disable sẵn** nút Xoá, đỡ cho cán
bộ bấm rồi mới biết.

---

## 8. Màn danh sách hồ sơ

### 8.1. Bộ lọc

`search` (họ tên / CCCD / SĐT) · `residential_area_id` · `beneficiary_type_id` ·
`relationship_id` · `gender` · `birth_year_from` / `birth_year_to` · `from_date` / `to_date` ·
`sort_by` · `sort_order` · `limit`.

**Gỡ bộ lọc `status`** và **gỡ cột Trạng thái** khỏi bảng. Gỡ luôn nút "Đổi trạng thái" và "Đổi
trạng thái hàng loạt" — hai endpoint đó trả 404.

Muốn ẩn một hồ sơ thì xoá mềm (`DELETE`).

### 8.2. `stats` không còn đếm theo trạng thái

```json
{ "total": 120, "new_in_30_days": 8,
  "with_coordinates": 100, "without_coordinates": 20,
  "by_gender": { "male": 90, "female": 30 },
  "by_residential_area": { "Tổ dân phố 1": 40 },
  "by_type": { "Thương binh": 55 } }
```

`without_coordinates` là con số đáng đưa lên thẻ nổi bật — nó cho cán bộ biết còn bao nhiêu hồ sơ
chưa chấm được lên bản đồ.

### 8.3. Nhập Excel

Response import giữ khuôn cũ: `data.failed_count`, `data.errors[]`, và `data.error_file` là file
Excel dạng base64. FE decode base64 rồi cho tải về — **không** bắt cán bộ đọc JSON lỗi.

Chỉ **Họ và tên** bắt buộc. Tổ dân phố nhập bằng **TÊN** (không phải id); không khớp thì ô để
trống, dòng vẫn được nhập.

---

## 9. Điểm giữ nguyên — đừng sửa nhầm

- **Tệp đính kèm vẫn có URL trực tiếp.** Resource trả `url`, dùng `<img src>` / `<embed>` như cũ.
  Không cần fetch kèm token rồi dựng blob URL.
- Khuôn response `{ success, message, data }` không đổi → `isApiSuccess`, `unwrapPaginatedList`
  dùng nguyên.
- `created_by` / `updated_by` vẫn là object `{ id, name, avatar }`.
- Format thời gian: chỉ ngày `d/m/Y`, có giờ `H:i:s d/m/Y`.
- `ApiService` base không phải sửa — `export`, `import`, `importTemplate`, `bulkDelete` dùng lại
  hết.

---

## 10. Thân nhân: hệ quả của việc chuyển sang 1–n

v1 cho một thân nhân dùng chung nhiều hồ sơ. v2 là quan hệ 1–n — thân nhân là dòng con trực
thuộc một hồ sơ.

FE phải bỏ:

- Màn "Danh sách thân nhân" toàn hệ thống
- Thao tác "gắn thân nhân có sẵn vào hồ sơ" (drawer chọn từ danh sách)
- **Cảnh báo trùng CCCD thân nhân** — hai hồ sơ cùng khai một người con là **hợp lệ**, BE không
  chặn và FE cũng không nên

Thân nhân chỉ xem/sửa trong ngữ cảnh một hồ sơ.

---

## 11. `is_primary` — "nhiều nhất một", không phải "đúng một"

Áp cho cả Đối tượng chính lẫn Thân nhân chính:

- Chọn một dòng làm chính → BE tự hạ các dòng còn lại xuống. FE chỉ cần gửi cờ, không phải tự
  gỡ cờ dòng khác.
- **Cho phép không có dòng nào là chính** — đừng bắt buộc chọn khi hồ sơ mới nhập.
- Xoá dòng đang là chính → BE **không** tự thăng dòng khác lên. FE nên nhắc cán bộ chọn lại,
  nhưng không tự chọn hộ.
- Gửi nhiều dòng cùng `is_primary: true` → dòng **đầu tiên** thắng.

---

## 12. Hai mã lỗi mới

| `error_code` | HTTP | Khi nào | FE làm gì |
|---|---|---|---|
| `STALE_RECORD` | 409 | `lock_version` lệch | Dialog "tải lại trang". **Không tự retry** |
| `CATALOG_IN_USE` | 409 | Xoá danh mục đang dùng | Dialog kèm nút "Chuyển sang Ngừng sử dụng" |

Bắt theo `error_code`, đừng bắt theo chuỗi `message` — message có thể đổi.

---

## 13. Bảng tra endpoint

```
GET    /api/beneficiaries/stats
GET    /api/beneficiaries
GET    /api/beneficiaries/{id}                       ← kèm 3 danh sách con
POST   /api/beneficiaries
PUT    /api/beneficiaries/{id}                       (hoặc POST + _method=PUT)
DELETE /api/beneficiaries/{id}
DELETE /api/beneficiaries/bulk-delete                body {ids:[]}
POST   /api/beneficiaries/save-full                  ← tạo mới trọn gói
POST   /api/beneficiaries/{id}/save-full             ← cập nhật trọn gói
GET    /api/beneficiaries/export
POST   /api/beneficiaries/import
GET    /api/beneficiaries/import-template

       /api/beneficiaries/{id}/type-relations        6 action  (có tệp → POST + _method=PUT)
       /api/beneficiaries/{id}/dependents            6 action  (không tệp → PUT thẳng)
       /api/beneficiaries/{id}/documents             6 action  (có tệp → POST + _method=PUT)

       /api/beneficiary-residential-areas            + PATCH /reorder, /{id}/status, /bulk-status
       /api/beneficiary-types                        như trên
       /api/beneficiary-relationships                như trên

GET    /api/beneficiary-enums                        → { gender, catalog_status }
```

`/beneficiary-enums` **chỉ còn 2 key**. Loại đối tượng và Mối quan hệ giờ là danh mục DB — gọi
endpoint riêng, không đọc từ enum nữa.

---

## 14. Thứ tự triển khai đề xuất

| Bước | Việc | Xong khi |
|---|---|---|
| 1 | Cập nhật `configs/permissions.js` (§2), gỡ subject đã bỏ | Không còn subject chết |
| 2 | Xoá 7 thư mục sub-module đã bỏ + handler `_mock` tương ứng | Build không lỗi import |
| 3 | Dựng `catalog/` dùng chung, ba route danh mục | CRUD + reorder + đổi trạng thái chạy |
| 4 | `useCatalogStore` cache option cho dropdown | Form hồ sơ có dữ liệu chọn |
| 5 | Màn danh sách hồ sơ + bộ lọc mới + stats | Lọc theo loại đối tượng chạy |
| 6 | Ba component editor (Đối tượng / Thân nhân / Tài liệu) | State tệp đúng theo §6.3 |
| 7 | Form trọn gói + `saveFull()` + optimistic lock | Sửa song song 2 tab → nhận 409 |
| 8 | Export / Import + tải file mẫu | Round-trip xuất → nhập chạy |
| 9 | Đổi nguồn toạ độ cho `map/` sang `/api/beneficiaries` | Bản đồ chấm đúng điểm |
| 10 | Cập nhật `router/navigation.js` | Menu khớp quyền thật |

---

## 15. Checklist trước khi merge

- [ ] Không còn chỗ nào gọi `/beneficiary-households`, `/beneficiary-statistics`,
      `/beneficiary-dependents` (route phẳng), `/beneficiary-documents` (route phẳng)
- [ ] Không còn cột/bộ lọc/nút "Trạng thái" ở màn **hồ sơ** (màn danh mục thì phải có)
- [ ] Mọi dropdown danh mục truyền `status=active`
- [ ] `lock_version` gửi nguyên chuỗi ISO8601, không format
- [ ] `sync_attachments` chỉ bật khi người dùng động vào vùng tệp
- [ ] Form chỉ sửa thông tin cá nhân **không** gửi ba key `*_json`
- [ ] Màn con có phân trang dùng sub-resource CRUD, **không** dùng `save-full`
- [ ] Bắt `error_code` `STALE_RECORD` và `CATALOG_IN_USE`, không bắt theo `message`
- [ ] Không cảnh báo trùng CCCD thân nhân
- [ ] Đối chiếu subject thật: `JSON.parse(localStorage.userAbilityRules).map(r => r.subject)`
