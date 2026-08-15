# Module Người có công — Thiết kế lại (v2, đơn giản hoá)

> Ngày tạo: 11:00:31 15/08/2026  
> Cập nhật lần cuối: 11:42:00 15/08/2026

**Trạng thái:** ĐÃ CHỐT 5 quyết định mở (§7) — sẵn sàng code.  
**Phạm vi:** chỉ Backend (`core-be`). Frontend `core-fe/src/modules/nguoicocong` **chưa đụng đến** theo yêu cầu.

**Các quyết định đã chốt ngày 15/08/2026:**

| # | Vấn đề | Chốt |
|---|---|---|
| 1 | Thân nhân 1–n hay n–n | **1–n** — dòng con trực thuộc một hồ sơ (§7.1) |
| 2 | Cột `status` | **Bảng chính bỏ, 3 danh mục có** — và **nới CLAUDE.md B3** để `status` là tuỳ nghiệp vụ, không mặc định bắt buộc (§7.5, §4.6) |
| 3 | Disk lưu tệp | **Disk mặc định `public`** — không dùng `private`, không có endpoint tải riêng (§7.7) |
| 4 | Cột danh mục | **`name` + `note` + `sort_order`**, **không** có `code` (§4.6) |
| 5 | Dữ liệu v1 trong DB | Không cần giữ — `migrate:fresh` thoải mái, không viết script chuyển đổi |

---

## 1. Bối cảnh

Module `Beneficiary` cũ đã được **gỡ bỏ hoàn toàn** khỏi `core-be` ngày 15/08/2026 (chi tiết ở §9). Lý do: mô hình cũ dựng theo trục **hộ gia đình** với 7 bảng, quan hệ n–n giữa người có công và thân nhân, thêm các nhánh trợ cấp (subsidy) / lịch thăm hỏi (visit-schedule) / báo cáo biến động — vượt xa nhu cầu thực tế của cán bộ nhập liệu.

Thiết kế v2 bỏ trục hộ gia đình, quay về mô hình **một hồ sơ = một người có công**, kèm ba danh sách con và ba danh mục.

---

## 2. Yêu cầu nghiệp vụ (nguyên văn)

**Người có công:** Họ và tên, ngày tháng năm sinh, năm sinh, giới tính, CCCD/CMND, số điện thoại, tổ dân phố/thôn, địa chỉ, vĩ độ, kinh độ, ghi chú.

**Đối tượng** — một người có thể thuộc nhiều đối tượng, cho phép chọn **đối tượng chính**:
- Loại đối tượng (danh mục)
- Tập tin đính kèm (nhiều)

**Thân nhân** — có thể có nhiều thân nhân, cho phép chọn **thân nhân chính**:
- Họ và tên, ngày tháng năm sinh, năm sinh, giới tính, CCCD/CMND, số điện thoại, tổ dân phố/thôn, địa chỉ, vĩ độ, kinh độ, ghi chú, mối quan hệ (danh mục)

**Tài liệu** — có thể có nhiều tài liệu:
- Tên tài liệu, tệp đính kèm (nhiều)

**Danh mục:** Tổ dân phố/Thôn, Mối quan hệ, Loại đối tượng.

---

## 3. So sánh v1 (đã xoá) ↔ v2

| Khái niệm | v1 | v2 | Lý do |
|---|---|---|---|
| Hộ gia đình (`beneficiary_households`) | Bảng chính, người có công & thân nhân đều trỏ vào hộ; có `member_count` denormalized + Observer đồng bộ | **Bỏ** | Không có trong yêu cầu. Kéo theo bỏ luôn `HouseholdObserver` và toàn bộ logic đếm thành viên |
| Thân nhân | Bảng riêng + bảng nối n–n (`beneficiary_dependent_relations`) — một thân nhân dùng chung cho nhiều người có công | **1–n**: thân nhân là dòng con trực thuộc một hồ sơ | Đơn giản hoá — xem đánh đổi §7.1 |
| Loại đối tượng | `BeneficiaryTypeEnum` (enum cứng) + bảng `beneficiary_classifications` mang số/ngày quyết định | **Danh mục DB** (`beneficiary_types`) + bảng nối mang `is_primary` và tệp đính kèm | Yêu cầu ghi rõ "Loại đối tượng" là **danh mục**; các trường quyết định không còn trong yêu cầu |
| Mối quan hệ | `DependentRelationshipEnum` (enum cứng, đã phải viết migration tách `spouse` → `wife`/`husband`) | **Danh mục DB** (`beneficiary_relationships`) | Enum cứng buộc phải deploy code mỗi lần nghiệp vụ thêm quan hệ mới |
| Trợ cấp, lịch thăm hỏi, báo cáo biến động, thống kê | Có (`subsidy-*`, `visit-schedule`, `beneficiary-report`, `StatisticsController`) | **Bỏ** | Ngoài phạm vi yêu cầu v2 |
| Tệp đính kèm | Qua `Core\Services\MediaService` | Gọi spatie thẳng trong Service theo **B5** | v2 là module mới → bắt buộc theo `docs/system/QUAN_HE_CHA_CON.md` |
| Bảng | 7 | **7** (nhưng 3 trong đó là danh mục phẳng, không quan hệ chéo) | |

---

## 4. Mô hình dữ liệu

### 4.1. ERD

```
beneficiary_residential_areas  (danh mục: Tổ dân phố/Thôn)
beneficiary_types              (danh mục: Loại đối tượng)
beneficiary_relationships      (danh mục: Mối quan hệ)
        │  │  │
        │  │  └────────────────────────────┐
        │  └──────────────┐                │
        ↓ (restrict)      ↓ (restrict)     ↓ (restrict)
   beneficiaries ─┬─< beneficiary_type_relations   [dạng D] + media(attachments)
   (BẢNG CHÍNH)   │
                  ├─< beneficiary_dependents        [dạng B]
                  │
                  └─< beneficiary_documents         [dạng A] + media(files)
```

**Phân dạng quan hệ theo `docs/system/QUAN_HE_CHA_CON.md` §2:**

| Bảng con | Dạng | Nhận biết | Bộ action |
|---|---|---|---|
| `beneficiary_type_relations` | **D** (n–n có thuộc tính) | Bảng nối `beneficiaries` ↔ `beneficiary_types`, mang cột nghiệp vụ `is_primary` + có tệp đính kèm | 6 — xử lý **y hệt dạng A**, **cấm** `sync()` |
| `beneficiary_dependents` | **B** (1–n không tệp) | `hasMany`, chỉ có cột dữ liệu | 6 |
| `beneficiary_documents` | **A** (1–n có tệp) | `hasMany`, mỗi dòng có nhiều tệp | 6 |
| 3 bảng danh mục | — | Tenant-scoped, cán bộ tự quản trị trong module | 11 (bảng chính, xem §7.3) |

### 4.2. `beneficiaries` — bảng chính

| Cột | Kiểu | Ràng buộc | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | | |
| `organization_id` | FK → `organizations` | `cascadeOnDelete`, NOT NULL | Tenant, `TenantModel` tự gán |
| `full_name` | string(191) | **required** | Họ và tên |
| `birth_date` | date | nullable | Ngày tháng năm sinh |
| `birth_year` | smallint unsigned | nullable | Năm sinh — dùng khi không rõ ngày/tháng (§7.2) |
| `gender` | string(20) | nullable, `GenderEnum` | `male` / `female` / `other` |
| `id_number` | string(20) | nullable, **UNIQUE(`organization_id`, `id_number`)** | CCCD/CMND (§7.4) |
| `phone` | string(20) | nullable | Số điện thoại |
| `residential_area_id` | FK → `beneficiary_residential_areas` | nullable, `restrictOnDelete` | Tổ dân phố/Thôn |
| `address` | string(255) | nullable | Địa chỉ |
| `latitude` | decimal(10,7) | nullable | Vĩ độ, −90 … 90 |
| `longitude` | decimal(10,7) | nullable | Kinh độ, −180 … 180 |
| `note` | text | nullable | Ghi chú |
| `created_by` / `updated_by` | FK → `users` | nullable, `nullOnDelete` | |
| `created_at` / `updated_at` | timestamps | | `updated_at` là nguồn của `lock_version` |
| `deleted_at` | softDeletes | | **Bắt buộc** — bảng con cascade (B5 điều 1) |

**Không có cột `status`** — xem §7.5.

**Index:** `(organization_id, residential_area_id)`, `(organization_id, full_name)`, `(organization_id, birth_year)`.

**Media:** không có. Tệp chỉ nằm ở `beneficiary_type_relations` và `beneficiary_documents`.

### 4.3. `beneficiary_type_relations` — Đối tượng (dạng D)

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | bigint PK | Khoá chính riêng — **không** `extends Pivot` (spatie cần model có id để gắn media, và cần model event để `$touches` nổ) |
| `organization_id` | FK | `cascadeOnDelete` |
| `beneficiary_id` | FK → `beneficiaries` | `cascadeOnDelete` |
| `beneficiary_type_id` | FK → `beneficiary_types` | **`restrictOnDelete`** — đang được dùng thì không cho xoá danh mục |
| `is_primary` | boolean | default `false` — đối tượng chính (§7.6) |
| `created_by` / `updated_by` / timestamps / `deleted_at` | | |

**UNIQUE:** `(beneficiary_id, beneficiary_type_id)` — một người không thuộc cùng một loại hai lần. Có UNIQUE + SoftDeletes → **bắt buộc nhánh `withTrashed()->restore()`** thay vì `create()` (B5 điều 4).  
**Index:** `(organization_id, beneficiary_id)`.  
**Media collection:** `attachments` (nhiều tệp, disk mặc định `public`).  
**`$touches = ['beneficiary']`.**

### 4.4. `beneficiary_dependents` — Thân nhân (dạng B)

Cột dữ liệu cá nhân giống hệt `beneficiaries` (`full_name`, `birth_date`, `birth_year`, `gender`, `id_number`, `phone`, `residential_area_id`, `address`, `latitude`, `longitude`, `note`), cộng thêm:

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `beneficiary_id` | FK → `beneficiaries` | `cascadeOnDelete` |
| `relationship_id` | FK → `beneficiary_relationships` | nullable, `restrictOnDelete` — Mối quan hệ |
| `is_primary` | boolean | default `false` — thân nhân chính (§7.6) |

**Không UNIQUE trên `id_number`** — hệ quả trực tiếp của việc chuyển n–n → 1–n (§7.1).  
**Index:** `(organization_id, beneficiary_id)`.  
**Không có media** (dạng B).  
**`$touches = ['beneficiary']`.**

### 4.5. `beneficiary_documents` — Tài liệu (dạng A)

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `beneficiary_id` | FK → `beneficiaries` | `cascadeOnDelete` |
| `name` | string(191) | **required** — Tên tài liệu |

**Media collection:** `files` (nhiều tệp, disk mặc định `public`).  
**Index:** `(organization_id, beneficiary_id)`. **`$touches = ['beneficiary']`.**

### 4.6. Ba bảng danh mục

Cùng một khuôn: `beneficiary_residential_areas`, `beneficiary_types`, `beneficiary_relationships`.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` / `organization_id` / `created_by` / `updated_by` / timestamps / `deleted_at` | | Chuẩn |
| `name` | string(191) | **required**, **UNIQUE(`organization_id`, `name`)** |
| `note` | text | nullable |
| `sort_order` | int | NOT NULL, default `0` |
| `status` | string(20) | NOT NULL, default `active` — `CatalogStatusEnum` |

**Danh mục CÓ `status`, bảng chính thì không** (§7.5). Đây không phải mâu thuẫn mà đúng theo B3 mới: danh mục thực sự có trạng thái nghiệp vụ, hồ sơ người có công thì không.

Trạng thái danh mục giải quyết bài toán **ngừng dùng mà không xoá được**: một tổ dân phố sau sáp nhập, hay một loại đối tượng theo chính sách cũ, không được xoá vì đang bị hàng trăm hồ sơ tham chiếu (`restrictOnDelete`), nhưng cũng không nên hiện trong dropdown khi nhập hồ sơ mới.

| | `active` | `inactive` |
|---|---|---|
| Hiện trong dropdown chọn khi tạo/sửa hồ sơ | Có | **Không** |
| Hồ sơ cũ đang tham chiếu | Giữ nguyên, vẫn hiển thị bình thường | **Giữ nguyên, vẫn hiển thị bình thường** |
| Lọc/thống kê theo danh mục đó | Có | Có |
| Import Excel khớp theo `name` | Có | **Không** — coi như không khớp, để trống, không chặn dòng |

Quy tắc quan trọng nhất: **`inactive` không làm hỏng dữ liệu cũ.** Chỉ chặn *gán mới*, không đụng bản ghi đã gán. Validate `residential_area_id` / `beneficiary_type_id` / `relationship_id` khi `store`/`update` phải kiểm `status = active`; khi `show`/`index` thì không kiểm.

**Không có cột `code`** (chốt ngày 15/08/2026 — `code` dư thừa khi `name` đã đủ định danh). Hai hệ quả bắt buộc:

1. **`name` phải UNIQUE theo tổ chức.** Vì `name` giờ là định danh duy nhất, import Excel tra ngược danh mục hoàn toàn dựa vào nó — hai dòng "Thương binh" trùng tên sẽ khiến import tra ra dòng nào cũng đúng như nhau, tức là sai không xác định được.
2. **UNIQUE + SoftDeletes → cần nhánh restore.** Dòng danh mục đã xoá mềm vẫn chiếm chỗ trong unique index; `store()` và `import()` phải `withTrashed()` → `restore()` thay vì `create()` (B5 điều 4). Cùng bẫy với §7.4.

Khi tra ngược lúc import: chuẩn hoá **trim + so sánh không phân biệt hoa/thường** trước khi khớp `name`, để cán bộ gõ "thương binh" vẫn ra "Thương binh". Không khớp thì để trống, **không chặn dòng**.

`sort_order` để cán bộ tự đặt thứ tự hiển thị trong dropdown → kèm action `PATCH /reorder` (chuẩn B3). Mặc định sắp xếp `index` theo `sort_order ASC, name ASC`.

Danh mục tenant-scoped (`organization_id` NOT NULL), **không** phải dạng E của B5 — lý do ở §7.3.

---

## 5. API

Đăng ký trong `routes/api.php`, trong nhóm `auth:sanctum`. Route tĩnh (`/save-full`, `/bulk-delete`, `/reorder`, `/stats`, `/export`, `/import`, `/import-template`) khai báo **trước** `/{id}`, và `{id}` có `->whereNumber()`.

### 5.1. Bảng chính — `/api/beneficiaries` (`ensure.route.org`)

Bộ chuẩn B3 **+ `saveFull`**, **trừ** nhóm trạng thái (module không có `status` — §7.5):

| Action | Method | Route | Permission |
|---|---|---|---|
| stats | GET | `/stats` | `beneficiaries.stats` |
| index | GET | `/` | `beneficiaries.index` |
| show | GET | `/{id}` | `beneficiaries.show` |
| store | POST | `/` | `beneficiaries.store` |
| update | PUT | `/{id}` | `beneficiaries.update` |
| destroy | DELETE | `/{id}` | `beneficiaries.destroy` |
| bulkDestroy | DELETE | `/bulk-delete` | `beneficiaries.bulkDestroy` |
| export | GET | `/export` | `beneficiaries.export` |
| import | POST | `/import` | `beneficiaries.import` |
| importTemplate | GET | `/import-template` | `beneficiaries.import` (dùng chung) |
| **saveFull** | POST | `/save-full` | `beneficiaries.store` / `.update` (dùng chung, **không** tạo permission riêng) |
| ~~changeStatus~~ | — | — | **Không có** |
| ~~bulkUpdateStatus~~ | — | — | **Không có** |

**Bộ lọc `index`:** `search` (họ tên / CCCD / SĐT), `residential_area_id`, `beneficiary_type_id` (lọc qua bảng nối), `relationship_id` (lọc qua thân nhân), `gender`, `birth_year_from` / `birth_year_to`, `from_date` / `to_date` (theo `created_at`), `sort_by` ∈ {`id`, `full_name`, `birth_date`, `birth_year`, `created_at`, `updated_at`}, `sort_order`, `limit`. **Không có** bộ lọc `status`.

**`stats` trả gì khi không có `status`:** tổng số hồ sơ, số hồ sơ theo từng loại đối tượng, theo tổ dân phố, theo giới tính, số hồ sơ có toạ độ / thiếu toạ độ (phục vụ bản đồ), số hồ sơ mới trong 30 ngày.

### 5.2. Sub-resource — route lồng (bắt buộc theo B5)

| Resource | Prefix | Action |
|---|---|---|
| Đối tượng | `/api/beneficiaries/{beneficiary}/type-relations` | `index, show, store, update, destroy, bulkDestroy` |
| Thân nhân | `/api/beneficiaries/{beneficiary}/dependents` | `index, show, store, update, destroy, bulkDestroy` |
| Tài liệu | `/api/beneficiaries/{beneficiary}/documents` | `index, show, store, update, destroy, bulkDestroy` |

`beneficiary_id` **luôn gán qua quan hệ**, không nhận từ body — đây là cơ chế chặn IDOR. Không có `stats` / `export` / `import` / `changeStatus` cho bảng con (B5 §1.1).

### 5.3. Danh mục

| Resource | Prefix | Action |
|---|---|---|
| Tổ dân phố/Thôn | `/api/beneficiary-residential-areas` | Bộ đầy đủ + `PATCH /reorder` + `import-template` |
| Loại đối tượng | `/api/beneficiary-types` | như trên |
| Mối quan hệ | `/api/beneficiary-relationships` | như trên |

Cụ thể: `stats, index, show, store, update, destroy, bulkDestroy, bulkUpdateStatus, changeStatus, reorder, export, import, importTemplate`. Danh mục **có** nhóm trạng thái (§4.6) nên có đủ `changeStatus` (`PATCH /{id}/status`) và `bulkUpdateStatus` (`PATCH /bulk-status`) — khác bảng chính (§7.5).

**Bộ lọc `index` của danh mục:** `search` (theo `name`), `status`, `from_date` / `to_date`, `sort_by` ∈ {`id`, `name`, `sort_order`, `created_at`, `updated_at`}, `sort_order`, `limit`. Mặc định `sort_order ASC, name ASC`.

**FE dựng dropdown phải gọi kèm `status=active`** — endpoint không tự lọc, vì màn quản trị danh mục cần thấy cả dòng `inactive`.

**`destroy` / `bulkDestroy` phải chặn khi danh mục đang được dùng** (`restrictOnDelete`): bắt `QueryException` → trả **409** kèm số lượng bản ghi đang tham chiếu và gợi ý *"chuyển sang Ngừng sử dụng nếu chỉ muốn ẩn khỏi dropdown"* — đây là lý do tồn tại của cột `status`, nói thẳng cho cán bộ thay vì để họ bế tắc trước lỗi 409.

### 5.4. Enum lookup

`GET /api/beneficiary-enums` — **không** `ensure.route.org`, **không** `permission:` (theo B2).

```json
{ "success": true, "data": {
  "gender": [{"value":"male","label":"Nam"}, {"value":"female","label":"Nữ"}, {"value":"other","label":"Khác"}],
  "catalog_status": [{"value":"active","label":"Đang sử dụng"}, {"value":"inactive","label":"Ngừng sử dụng"}]
}}
```

Module có **hai** Enum: `GenderEnum` (dùng cho `beneficiaries` và `beneficiary_dependents`) và `CatalogStatusEnum` (dùng cho cả ba danh mục — §4.6). Bảng chính không có trạng thái nên không có enum tương ứng (§7.5).

Nhãn cố ý là "Đang sử dụng / Ngừng sử dụng" chứ không phải "Hoạt động / Ngừng hoạt động" — nói đúng cái mà trạng thái này điều khiển: mục danh mục còn được chọn khi nhập hồ sơ mới hay không.

Loại đối tượng và Mối quan hệ **không** ở đây — chúng là danh mục DB, FE gọi `/api/beneficiary-types` và `/api/beneficiary-relationships`.

### 5.5. `save-full` — payload

Màn hình form trọn gói (một trang nhập đủ hồ sơ + 3 danh sách con). Danh sách con gửi dưới dạng **chuỗi JSON**, không phải mảng lồng FormData (B5 điều 6 — `max_input_vars` cắt đuôi payload im lặng, và mảng lồng không phân biệt được `"[]"` = xoá hết với vắng mặt = không quản lý).

```
POST /api/beneficiaries/save-full        (multipart/form-data)

id                      (null = tạo mới)
lock_version            (bắt buộc khi cập nhật — ISO8601)
full_name, birth_date, birth_year, gender, id_number, phone,
residential_area_id, address, latitude, longitude, note

type_relations_json     '[{"id":null,"beneficiary_type_id":3,"is_primary":true,"keep_media_ids":[]}]'
dependents_json         '[{"id":12,"full_name":"...","relationship_id":2,"is_primary":true, ...}]'
documents_json          '[{"id":null,"name":"Quyết định trợ cấp","keep_media_ids":[7,8]}]'

type_relations_files[0][]   (tệp mới của dòng đối tượng thứ 0)
documents_files[0][]        (tệp mới của dòng tài liệu thứ 0)
```

**Quy tắc thực thi trong `saveFull()`:**
1. `DB::transaction()` — đọc lại bản chính kèm `lockForUpdate()` **bên trong** transaction, so `lock_version` bằng `->timestamp` (B5 điều 5).
2. **Không tự ghi bản chính** — gọi lại `BeneficiaryService::update()` (B5 quy tắc 3).
3. Với mỗi danh sách: upsert dòng có `id`, tạo dòng không có `id`, `whereNotIn(ids)->delete()` phần còn lại. `whereNotIn()->delete()` chạy qua Query Builder nên **không** nổ `$touches` → phải `$beneficiary->touch()` tay (B5 điều 2).
4. Media theo đúng thứ tự: **snapshot `getMedia()` → commit → `addMedia()` → mới xoá tệp cũ** (B5 điều 3). Có `try/catch` cleanup tệp khi lỗi.
5. Fire event ở **cuối** `saveFull()` sau khi ghi/xoá tệp xong.

**Cấm gọi `save-full` từ màn hình có phân trang** — `whereNotIn` sẽ xoá mềm sạch phần chưa load và vẫn trả 200. Backend không chặn được điều này; đây là ràng buộc FE phải giữ.

---

## 6. Export / Import (bảng chính)

**Export** — đủ trường như `index`, kèm quan hệ:

| Nhóm | Cột |
|---|---|
| Dữ liệu | Họ và tên, Ngày sinh, Năm sinh, Giới tính, CCCD/CMND, Số điện thoại, Địa chỉ, Vĩ độ, Kinh độ, Ghi chú |
| Quan hệ N–1 (xuất **tên**, không xuất `*_id`) | Tổ dân phố/Thôn |
| Quan hệ 1–N / N–N (liệt kê 1 ô, ngăn bởi `; `) | **Danh sách loại đối tượng** = `Thương binh; Bệnh binh` · **Danh sách thân nhân** = `Nguyễn Văn A (Con); Trần Thị B (Vợ)` · **Danh sách tài liệu** = `Quyết định trợ cấp; Giấy chứng nhận` |
| Hệ thống | `created_by`, `updated_by`, `created_at`, `updated_at` |

Không có cột "Trạng thái" (§7.5). Các cột liệt kê 1–N/N–N chỉ để **đọc đối chiếu** — import **bỏ qua**, và đặt tên header khác cột nhập liệu ("Danh sách …") để cán bộ không nhầm.

**Import** — file phẳng, nhận **mọi** trường của Export trừ các cột mảng lồng:
- Bắt buộc tối thiểu: **`full_name`**. Mọi cột khác `nullable`.
- **Tổ dân phố/Thôn nhập bằng TÊN** (danh mục không có `code` — §4.6), `model()` tra ngược về `residential_area_id` với chuẩn hoá trim + không phân biệt hoa/thường; không khớp thì để trống, **không chặn dòng**.
- Giới tính: chấp nhận cả value gốc (`male`) lẫn nhãn tiếng Việt (`Nam`) — chuẩn hoá trong `prepareForValidation`.
- Trùng CCCD với dòng đã xoá mềm → `withTrashed()` → `restore()` + `update()`, không `create()` (§7.4).
- Lỗi tổng hợp ra **file Excel** qua `Controller::importResult($failures, 'người có công', BeneficiaryImport::FIELD_LABELS)` — không tự implement lại.
- File mẫu qua `Core\Exports\ImportTemplateExport` với `TEMPLATE_LABELS`, `TEMPLATE_EXAMPLES`, `REQUIRED_KEYS`, `templateNotes()`, `templateOptions()`.

Ba danh mục cũng có export/import riêng theo cùng khuôn — cột: **Tên, Ghi chú, Thứ tự, Trạng thái** (`REQUIRED_KEYS = ['name']`). Cột Trạng thái nhận cả value gốc (`active`) lẫn nhãn tiếng Việt (`Đang sử dụng`), để trống thì mặc định `active`; `templateNotes()` liệt kê đủ giá trị qua `NormalizesImportValues::enumHint(CatalogStatusEnum::cases())`.

**Import hồ sơ chỉ khớp danh mục đang `active`** (§4.6): dòng Excel ghi tên một tổ dân phố đã `inactive` sẽ để trống ô đó chứ không gán — nhất quán với việc dropdown cũng không cho chọn. Không chặn dòng.

---

## 7. Quyết định thiết kế & đánh đổi

### 7.1. Thân nhân là 1–n, không phải n–n ✅ ĐÃ CHỐT 15/08/2026

Yêu cầu viết "Thân nhân → có thể có nhiều thân nhân" — đọc theo nghĩa danh sách con trực thuộc một hồ sơ. v1 làm n–n (một thân nhân dùng chung cho nhiều người có công).

| | 1–n (đề xuất) | n–n (như v1) |
|---|---|---|
| Số bảng | 1 | 2 (bảng thân nhân + bảng nối) |
| Hai người có công là vợ chồng, cùng khai một người con | Người con bị **nhập 2 lần**, 2 dòng độc lập | Một dòng, hai liên kết |
| Sửa thông tin người con | Phải sửa ở cả 2 hồ sơ | Sửa một chỗ |
| UNIQUE trên CCCD thân nhân | **Không thể có** | Có |
| Độ phức tạp code | Thấp | Cao (bảng nối + restore + đồng bộ) |

**Đã chốt: 1–n.** Đánh đổi được chấp nhận có ý thức — **dữ liệu thân nhân có thể trùng lặp giữa các hồ sơ, sửa phải sửa nhiều chỗ, và không chặn được trùng CCCD thân nhân.**

Dấu hiệu cần xem lại quyết định này về sau: nghiệp vụ bắt đầu hỏi "người này là thân nhân của những ai", hoặc cần thống kê số **người** (không phải số **dòng**) thân nhân toàn xã. Lúc đó việc chuyển 1–n → n–n là một migration gộp dòng trùng theo CCCD, không phải viết lại module.

### 7.2. `birth_date` và `birth_year` cùng tồn tại

Yêu cầu liệt kê cả hai. Quy tắc đề xuất:
- Cả hai đều `nullable`.
- Nhập `birth_date` → service **tự suy ra** `birth_year` (không bắt cán bộ nhập hai lần, không để lệch).
- Chỉ biết năm → nhập `birth_year`, để trống `birth_date`.
- Nhập cả hai mà lệch nhau → validate báo lỗi.

Lọc/thống kê theo tuổi dùng `birth_year` (luôn có dữ liệu khi biết năm), không dùng `birth_date`.

### 7.3. Ba danh mục là bảng chính có đủ CRUD, không phải dạng E

Dạng E của B5 dành cho danh mục **dùng chung nhiều module** với `organization_id = NULL`. Ba danh mục ở đây chỉ dùng trong module này, mỗi tổ chức có danh sách tổ dân phố riêng, và cán bộ phải tự thêm/sửa/nhập Excel → tenant-scoped, đủ CRUD. Cùng khuôn với `meeting-types`, `task-assignment-types` đang chạy.

Xoá một mục danh mục đang được tham chiếu bị **chặn** (`restrictOnDelete`), không cascade — trả 409 với thông báo rõ đang bị bao nhiêu hồ sơ dùng.

Bộ action của danh mục theo B3 **sau khi nới** (§7.5): 9 action chuẩn + nhóm trạng thái (`changeStatus`, `bulkUpdateStatus`) + `reorder`. Danh mục là chỗ trong module này thực sự có trạng thái nghiệp vụ — xem §4.6.

### 7.4. UNIQUE CCCD trên `beneficiaries` + SoftDeletes → cần nhánh restore

`UNIQUE(organization_id, id_number)` với `id_number` nullable. Dòng đã xoá mềm **vẫn chiếm chỗ** trong unique index (đưa `deleted_at` vào unique không cứu được — MySQL coi mọi `NULL` là khác nhau). Vì vậy `store()` và `import()` phải `withTrashed()` → `restore()` + `update()` thay vì `create()` khi gặp CCCD đã tồn tại ở dòng đã xoá (B5 điều 4).

### 7.5. Bỏ cột `status` — và nới CLAUDE.md B3 ✅ ĐÃ CHỐT 15/08/2026

Yêu cầu nghiệp vụ không có khái niệm trạng thái. CLAUDE.md **B3** (bản cũ) lại bắt buộc mọi module mới có `status`, `changeStatus`, `bulkUpdateStatus` và bộ lọc `status`.

**Kiểm chứng trước khi quyết định** (thực hiện 15/08/2026): B3 là quy ước do team tự viết, **không** phải ràng buộc kỹ thuật của Laravel. Trong repo, mọi bảng nghiệp vụ hiện có đều có `status` (`meetings`, `meeting_types`, `meeting_locations`, `meeting_agendas`, `task_assignment_items`, `schedules`, `scheduling_employees`, `users`, `organizations`…); các bảng không có chỉ là bảng framework (`cache`, `jobs`, `media`), bảng con/pivot (`meeting_guests`, `schedule_participants`) và bảng cấu hình (`settings`). Đơn thư dùng tên nghiệp vụ `processing_status` chứ không phải ngoại lệ.

**Chốt: bỏ hẳn `status` khỏi module này, và sửa luôn CLAUDE.md B3** để quy ước phản ánh đúng nguyên tắc A2 ("không thêm chức năng ngoài yêu cầu"):

| | B3 cũ | B3 mới |
|---|---|---|
| Bộ bắt buộc | 11 action, luôn có `status` | 9 action: `stats, index, show, store, update, destroy, bulkDestroy, export, import` |
| `status`, `changeStatus`, `bulkUpdateStatus` | Bắt buộc | **Chỉ khi nghiệp vụ có trạng thái** — quyết định ở bước phân tích, ghi rõ trong PR |
| Bộ lọc `status` ở `index` | Bắt buộc | Chỉ khi module có `status` |

Năm module cũ giữ nguyên `status` — chúng có vì nghiệp vụ cần, không phải vì quy ước ép.

**Hệ quả — áp riêng cho từng bảng, đây chính là điều B3 mới cho phép:**

| | `beneficiaries` + 3 bảng con | 3 bảng danh mục |
|---|---|---|
| Cột `status` | **Không** | **Có** (`CatalogStatusEnum`) |
| `changeStatus` / `bulkUpdateStatus` | Không | Có |
| Permission `.changeStatus` / `.bulkUpdateStatus` | Không | Có |
| Bộ lọc `status` ở `index` | Không | Có |
| Cột "Trạng thái" khi Export/Import | Không | Có |

Lý do khác nhau: hồ sơ một người có công **không có trạng thái nghiệp vụ** — hoặc còn trong danh sách quản lý, hoặc bị xoá. Còn danh mục **có**: mục cũ phải ngừng dùng cho hồ sơ mới nhưng vẫn giữ nguyên cho hàng trăm hồ sơ đang tham chiếu, mà `restrictOnDelete` không cho xoá. Chi tiết ở §4.6.

**FE cần biết:** màn danh sách **hồ sơ** không có cột/bộ lọc "Trạng thái" — muốn ẩn tạm một hồ sơ thì xoá mềm (`destroy`), không có đường "ngừng hoạt động". Màn **danh mục** thì có đủ cột, bộ lọc và nút đổi trạng thái.

### 7.6. `is_primary` — "nhiều nhất một", không phải "đúng một"

Cả đối tượng chính lẫn thân nhân chính đều enforce trong Service, không bằng ràng buộc DB:
- Set `is_primary = true` cho một dòng → tự `false` toàn bộ dòng còn lại **cùng `beneficiary_id`** trong cùng transaction.
- Cho phép **không có** dòng nào là chính (hồ sơ mới nhập chưa xác định).
- Xoá dòng đang là chính → **không** tự thăng dòng khác lên; cán bộ chọn lại. Tự động chọn hộ dễ tạo dữ liệu sai mà không ai biết.

### 7.7. Media: spatie thẳng trong Service, disk mặc định ✅ ĐÃ CHỐT 15/08/2026

v2 là module **mới** → theo `docs/system/QUAN_HE_CHA_CON.md`, không dùng `Core\Services\MediaService` (khác v1). Lý do: phần khó của luồng media là **thứ tự** snapshot → commit → ghi → xoá, mà thứ tự nằm ở service gọi — lớp bọc không ép được.

**Chốt: dùng disk mặc định `config('media-library.disk_name')` = `public`, không dùng disk `private`.** Không gọi `useDisk()`, không thêm disk mới, không có endpoint tải riêng — giống hệt cách 4 module hiện có đang làm.

Ba hệ quả:

1. **Không đụng `config/filesystems.php`** — repo giữ nguyên 3 disk `local`, `public`, `s3`. Module này không phải ngoại lệ về hạ tầng lưu trữ.
2. **Resource trả thẳng `getUrl()` của spatie** — FE dùng `<img src>` / `<embed>` trực tiếp, không cần fetch kèm token rồi dựng blob URL. Đây cũng là hành vi FE v1 đang có nên không phải viết lại phần hiển thị tệp.
3. **Đánh đổi đã biết:** spatie lưu theo tên gốc đã sanitize (`/storage/{media_id}/quyet-dinh-nguyen-van-a.pdf`) nên **ai biết URL đều tải được, không qua kiểm quyền**. Tệp ở đây là giấy tờ cá nhân (CCCD, quyết định), nên nếu về sau cần siết thì đường nâng cấp là: thêm disk `private` + endpoint tải có kiểm quyền + đổi `download_url` trong Resource — không phải sửa cấu trúc bảng.

### 7.8. Morph map

Thêm vào `AppServiceProvider::boot()`:
```php
'beneficiary_type_relation' => \App\Modules\Beneficiary\Models\BeneficiaryTypeRelation::class,
'beneficiary_document'      => \App\Modules\Beneficiary\Models\BeneficiaryDocument::class,
```
Chỉ hai model này tham gia quan hệ polymorphic (media). Thiếu alias → `ClassMorphViolationException` → 500.

---

## 8. Permission

Thêm nhóm `'Beneficiary' => 'Người có công'` vào `PermissionSeeder`:

| Resource | Nhãn | Action |
|---|---|---|
| `beneficiaries` | Người có công | `stats, index, show, store, update, destroy, bulkDestroy, export, import` |
| `beneficiary-type-relations` | Đối tượng của người có công | `index, show, store, update, destroy, bulkDestroy` |
| `beneficiary-dependents` | Thân nhân | `index, show, store, update, destroy, bulkDestroy` |
| `beneficiary-documents` | Tài liệu hồ sơ | `index, show, store, update, destroy, bulkDestroy` |
| `beneficiary-residential-areas` | Tổ dân phố/Thôn | `stats, index, show, store, update, destroy, bulkDestroy, bulkUpdateStatus, changeStatus, reorder, export, import` |
| `beneficiary-types` | Loại đối tượng | như trên |
| `beneficiary-relationships` | Mối quan hệ | như trên |

`changeStatus` / `bulkUpdateStatus` **chỉ có ở 3 danh mục**, không có ở `beneficiaries` và 3 bảng con (§7.5).

`saveFull` dùng chung `beneficiaries.store` / `beneficiaries.update`. `importTemplate` dùng chung `.import`.

Bổ sung lại nhãn resource trong `Core\Middleware\LogActivity::resourceLabel()`.

---

## 9. Những gì đã xoá khỏi `core-be` (đã thực hiện)

| Nhóm | Nội dung |
|---|---|
| Mã nguồn | `app/Modules/Beneficiary/` (toàn bộ: 7 Model, 7 Controller, 6 Service, 4 Enum, 4 Export, 4 Import, 20 Request, 13 Resource, 7 file Route, 2 Concern, 1 Observer) |
| Migration | 16 file `2026_07_16_*` → `2026_07_26_*` liên quan beneficiary |
| Factory | `database/factories/Modules/Beneficiary/` (7 factory) |
| Seeder | `BeneficiaryDataSeeder.php`, `BeneficiarySampleSeeder.php` |
| Test | `tests/Feature/Beneficiary/` (7 test) |
| Tài liệu | `docs/modules/Beneficiary/`, `docs/database/Beneficiary.md`, 7 file `docs/api/beneficiary-*.md`, `docs/decisions/ADR-001-…` |
| Scribe | 14 file `.scribe/endpoints{,.cache}/*.yaml` chứa endpoint beneficiary |
| Sửa (không xoá) | `routes/api.php`, `app/Providers/AppServiceProvider.php` (morph map + observer), `Core/Middleware/LogActivity.php`, `DatabaseSeeder.php`, `PermissionSeeder.php`, `docs/README.md`, `docs/database/ERD.md`, `docs/system/QUAN_HE_CHA_CON.md`, `CLAUDE.md` (gỡ tham chiếu **và** nới B3 theo §7.5) |

**Giữ lại** (là hồ sơ lịch sử, không phải mã đang chạy): `docs/answer/*nguoi-co-cong*` và `docs/changelogs/*beneficiary*` của v1.

**Chưa đụng đến:** toàn bộ `core-fe/src/modules/nguoicocong/` — FE hiện đang gọi các endpoint vừa bị xoá và sẽ lỗi 404 cho tới khi v2 lên.

---

## 10. Kế hoạch triển khai (sau khi duyệt)

| Bước | Nội dung | Kiểm chứng |
|---|---|---|
| 1 | 7 migration (3 danh mục → `beneficiaries` → 3 bảng con) | `sail artisan migrate:fresh` chạy sạch |
| 2 | 7 Model (`TenantModel`, `SoftDeletes`, `$touches`, media collection disk mặc định) + 7 Factory | `sail artisan tinker` tạo được bản ghi kèm quan hệ |
| 3 | `GenderEnum` + `CatalogStatusEnum` + `EnumController` | `GET /api/beneficiary-enums` trả 2 key `gender`, `catalog_status` |
| 4 | 3 danh mục: Service + Controller + Request + Resource + Route (11 action + `reorder`) | Test CRUD, `reorder`, `changeStatus`/`bulkUpdateStatus`, lọc `status`, chặn xoá khi đang được dùng (409), trùng `name` |
| 5 | `BeneficiaryService` + 9 action + optimistic lock | Test cross-tenant, `lock_version` lệch → 409, restore theo CCCD đã xoá mềm, **gán danh mục `inactive` → validate chặn** |
| 6 | 3 sub-resource (6 action mỗi bộ), route lồng | Test IDOR: `beneficiary_id` không nhận từ body |
| 7 | `saveFull()` + luồng media đúng thứ tự | Test: xoá dòng con → media bị xoá; rollback → không mất tệp |
| 8 | Export + Import + template cho 4 resource | Round-trip Export → Import không lỗi; import hồ sơ gặp danh mục `inactive` → để trống, không chặn dòng |
| 9 | `PermissionSeeder`, `LogActivity`, `AppServiceProvider` morph map | `sail artisan db:seed --class=PermissionSeeder` |
| 10 | `docs/database/Beneficiary.md`, `docs/modules/Beneficiary/README.md`, `docs/api/beneficiary*.md`, `sail artisan scribe:generate` | Docs khớp code |
| 11 | Changelog FE `docs/changelogs/YYYY-MM-DD-beneficiary-v2-fe.md` | FE có đủ thông tin migrate |

---

## 11. Việc FE phải làm khi v2 lên

FE (`core-fe/src/modules/nguoicocong/`) **chưa đụng đến** và đang gọi API v1 đã bị xoá — sẽ 404 cho tới khi migrate. Ba thay đổi phá vỡ tương thích, ghi đủ vào changelog ở bước 11:

1. **Bỏ hoàn toàn màn Hộ gia đình** (`household/`) và các nhánh subsidy / visit-schedule / báo cáo biến động — endpoint không còn.
2. **Cột & bộ lọc "Trạng thái" chỉ còn ở màn danh mục**, gỡ khỏi màn hồ sơ người có công và 3 danh sách con (§7.5).
3. **Loại đối tượng và Mối quan hệ chuyển từ enum sang danh mục DB** — không đọc `/beneficiary-enums` nữa mà gọi `/beneficiary-types`, `/beneficiary-relationships`. `/beneficiary-enums` còn `gender` và `catalog_status`.
4. **Dropdown chọn danh mục phải truyền `status=active`** — endpoint không tự lọc vì màn quản trị danh mục cần thấy cả dòng `inactive` (§5.3). Quên tham số này thì cán bộ vẫn chọn được mục đã ngừng dùng, và BE sẽ trả lỗi validate.

Phần hiển thị tệp đính kèm **giữ nguyên** — Resource vẫn trả URL trực tiếp như v1, FE không phải đổi gì (§7.7).

Phần bản đồ (`map/`, `map-studio/`, `poi-category/`) không phụ thuộc các endpoint đã xoá — giữ nguyên, chỉ cần đổi nguồn toạ độ sang `latitude`/`longitude` của `beneficiaries` v2.
