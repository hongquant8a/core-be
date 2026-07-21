# Hướng dẫn Frontend — Module Người có công (Beneficiary)

> Ngày tạo: 15:35:03 19/07/2026
> Cập nhật lần cuối: 10:00:00 21/07/2026

Tài liệu tổng hợp toàn bộ luồng hoạt động, endpoint, field request/response của module Người có công (TP Đà Nẵng) để FE triển khai màn hình. Đây là tài liệu **workflow tổng hợp** — chi tiết từng field/response mẫu của mỗi resource xem thêm `docs/api/beneficiary*.md` (lưu ý: các file đó có thể chưa cập nhật 2 thay đổi mới nhất nêu ở mục 3.2 và 3.4, tài liệu này là bản đúng nhất tại thời điểm viết).

Thiết kế DB đầy đủ: [`docs/database/Beneficiary.md`](../database/Beneficiary.md). Luồng nghiệp vụ BE: [`docs/modules/Beneficiary/README.md`](../modules/Beneficiary/README.md).

---

## 0. Những điều bắt buộc biết trước khi gọi API

### 0.1. Auth & Tenant

Chi tiết đầy đủ: [`docs/system/AUTH_TENANT.md`](../system/AUTH_TENANT.md). Tóm tắt cho module này:

- Đăng nhập `POST /api/auth/login` lấy `token`, gắn mọi request sau: `Authorization: Bearer {token}`.
- **Mọi endpoint nghiệp vụ của module đều bắt buộc header** `X-Organization-Id: {id}` (id phường/xã cán bộ đang thao tác). Thiếu → `422`. User không thuộc org đó → `403`.
- Dữ liệu tự động lọc theo `organization_id` hiện tại — FE **không cần** tự truyền `organization_id` khi tạo/sửa, và **không thể** dùng nó để truy cập chéo phường (BE tự chặn, thao tác theo `id` của org khác trả `404`).
- Riêng `beneficiary-subsidy-policies`: KHÔNG bắt buộc phải thuộc org hiện tại — có thể trả về cả bản ghi dùng chung toàn thành phố (`organization_id = null`), xem mục 1.2.

### 0.2. Cấu trúc response chung

Toàn dự án dùng 1 envelope (`RespondsWithJson`):

```jsonc
// show / store / update / changeStatus
{ "success": true, "message": "Tạo hồ sơ người có công thành công!", "data": { /* Resource */ } }

// index (danh sách, có phân trang)
{
  "success": true,
  "data": [ /* Resource[] */ ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "from": 1, "last_page": 3, "path": "...", "per_page": 10, "to": 10, "total": 26 }
}

// stats / destroy / bulk*
{ "success": true, "message": "...", "data": { /* raw object, vd {"total": 50, ...} */ } }

// lỗi (validate 422, business rule 4xx)
{ "success": false, "message": "...", "errors": { "full_name": ["Họ tên không được để trống."] } }
```

### 0.3. Bộ query filter chung cho mọi `index`/`stats`/`export`

| Param | Ghi chú |
|---|---|
| `search` | Theo từng resource — xem cột "Tìm theo" ở bảng field mỗi resource bên dưới |
| `status` | Chỉ áp dụng resource có cột `status` |
| `from_date`, `to_date` | Định dạng `Y-m-d`, mặc định lọc theo `created_at` (riêng vài resource lọc theo cột ngày nghiệp vụ — ghi rõ ở từng mục) |
| `sort_by`, `sort_order` (`asc`/`desc`) | Danh sách cột hợp lệ khác nhau theo resource — sai tên tự fallback về cột mặc định, KHÔNG lỗi 422 |
| `limit` | Số bản ghi/trang. **`limit=-1` = lấy tất cả** (dùng cho dropdown/export), tối đa 1.000.000 |

### 0.4. Danh sách bảng chủ thể (theo thứ tự nên triển khai màn hình)

| # | Bảng | Là gì | Route prefix |
|---|---|---|---|
| 1 | `beneficiary_residential_areas` | Danh mục Tổ dân phố | `beneficiary-residential-areas` |
| 2 | `beneficiary_subsidy_policies` | Danh mục Chính sách trợ cấp | `beneficiary-subsidy-policies` |
| 3 | `beneficiary_households` | Hộ gia đình | `beneficiary-households` |
| 4 | **`beneficiaries`** | **Người có công (chủ thể trung tâm)** | `beneficiaries` |
| 5 | `beneficiary_classifications` | Phân loại đối tượng (sub của #4, không có route riêng) | — |
| 6 | `beneficiary_dependents` | Thân nhân | `beneficiary-dependents` |
| 7 | `beneficiary_dependent_relations` | Quan hệ hưởng chế độ (sub-resource của #6) | `beneficiary-dependents/{id}/relations` |
| 8 | `beneficiary_subsidy_grants` | Trợ cấp đã cấp | `beneficiary-subsidy-grants` |
| 9 | `beneficiary_status_histories` | Lịch sử đổi trạng thái (chỉ đọc, nested) | `.../status-histories` |
| 10 | `beneficiary_visit_schedules` | Lịch viếng thăm/tặng quà | `beneficiary-visit-schedules` |
| 11 | Media (spatie) | Giấy tờ đính kèm | qua `MediaService`, không phải REST resource riêng |

---

## 1. Danh mục dùng trước (setup 1 lần)

### 1.1. Tổ dân phố — `beneficiary-residential-areas`

CRUD đầy đủ, KHÔNG có `status` (không có `bulk-status`/`{id}/status`).

| Action | Method & Path | Permission |
|---|---|---|
| Thống kê | `GET /stats` | `beneficiary-residential-areas.stats` |
| Danh sách | `GET /` | `.index` |
| Chi tiết | `GET /{id}` | `.show` |
| Tạo | `POST /` | `.store` |
| Cập nhật | `PUT /{id}` | `.update` |
| Xóa | `DELETE /{id}` | `.destroy` |
| Xóa hàng loạt | `DELETE /bulk-delete` body `{"ids":[...]}` | `.bulkDestroy` |
| Xuất Excel | `GET /export` | `.export` |
| Nhập Excel | `POST /import` | `.import` |

**Body tạo/sửa:**

| Field | Bắt buộc | Kiểu | Ghi chú |
|---|---|---|---|
| `name` | Có (tạo) / tùy chọn (sửa) | string ≤255 | "Tổ 5" |
| `code` | Không | string ≤255 | Mã nội bộ nếu có |

**Response** (`ResidentialAreaResource`): `id, name, code, household_count (chỉ có khi index/show — số hộ đang gán), created_by, updated_by, created_at, updated_at` (format `H:i:s d/m/Y`).

`search` lọc theo `name`. `sort_by`: `id, name, created_at, updated_at`.

### 1.2. Chính sách trợ cấp — `beneficiary-subsidy-policies`

CRUD đầy đủ + `renew`, KHÔNG có `status` (hiệu lực xác định bởi `effective_from`/`effective_to`, không có route bulk-status).

| Action | Method & Path | Permission |
|---|---|---|
| Thống kê | `GET /stats` | `.stats` |
| Danh sách | `GET /` | `.index` |
| Chi tiết | `GET /{id}` | `.show` |
| Tạo | `POST /` | `.store` |
| Cập nhật | `PUT /{id}` | `.update` |
| Xóa | `DELETE /{id}` | `.destroy` |
| Xóa hàng loạt | `DELETE /bulk-delete` | `.bulkDestroy` |
| **Ban hành mức mới thay thế** | `POST /{id}/renew` | `.renew` |
| Xuất/Nhập Excel | `GET /export`, `POST /import` | `.export` / `.import` |

**Body tạo/sửa/renew:**

| Field | Bắt buộc | Ghi chú |
|---|---|---|
| `beneficiary_type` | Không | 1 trong 12 giá trị `BeneficiaryTypeEnum` (mục 11) — để trống = áp dụng mọi loại |
| `relationship_type` | Không | 1 trong 6 giá trị `DependentRelationshipEnum` — dùng khi chính sách dành cho thân nhân |
| `amount` | **Có** | number ≥0 |
| `unit` | Không | mặc định `"VND/tháng"` |
| `legal_basis` | **Có** | string ≤255, VD "Nghị định 75/2021/NĐ-CP" |
| `effective_from` | **Có** | date |
| `effective_to` | Không | date, phải sau `effective_from` |

**`POST /{id}/renew`** — hành động nghiệp vụ đặc biệt: đóng chính sách cũ + tạo chính sách mới + **tự động nối tiếp mọi `subsidy_grant` đang active thuộc chính sách cũ** sang chính sách mới (đóng grant cũ, tạo grant mới, giữ nguyên lịch sử — FE chỉ cần gọi API này khi Nhà nước tăng mức trợ cấp, không cần tự xử lý từng grant).

**Response** (`SubsidyPolicyResource`): thêm field tính sẵn `is_effective` (boolean — còn hiệu lực hay không, FE dùng để tô màu/disable khi chọn chính sách lúc cấp trợ cấp).

⚠️ **Quan trọng — phạm vi tenant khác các resource khác:** `beneficiary_subsidy_policies` có thể có `organization_id = null` (catalog dùng chung toàn TP). Route này **không** dùng middleware `ensure.route.org` bắt buộc — danh sách trả về gồm cả bản ghi của org hiện tại LẪN bản ghi dùng chung. Khi hiển thị dropdown chọn chính sách, không cần lọc thêm gì, BE đã gộp sẵn.

### 1.3. "Loại đối tượng" và các enum tĩnh khác — KHÔNG phải danh mục CRUD, nhưng ĐÃ có endpoint tra cứu

12 giá trị "Loại đối tượng" theo Pháp lệnh 02/2020/UBTVQH14 (và 9 enum tĩnh khác của module) **không có API tạo/sửa/xóa** — vẫn là PHP Enum hardcode trong code BE. Nhưng từ 21/07/2026, FE **không cần tự hardcode** giá trị/label nữa: gọi 1 lần

```
GET /api/beneficiary-enums
```

(cần header `X-Organization-Id` như mọi endpoint khác trong nhóm `auth:sanctum`, nhưng dữ liệu trả về **không** phụ thuộc tổ chức — có thể gọi 1 lần rồi cache cả session, không cần gọi lại mỗi khi đổi tổ chức) trả về toàn bộ 10 enum của module cùng lúc:

```jsonc
{
  "success": true,
  "data": {
    "beneficiary_type": [{ "value": "martyr", "label": "Liệt sĩ" }, /* ... 12 giá trị, mục 11.1 */],
    "beneficiary_status": [/* ... mục 11.2 */],
    "gender": [/* ... mục 11.3 */],
    "dependent_eligibility": [/* ... mục 11.4 */],
    "dependent_relationship": [/* ... mục 11.5 */],
    "dependent_relation_status": [/* ... mục 11.6 */],
    "subsidy_status": [/* ... mục 11.7 */],
    "document_type": [/* ... mục 11.8 */],
    "visit_occasion": [/* ... mục 11.9 */],
    "schedule_status": [/* ... mục 11.10 */]
  }
}
```

Route: `App\Modules\Beneficiary\Controllers\EnumController::index()` (`app/Modules/Beneficiary/Routes/enum.php`). Không gắn permission riêng (chỉ cần đăng nhập) vì đây là dữ liệu tra cứu dùng chung cho nhiều form/permission khác nhau trong module, không phải resource CRUD của 1 quyền cụ thể — giống cách `Scheduling` xử lý "general views".

Bảng tĩnh ở mục 11 bên dưới vẫn giữ nguyên để tham khảo nhanh, nhưng **nguồn đúng (source of truth) là response API**, không phải bảng markdown — nếu sau này BE thêm/sửa case enum mà quên cập nhật tài liệu này, response API vẫn phản ánh đúng.

---

## 2. Bảng chủ thể — Hộ gia đình (`beneficiary-households`)

CRUD đầy đủ, KHÔNG có `status`.

| Action | Method & Path | Permission |
|---|---|---|
| Thống kê | `GET /stats` | `.stats` |
| Danh sách | `GET /` | `.index` |
| Chi tiết | `GET /{id}` | `.show` |
| Tạo | `POST /` | `.store` |
| Cập nhật | `PUT /{id}` | `.update` |
| Xóa | `DELETE /{id}` | `.destroy` |
| Xóa hàng loạt | `DELETE /bulk-delete` | `.bulkDestroy` |
| Xuất/Nhập Excel | `GET /export`, `POST /import` | `.export` / `.import` |

**Body tạo/sửa:**

| Field | Bắt buộc | Ghi chú |
|---|---|---|
| `residential_area_id` | Không | FK tổ dân phố (mục 1.1) |
| `household_code` | Không | Để trống → BE tự sinh `{mã_org}-HGD-{số thứ tự 5 chữ số}` |
| `head_name` | **Có** | Chủ hộ |
| `head_id_number` | Không | CCCD chủ hộ |
| `address` | Không | **Cho phép để trống lúc tạo**, bổ sung sau khi xác minh thực địa |
| `latitude`, `longitude` | Không | Tọa độ, `-90..90` / `-180..180` |
| `phone`, `note` | Không | |
| `beneficiary_ids` | Không (chỉ lúc tạo) | Mảng ID người có công gán ngay vào hộ |
| `dependent_ids` | Không (chỉ lúc tạo) | Mảng ID thân nhân gán ngay vào hộ |

**Response** (`HouseholdResource`): `id, residential_area_id, residential_area {id,name} (khi loaded), household_code, head_name, head_id_number, address, latitude, longitude, phone, member_count, note, created_by, updated_by, created_at, updated_at`.

`member_count` **tự động đếm lại** mỗi khi có thành viên gán vào/gỡ khỏi hộ (qua Beneficiary/Dependent) — FE **không tự tính**, chỉ hiển thị giá trị BE trả về.

`search` lọc theo `head_name` HOẶC `household_code`. Filter thêm: `residential_area_id`. `sort_by`: `id, head_name, household_code, member_count, created_at, updated_at`.

---

## 3. Bảng chủ thể — Người có công (`beneficiaries`) ⭐

Chủ thể trung tâm của module — nhiều action nhất trong toàn bộ hệ thống.

| Action | Method & Path | Permission |
|---|---|---|
| Thống kê | `GET /stats` | `.stats` |
| Danh sách | `GET /` | `.index` |
| Chi tiết | `GET /{id}` | `.show` |
| **Tạo** | `POST /` | `.store` |
| **Cập nhật** | `PUT /{id}` | `.update` |
| Xóa | `DELETE /{id}` | `.destroy` |
| Xóa hàng loạt | `DELETE /bulk-delete` | `.bulkDestroy` |
| Đổi trạng thái hàng loạt | `PATCH /bulk-status` | `.bulkUpdateStatus` |
| **Đổi trạng thái đơn** | `PATCH /{id}/status` | `.changeStatus` |
| Lịch sử đổi trạng thái | `GET /{id}/status-histories` | `.show` |
| Xuất/Nhập Excel | `GET /export`, `POST /import` | `.export` / `.import` |

`search` lọc theo `full_name` HOẶC `id_number`. Filter thêm: `status`, `household_id`. `sort_by`: `id, full_name, date_of_birth, status, created_at, updated_at`.

### 3.1. Body tạo (`POST /beneficiaries`) — field cơ bản

| Field | Bắt buộc | Ghi chú |
|---|---|---|
| `household_id` | Không | FK hộ có sẵn — **không dùng cùng lúc với `household`** (mục 3.4) |
| `full_name` | **Có** | ≤255 |
| `date_of_birth` | Không | date — dùng khi biết đủ ngày/tháng/năm |
| `birth_year` | Không | text ≤20 — dùng khi chỉ nhớ năm (VD "1950", "khoảng 1948") |
| `gender` | **Có** | `male \| female \| other` |
| `id_number` | Không | CCCD/CMND — unique trong phạm vi 1 phường |
| `injury_rate` | Không | % thương tật, 0–100 |
| `recognition_decision_no`, `recognition_date` | Không | Chỉ tham chiếu nhanh — chi tiết từng loại nằm ở `classifications` |
| `status` | Không | mặc định `pending` — xem enum mục 11.2 |
| `address`, `latitude`, `longitude`, `phone`, `note` | Không | Địa chỉ/tọa độ độc lập với hộ (dùng khi NCC ở khác địa chỉ hộ) |

### 3.2. `classifications` — phân loại đối tượng (COARSE, gộp trong payload NCC)

Một người có công có thể có **nhiều** bản ghi phân loại (VD vừa thương binh vừa nạn nhân chất độc hóa học).

**Lúc tạo (`POST /beneficiaries`)** — mảng đơn giản, mỗi phần tử chỉ tạo mới:

```jsonc
"classifications": [
  { "type": "war_invalid", "is_primary": true },
  { "type": "agent_orange_victim", "decision_no": "QD-45/2020", "decision_date": "2020-05-01", "issued_by": "UBND phường" }
]
```

| Field trong mỗi phần tử | Bắt buộc | Ghi chú |
|---|---|---|
| `type` | **Có** | 1 trong 12 giá trị `BeneficiaryTypeEnum` |
| `decision_no`, `decision_date`, `issued_by` | Không | Giấy tờ hành chính — **cho phép bổ sung sau** khi cán bộ có đủ hồ sơ, không chặn tạo NCC |
| `is_primary` | Không | Tối đa **1** phần tử `true`/hồ sơ — loại chính dùng tính trợ cấp ưu tiên |

**Lúc sửa (`PUT /beneficiaries/{id}`)** — ⚠️ khác cơ chế, phải đọc kỹ vì đây là **quy ước sync**, không phải "gửi gì lưu nấy":

| `classifications[i]` có... | Hành động BE |
|---|---|
| Có `id` (thuộc đúng hồ sơ này) | **Cập nhật** dòng đó |
| Không có `id` | **Tạo mới** |
| Dòng cũ **không xuất hiện** trong mảng `classifications` | **Giữ nguyên** — KHÔNG tự xóa |
| Muốn xóa dòng cũ | Đưa `id` vào mảng riêng **`classifications_deleted`** (không phải xóa bằng cách bỏ khỏi payload) |

```jsonc
// PUT /beneficiaries/12
{
  "classifications": [
    { "id": 3, "type": "war_invalid", "is_primary": true },   // cập nhật dòng id=3
    { "type": "martyr" }                                       // tạo mới, không is_primary
  ],
  "classifications_deleted": [7]                                // xóa dòng id=7
}
```

Bất biến **"chỉ 1 `is_primary=true`/hồ sơ"** được BE enforce **toàn cục** — nếu FE đánh `is_primary=true` cho 1 dòng, BE tự demote MỌI dòng khác của hồ sơ đó về `false`, kể cả dòng không nằm trong payload lần này. FE không cần tự gửi `is_primary=false` cho các dòng cũ.

**Response mỗi classification** (`BeneficiaryClassificationResource`): `id, type, type_label, decision_no, decision_date, issued_by, is_primary`.

### 3.3. `status` & `changeStatus` — vòng đời hồ sơ

| Trạng thái hiện tại | Gọi | Trạng thái mới | Body bắt buộc thêm |
|---|---|---|---|
| `pending` | `PATCH /{id}/status` | `active` | — |
| `active` | `PATCH /{id}/status` | `deceased` | `death_date` (bắt buộc), `reason` |
| `active` | `PATCH /{id}/status` | `moved_out` | `reason` (khuyến nghị) |
| `active` | `PATCH /{id}/status` | `suspended` | `reason` (khuyến nghị) |

Body: `status` (bắt buộc), `reason` (tùy chọn), `death_date` (bắt buộc nếu `status=deceased`).

Khi chuyển `deceased`/`moved_out`: BE **tự động dừng** mọi `subsidy_grant` đang `active` gắn trực tiếp với NCC này — FE không cần gọi thêm API dừng trợ cấp riêng. Mọi lần đổi trạng thái đều ghi vào `beneficiary_status_histories`, xem `GET /{id}/status-histories` (trả `StatusHistoryCollection`: `id, old_status, new_status, reason, changed_by, changed_at`).

⚠️ **Khác với tài liệu thiết kế `docs/database/Beneficiary.md`:** thiết kế nháp có nêu "thiếu giấy tờ bắt buộc thì chặn chuyển `pending → active`", nhưng code hiện tại (`BeneficiaryService::changeStatus()`) **không có** bước kiểm tra này — BE cho đổi trạng thái tự do, không validate giấy tờ đính kèm. FE không nên tự chặn nút "Công nhận" chờ đủ giấy tờ (vì thực tế chưa upload được giấy tờ gì cho NCC — xem cảnh báo ở mục 8).

`bulkUpdateStatus` (`PATCH /bulk-status`, body `{ids:[...], status}`) đổi hàng loạt nhưng **không** ghi status-history và **không** tự dừng trợ cấp (chỉ dùng cho thao tác quản trị dữ liệu, KHÔNG dùng thay cho `changeStatus` trong luồng nghiệp vụ bình thường).

### 3.4. `household` / `dependents` — lối tắt tạo hồ sơ mới hoàn toàn (chỉ ở `POST`, không có ở `PUT`)

Dành riêng cho tình huống tiếp nhận **hồ sơ mới toanh**: 1 lần gọi tạo luôn cả NCC + hộ gia đình (nếu chưa có) + thân nhân đi kèm (tự tạo cả quan hệ hưởng chế độ) — tránh FE phải gọi 3-4 API tuần tự.

```jsonc
// POST /beneficiaries — ví dụ đầy đủ 1 hồ sơ mới
{
  "full_name": "Trần Văn B",
  "gender": "male",
  "classifications": [{ "type": "war_invalid", "is_primary": true }],

  "household": {                          // KHÔNG gửi cùng household_id
    "head_name": "Trần Văn B",
    "address": "12 Trần Phú, Hải Châu"
  },

  "dependents": [
    {
      "full_name": "Trần Thị D",
      "gender": "female",
      "date_of_birth": "2015-03-01",
      "relationship_type": "child",       // thêm so với StoreDependentRequest
      "eligible_from": "2024-01-01"       // thêm so với StoreDependentRequest
    }
  ]
}
```

- `household` (object, **loại trừ với `household_id`** — gửi cả 2 sẽ lỗi 422): các field giống mục 2, tạo hộ mới rồi tự gán làm hộ của NCC đang tạo.
- `dependents` (array): mỗi phần tử = toàn bộ field của thân nhân (mục 4) **cộng thêm** `relationship_type` (bắt buộc, xem enum mục 11.5) và `eligible_from` (bắt buộc, date) để BE tự tạo `beneficiary_dependent_relations` nối NCC vừa tạo với thân nhân này. `household_id` của mỗi thân nhân mặc định lấy theo hộ của NCC nếu không truyền riêng.
- Trạng thái quan hệ (`active`/`expired`) do BE **tự tính theo tuổi + `eligibility_status`** giống hệt quy tắc ở mục 5 — không hard-code `active`.

Sau khi tạo, hộ/thân nhân là resource **độc lập** — sửa tiếp qua `beneficiary-households`/`beneficiary-dependents` như bình thường, KHÔNG dùng lại `household`/`dependents` object khi `PUT /beneficiaries/{id}` (field này chỉ FE gửi ở `POST`, BE cũng không đọc field này ở `update`).

### 3.5. Response chi tiết NCC (`BeneficiaryResource`)

```jsonc
{
  "id": 12,
  "household_id": 5,
  "household": { "id": 5, "household_code": "PHUONG-HGD-00003", "head_name": "..." }, // chỉ có khi API load quan hệ (show/index có with)
  "full_name": "Trần Văn B",
  "date_of_birth": "20/05/1950", "birth_year": null,
  "gender": "male", "gender_label": "Nam",
  "id_number": "049123456789",
  "injury_rate": 61,
  "recognition_decision_no": "QD-123/2020", "recognition_date": "15/07/2020",
  "status": "active", "status_label": "Đang hưởng",
  "death_date": null,
  "address": null, "latitude": 16.0678, "longitude": 108.2208,
  "phone": null, "note": null,
  "classifications": [ /* xem 3.2 */ ],
  "dependents_count": 2,              // chỉ có ở index (withCount)
  "active_subsidy_grants_count": 1,   // chỉ có ở index (withCount)
  "created_by": { /* user summary */ }, "updated_by": { /* ... */ },
  "created_at": "10:00:00 16/07/2026", "updated_at": "..."
}
```

Lưu ý: mọi ngày tháng field nghiệp vụ format `d/m/Y` (VD `20/05/1950`), riêng `created_at`/`updated_at` format `H:i:s d/m/Y`.

---

## 4. Bảng chủ thể — Thân nhân (`beneficiary-dependents`)

| Action | Method & Path | Permission |
|---|---|---|
| Thống kê | `GET /stats` | `.stats` |
| Danh sách | `GET /` | `.index` |
| Chi tiết | `GET /{id}` | `.show` |
| Tạo | `POST /` | `.store` |
| Cập nhật | `PUT /{id}` | `.update` |
| Xóa | `DELETE /{id}` | `.destroy` |
| Xóa hàng loạt | `DELETE /bulk-delete` | `.bulkDestroy` |
| Thêm quan hệ với 1 NCC | `POST /{id}/relations` | `.storeRelation` |
| Xóa quan hệ | `DELETE /{id}/relations/{relation}` | `.destroyRelation` |
| Lịch sử đổi trạng thái | `GET /{id}/status-histories` | `.show` |
| Xuất/Nhập Excel | `GET /export`, `POST /import` | `.export` / `.import` |

Không có `status` vòng đời như Beneficiary (chỉ có `eligibility_status` sửa qua `update()` và trạng thái quan hệ pivot qua relations — mục 5).

**Body tạo/sửa:**

| Field | Bắt buộc | Ghi chú |
|---|---|---|
| `household_id` | Không | |
| `full_name` | **Có** | |
| `date_of_birth` | Không | Dùng tính tuổi runtime — quyết định điều kiện hưởng tuất |
| `gender` | **Có** | |
| `id_number` | Không | |
| `is_alive` | Không | mặc định `true` |
| `death_date` | **Bắt buộc nếu `is_alive=false`** | |
| `eligibility_status` | Không | `normal \| studying \| disabled_no_work_capacity` |
| `note` | Không | |

⚠️ **Side-effect khi `update()` chuyển `is_alive: true → false`:** BE tự động chuyển **toàn bộ** quan hệ pivot đang `active` của thân nhân này sang `expired` (trừ truy lĩnh — chưa hỗ trợ). FE hiển thị cảnh báo xác nhận trước khi cho cán bộ đổi field này.

**Response** (`DependentResource`): `id, household_id, household{...}, full_name, date_of_birth, gender, gender_label, id_number, is_alive, death_date, eligibility_status, eligibility_status_label, note, relations[] (xem mục 5, chỉ có khi load), created_by, updated_by, created_at, updated_at`.

`search` lọc theo `full_name` HOẶC `id_number`. Filter thêm: `household_id`. `sort_by`: `id, full_name, date_of_birth, created_at, updated_at`.

---

## 5. Quan hệ hưởng chế độ (`beneficiary_dependent_relations`)

Không có route CRUD riêng đầy đủ — chỉ **thêm**/**xóa** qua sub-resource của Dependent (mục 4). Đây là bước bắt buộc để thân nhân **thực sự** được hưởng chế độ (chỉ tạo Dependent ở mục 4 chưa đủ).

**`POST /beneficiary-dependents/{id}/relations`** — body:

| Field | Bắt buộc | Ghi chú |
|---|---|---|
| `beneficiary_id` | **Có** | NCC mà thân nhân này phụ thuộc |
| `relationship_type` | **Có** | `spouse \| child \| father \| mother \| foster_parent \| guardian` |
| `eligible_from` | **Có** | Ngày bắt đầu đủ điều kiện hưởng |
| `note` | Không | |

**BE tự tính `status` khởi tạo** (FE không truyền, không hiển thị input chọn status khi tạo):

```
is_alive (Dependent) = false           → status = expired
tuổi < 18                               → status = active
tuổi ≥ 18 VÀ eligibility_status
  ∈ {studying, disabled_no_work_capacity} → status = active
tuổi ≥ 18 VÀ eligibility_status khác    → status = expired
```

→ **Gợi ý UX:** nếu thân nhân đã ≥18 tuổi và chưa set `eligibility_status` phù hợp, cảnh báo trước cho cán bộ ("thân nhân sẽ được tạo với trạng thái Hết điều kiện hưởng — cập nhật Tình trạng điều kiện hưởng trước nếu thân nhân đang đi học").

`eligible_until` **không bao giờ** do FE/cán bộ set trực tiếp — chỉ Job hệ thống chạy hằng ngày tự tính khi phát hiện hết tuổi/điều kiện. Không hiển thị input cho field này.

Một thân nhân có thể có **nhiều** quan hệ (với nhiều NCC khác nhau) — mỗi quan hệ là 1 bản ghi độc lập, điều kiện hưởng tính riêng, **không gộp**.

**`DELETE /beneficiary-dependents/{id}/relations/{relation}`** — xóa hẳn 1 quan hệ (khác với đổi status).

**Response mỗi relation** (`DependentRelationResource`, lồng trong `DependentResource.relations[]` hoặc trả riêng khi `storeRelation`): `id, beneficiary_id, beneficiary{id,full_name}, relationship_type, relationship_type_label, eligible_from, eligible_until, status, status_label, note`.

---

## 6. Trợ cấp (`beneficiary-subsidy-grants`)

Chỉ `index, store, changeStatus` — **không có** `update/destroy/bulkDestroy/import/export` (bản ghi phát sinh từ hành động nghiệp vụ "cấp trợ cấp", không phải danh mục CRUD tự do).

| Action | Method & Path | Permission |
|---|---|---|
| Danh sách | `GET /` | `.index` |
| Cấp trợ cấp | `POST /` | `.store` |
| Dừng/tạm dừng | `PATCH /{id}/status` | `.changeStatus` |

**`GET /` query:** `subject_type`, `subject_id`, `status` (`active\|terminated\|suspended`), `from_date`/`to_date` (lọc theo `granted_from`), `limit`. `sort_by`: `id, amount, granted_from, created_at`.

⚠️⚠️ **Gotcha quan trọng nhất module — `subject_type` có 2 định dạng khác nhau tùy chỗ dùng:**

| Chỗ dùng | Giá trị `subject_type` |
|---|---|
| Body khi **tạo** (`POST /`) | Alias ngắn: `"beneficiary"` hoặc `"dependent"` |
| Query filter khi **liệt kê** (`GET /?subject_type=...`) | **FQCN đầy đủ**, y hệt giá trị BE đã lưu trong DB: `"App\Modules\Beneficiary\Models\Beneficiary"` hoặc `"App\Modules\Beneficiary\Models\Dependent"` |
| Field `subject_type` trong **response** (`SubsidyGrantResource`) | FQCN đầy đủ (giống trên) |

BE hiện **không** có morph map alias ngắn cho query/response — nếu FE chỉ cần lọc trợ cấp của 1 NCC/thân nhân cụ thể, cách an toàn nhất là dùng `subject_id` kết hợp đọc `subject_type` trả về từ 1 bản ghi mẫu trước, hoặc lọc theo ngữ cảnh màn hình (đang xem NCC nào thì tự biết `subject_id`) thay vì hardcode alias `"beneficiary"` vào query string.

**Body tạo (`POST /`):**

| Field | Bắt buộc | Ghi chú |
|---|---|---|
| `subject_type` | **Có** | `"beneficiary"` hoặc `"dependent"` (alias ngắn — xem bảng trên) |
| `subject_id` | **Có** | ID NCC hoặc thân nhân |
| `beneficiary_subsidy_policy_id` | **Có** | Chọn từ danh mục mục 1.2 (chỉ chính sách còn hiệu lực — check `is_effective` trước khi cho chọn) |
| `amount` | Không | Để trống → tự lấy `amount` của chính sách; điền tay nếu có điều chỉnh riêng |
| `granted_from` | **Có** | date |

Nếu chọn chính sách đã hết hiệu lực (`effective_to` đã qua) → BE trả lỗi 422 field `beneficiary_subsidy_policy_id`, chặn tạo. Với thân nhân: chỉ cấp được khi quan hệ pivot (mục 5) đang `status=active` — BE không tự chặn ở tầng validate request nhưng nghiệp vụ yêu cầu FE chỉ hiển thị nút "Cấp trợ cấp" cho quan hệ đang active.

**`PATCH /{id}/status`:** body `status` (`active\|terminated\|suspended`), `termination_reason` (**bắt buộc nếu `status=terminated`**).

**Response** (`SubsidyGrantResource`): `id, subject_type, subject_id, subject{id,name}, beneficiary_subsidy_policy_id, policy{id,legal_basis}, amount, granted_from, granted_to, status, status_label, termination_reason, created_at, updated_at`.

Khi Nhà nước đổi mức trợ cấp: FE gọi `POST /beneficiary-subsidy-policies/{id}/renew` (mục 1.2) — BE tự đóng grant cũ + tạo grant mới nối tiếp cho MỌI đối tượng đang hưởng chính sách đó, **không** cần FE lặp gọi `changeStatus` từng grant.

---

## 7. Lịch viếng thăm / tặng quà (`beneficiary-visit-schedules`)

Chỉ `index, show, changeStatus` — **không có `store`** (lịch được BE **tự sinh** đầu năm/đầu dịp lễ qua Console Command, hoặc tạo thủ công qua kênh khác cho `occasion=custom` — nếu cần màn hình cho cán bộ tự tạo lịch nhắc, cần xác nhận thêm với BE vì hiện route `store` không tồn tại).

| Action | Method & Path | Permission |
|---|---|---|
| Danh sách | `GET /` | `.index` |
| Chi tiết | `GET /{id}` | `.show` |
| Đổi trạng thái | `PATCH /{id}/status` | `.changeStatus` |

**`GET /` query:** `assigned_to` (lọc theo cán bộ phụ trách), `status` (`pending\|done\|skipped`), `from_date`/`to_date` (lọc theo `scheduled_date`), `limit`. `sort_by` mặc định `scheduled_date asc`.

**`PATCH /{id}/status`** (multipart/form-data nếu có ảnh):

| Field | Bắt buộc | Ghi chú |
|---|---|---|
| `status` | **Có** | Chỉ nhận `done` hoặc `skipped` |
| `note` | Không | Lý do nếu `skipped` |
| `evidence[]` | Không | File ảnh (jpg/jpeg/png/gif/webp, ≤10MB/file) — chỉ dùng khi `status=done` |

**Response** (`VisitScheduleResource`): `id, subject_type, subject_id, subject{id,name}, occasion, occasion_label, scheduled_date, status, status_label, assigned_to{user summary}, note, evidence[{id,url}], created_at, updated_at`.

Nhắc lịch **tự động** qua hạ tầng thông báo chung — FE không cần tự tính ngày nhắc; nếu cần màn hình cho cán bộ phường tự cấu hình "nhắc trước N ngày", dùng nhóm endpoint `/api/beneficiary/notification-config/event-configs` (xem `docs/api/notification-config.md`).

---

## 8. Giấy tờ / Hồ sơ đính kèm

⚠️ **CHƯA TRIỂN KHAI cho Beneficiary/Dependent — đừng dựng UI upload giấy tờ NCC dựa theo mục này mà không hỏi lại BE trước.**

Đã kiểm tra code thực tế (`app/Modules/Beneficiary/Models/Beneficiary.php`, `Dependent.php`): **không** implement `HasMedia`/`InteractsWithMedia`, **không** có `registerMediaCollections()`. Tài liệu thiết kế `docs/database/Beneficiary.md` mục 11 có mô tả dự kiến 4 collection (`decision_documents`, `identity_documents`, `death_certificates`, `medical_certificates`) nhưng đây là **thiết kế dự kiến, chưa được code hóa** — hiện KHÔNG có cách nào qua API để đính kèm giấy tờ cho 1 hồ sơ Người có công hay Thân nhân.

**Phần ĐÃ hoạt động thật:** chỉ riêng `VisitSchedule` (mục 7) implement `HasMedia`, collection `visit_evidence` — upload qua `PATCH /beneficiary-visit-schedules/{id}/status` (field `evidence[]`, chỉ dùng khi `status=done`), không có endpoint upload rời.

Nếu màn hình FE cần đính kèm giấy tờ (CCCD, quyết định công nhận, giấy chứng tử...) cho NCC/Thân nhân — đây là việc BE cần bổ sung trước (thêm `HasMedia` + `registerMediaCollections()` vào 2 model + route upload qua `MediaService`), không phải việc FE có thể tự làm được với API hiện có.

---

## 9. Báo cáo & Thống kê & Xuất dữ liệu

### 9.1. Bảng tổng hợp toàn bộ endpoint `stats`/`export` (dùng dựng dashboard tổng quan)

| Resource | `GET .../stats` trả về | `GET .../export` (Excel) |
|---|---|---|
| `beneficiaries` | `{total, pending, active, deceased}` | Đầy đủ field index + `created_by/updated_by/created_at/updated_at/status` |
| `beneficiary-households` | `{total, total_members}` (`total_members` = SUM `member_count`) | Mã hộ, chủ hộ, CCCD chủ hộ, tổ dân phố, địa chỉ, tọa độ, SĐT, số thành viên, người tạo/sửa, ngày tạo/sửa |
| `beneficiary-residential-areas` | `{total, total_households}` | Tên, mã, số hộ, người tạo/sửa, ngày tạo/sửa |
| `beneficiary-dependents` | `{total, alive, deceased}` | Họ tên, ngày sinh, giới tính, CCCD, mã hộ, còn sống, người tạo/sửa, ngày tạo/sửa |
| `beneficiary-subsidy-policies` | `{total, effective}` (`effective` = còn hiệu lực) | Loại đối tượng, mức, đơn vị, căn cứ pháp lý, hiệu lực từ/đến, ngày tạo/sửa |
| `beneficiary-subsidy-grants` | — (không có `stats`) | — (không có `export` — chỉ xem qua `index` + tự tổng hợp phía FE, hoặc dùng báo cáo tổng hợp mục 9.2) |
| `beneficiary-visit-schedules` | — (không có `stats`) | — (không có `export`) |

Với `beneficiary-subsidy-grants`/`beneficiary-visit-schedules` (không có stats/export riêng): nếu cần báo cáo tổng hợp, FE gọi `index` với `limit=-1` + filter cần thiết rồi tự tổng hợp, hoặc yêu cầu BE bổ sung endpoint stats riêng nếu khối lượng dữ liệu lớn (không nên tự tổng hợp phía FE khi dữ liệu > vài nghìn dòng).

### 9.2. Số liệu báo cáo cần ghép từ nhiều nguồn (chưa có endpoint tổng hợp sẵn — FE/BE cần thống nhất thêm nếu làm màn hình báo cáo riêng)

- **Tổng kinh phí đang chi trả:** `SUM(beneficiary_subsidy_grants.amount) WHERE status=active` — hiện chỉ lấy được qua `GET /beneficiary-subsidy-grants?status=active&limit=-1` rồi cộng dồn phía FE (chưa có `stats` trả sẵn tổng tiền).
- **Báo cáo biến động (tăng/giảm) theo kỳ:** dùng `GET /beneficiaries/{id}/status-histories` hoặc `GET /beneficiary-dependents/{id}/status-histories` lọc theo `from_date`/`to_date` — hiện là **nested theo từng hồ sơ**, chưa có endpoint "danh sách biến động toàn phường theo khoảng thời gian" — nếu màn hình báo cáo cần liệt kê biến động toàn phường trong 1 kỳ, cần đề xuất BE bổ sung endpoint riêng (không suy ra được từ API hiện có mà không N+1 request).
- **Thống kê theo `BeneficiaryTypeEnum`** (số NCC mỗi loại trong 12 nhóm): chưa có endpoint group-by sẵn — `GET /beneficiaries/stats` chỉ group theo `status`, không group theo loại đối tượng. Cần đề xuất BE bổ sung nếu dashboard yêu cầu biểu đồ theo loại.

→ **Khuyến nghị cho FE:** với 2 nhóm báo cáo tổng hợp toàn phường (biến động theo kỳ, thống kê theo loại đối tượng), nên trao đổi thêm với BE để bổ sung endpoint thay vì tự ghép nhiều request `limit=-1` — dữ liệu người có công của cả phường có thể vài trăm–vài nghìn bản ghi, tổng hợp phía client sẽ chậm và không nhất quán khi có nhiều cán bộ cùng xem.

### 9.3. Import Excel — cột theo từng resource

| Resource | Cột bắt buộc | Cột không bắt buộc |
|---|---|---|
| `beneficiaries` | `full_name`, `gender` | `date_of_birth`, `id_number`, `status` (không có `classifications` — import không tạo được phân loại kèm theo, phải bổ sung tay sau khi import) |
| `beneficiary-households` | `head_name`, `address` | `household_code`, `head_id_number`, `phone` |
| `beneficiary-residential-areas` | `name` | `code` |
| `beneficiary-dependents` | `full_name`, `gender` | `date_of_birth`, `id_number` |
| `beneficiary-subsidy-policies` | `amount`, `legal_basis`, `effective_from` | `beneficiary_type`, `unit` |

Cột file **phải khớp header cột Excel export tương ứng** (tải file export mẫu rồi điền lại là cách an toàn nhất, thay vì tự tạo file từ đầu).

---

## 10. Luồng màn hình đề xuất cho FE

```
1. [Setup 1 lần] Màn hình Danh mục
   ├─ Tổ dân phố (1.1) — CRUD đơn giản
   └─ Chính sách trợ cấp (1.2) — CRUD + nút "Ban hành mức mới" (renew)

2. [Luồng chính] Tiếp nhận hồ sơ mới
   ├─ Form 1 màn hình duy nhất: Người có công + Phân loại + (tùy chọn) Hộ gia đình mới +
   │  (tùy chọn) danh sách Thân nhân đi kèm
   │  → POST /beneficiaries với household/dependents lồng (mục 3.4)
   │  → Nếu hộ đã có sẵn: chọn household_id thay vì gửi household object
   ├─ (Chưa làm được) Upload giấy tờ — xem cảnh báo mục 8, API chưa tồn tại cho NCC/Thân nhân
   └─ PATCH /{id}/status → active (không có điều kiện chặn giấy tờ ở BE hiện tại, mục 3.3)

3. [Quản lý tiếp diễn] Danh sách + chi tiết
   ├─ Danh sách NCC (index) — filter theo status/household_id, hiển thị dependents_count,
   │  active_subsidy_grants_count ngay trên list (đã có sẵn, không cần gọi thêm API/hồ sơ)
   ├─ Chi tiết NCC — tab Phân loại (sync qua PUT, mục 3.2), tab Thân nhân/Quan hệ (mục 4-5),
   │  tab Trợ cấp (mục 6), tab Lịch sử trạng thái (chưa có tab Giấy tờ — xem mục 8)
   └─ Quản lý Hộ gia đình / Thân nhân độc lập (mục 2, 4) khi KHÔNG phát sinh từ luồng tạo mới

4. [Nghiệp vụ định kỳ] Trợ cấp & Lịch viếng thăm
   ├─ Cấp trợ cấp (mục 6) khi hồ sơ/quan hệ đủ điều kiện
   ├─ Ban hành mức mới → renew (tự động nối tiếp mọi grant, không thao tác tay từng dòng)
   └─ Danh sách lịch viếng thăm (mục 7) — lọc theo assigned_to (cán bộ đang đăng nhập),
      đánh dấu done/skipped kèm ảnh xác nhận

5. [Báo cáo] xem mục 9 — phần lớn dựng được từ index + filter, 2 nhóm cần BE bổ sung thêm
   endpoint nếu làm dashboard tổng hợp toàn phường.
```

---

## 11. Bảng tra cứu Enum đầy đủ

### 11.1. `BeneficiaryTypeEnum` — Loại đối tượng (12 nhóm, Pháp lệnh 02/2020/UBTVQH14)

| `value` | `label` |
|---|---|
| `pre_revolution_1945` | Người hoạt động cách mạng trước ngày 01/01/1945 |
| `revolution_1945_to_1945_uprising` | Người hoạt động cách mạng từ 01/01/1945 đến Khởi nghĩa tháng Tám năm 1945 |
| `martyr` | Liệt sĩ |
| `vietnamese_heroic_mother` | Bà mẹ Việt Nam anh hùng |
| `hero_of_armed_forces` | Anh hùng Lực lượng vũ trang nhân dân |
| `hero_of_labor` | Anh hùng Lao động trong thời kỳ kháng chiến |
| `war_invalid` | Thương binh, người hưởng chính sách như thương binh |
| `disease_invalid` | Bệnh binh |
| `agent_orange_victim` | Người hoạt động kháng chiến bị nhiễm chất độc hóa học |
| `former_prisoner` | Người hoạt động cách mạng, kháng chiến, bảo vệ Tổ quốc, làm nghĩa vụ quốc tế bị địch bắt tù, đày |
| `resistance_activist` | Người hoạt động kháng chiến giải phóng dân tộc, bảo vệ Tổ quốc, làm nghĩa vụ quốc tế |
| `revolution_supporter` | Người có công giúp đỡ cách mạng |

### 11.2. `BeneficiaryStatusEnum`

| `value` | `label` |
|---|---|
| `pending` | Chờ công nhận |
| `active` | Đang hưởng |
| `deceased` | Đã mất |
| `moved_out` | Đã chuyển đi |
| `suspended` | Tạm dừng |

### 11.3. `GenderEnum`

`male` = Nam · `female` = Nữ · `other` = Khác

### 11.4. `DependentEligibilityEnum`

`normal` = Bình thường · `studying` = Đang đi học · `disabled_no_work_capacity` = Mất khả năng lao động

### 11.5. `DependentRelationshipEnum`

`spouse` = Vợ/Chồng · `child` = Con · `father` = Cha · `mother` = Mẹ · `foster_parent` = Người nuôi dưỡng · `guardian` = Người giám hộ

### 11.6. `DependentRelationStatusEnum`

`active` = Đang hưởng · `expired` = Hết điều kiện hưởng · `suspended` = Tạm dừng

### 11.7. `SubsidyStatusEnum`

`active` = Đang chi trả · `terminated` = Đã dừng · `suspended` = Tạm dừng

### 11.8. `DocumentTypeEnum` (custom property của Media)

`decision` = Quyết định công nhận · `id_card` = Giấy tờ tùy thân · `death_certificate` = Giấy chứng tử · `medical_certificate` = Giấy chứng nhận y tế · `other` = Khác

> Enum này tồn tại trong code nhưng hiện **chưa gắn được vào Beneficiary/Dependent** (xem cảnh báo mục 8) — chỉ ghi chú ở đây để dùng sau khi BE bổ sung tính năng upload giấy tờ.

### 11.9. `VisitOccasionEnum`

`tet` = Tết Nguyên đán · `war_invalids_day_27_7` = Ngày Thương binh - Liệt sĩ 27/7 · `birthday` = Sinh nhật · `custom` = Khác

### 11.10. `ScheduleStatusEnum`

`pending` = Chờ thực hiện · `done` = Đã thực hiện · `skipped` = Bỏ qua

Tất cả enum trên đều có field `xxx_label` đi kèm sẵn trong response resource — khi hiển thị dữ liệu đã có (index/show), FE ưu tiên dùng `label` từ response đó. Khi cần dropdown lúc chưa có data (VD form tạo mới), gọi `GET /api/beneficiary-enums` (mục 1.3) thay vì hardcode — bảng trên chỉ còn dùng để tham khảo nhanh giá trị nào ứng với label nào.

---

## 12. Checklist lỗi thường gặp khi tích hợp

- [ ] Quên header `X-Organization-Id` → mọi request nghiệp vụ trả `422`.
- [ ] Gửi cả `household_id` VÀ `household` khi tạo NCC → `422` (mục 3.4).
- [ ] Nghĩ rằng bỏ 1 dòng `classifications` ra khỏi payload `PUT` là xóa được nó → **sai**, phải đưa `id` vào `classifications_deleted` (mục 3.2).
- [ ] Dùng alias `"beneficiary"`/`"dependent"` khi lọc `GET /beneficiary-subsidy-grants?subject_type=...` → không match được gì vì DB lưu FQCN (mục 6).
- [ ] Tự set `eligible_until` khi tạo/sửa quan hệ hưởng chế độ → field này chỉ Job hệ thống ghi, FE gửi lên sẽ bị bỏ qua (không có trong rule cho phép).
- [ ] Gọi `PATCH /beneficiaries/{id}/status` để dừng trợ cấp thủ công khi NCC mất/chuyển đi → **không cần**, BE tự dừng kèm theo (mục 3.3).
- [ ] Tự tính lại `member_count` của hộ ở FE → luôn hiển thị giá trị BE trả, không suy ra từ danh sách thành viên đang có trên client (có thể lệch do phân trang).
- [ ] Gọi `POST /beneficiary-visit-schedules` để tạo lịch mới → route này **không tồn tại**, lịch chỉ do BE tự sinh (mục 7).

---

*Tài liệu này tổng hợp từ code thực tế tại thời điểm viết (routes, FormRequest, Resource, Service — không suy đoán từ thiết kế nháp). Nếu BE có thay đổi API sau thời điểm này, cập nhật lại tài liệu theo đúng timestamp ở đầu file (CLAUDE.md §10).*
