# Thiết kế lại (đơn giản hóa) Module Người có công + Kế hoạch triển khai

> Ngày tạo: 13:37:39 25/07/2026
> Cập nhật lần cuối: 13:37:39 25/07/2026

Tài liệu này phân tích thiết kế hiện tại, thiết kế lại theo hướng **chỉ lưu thông tin cơ bản** (bỏ toàn bộ engine trợ cấp, audit trạng thái, lịch viếng thăm và hạ tầng nhắc lịch đi kèm), và đưa ra kế hoạch triển khai từng bước có tiêu chí kiểm chứng.

Nguồn thiết kế cũ: [docs/database/Beneficiary.md](../database/Beneficiary.md), [docs/modules/Beneficiary/README.md](../modules/Beneficiary/README.md).

## Quyết định đã chốt (ảnh hưởng toàn bộ kế hoạch)

1. **Migration**: viết lại migration gốc (module chưa lên production) — sửa trực tiếp 10 file `2026_07_16_*`, **xóa hẳn** file migration của 4 bảng bị bỏ, thêm 1 migration bảng giấy tờ mới. Sau đó `sail artisan migrate:fresh --seed` lại DB dev.
2. **Giấy tờ đính kèm**: bảng mới `beneficiary_documents` gắn **trực tiếp** vào Người có công (`beneficiary_id`), mỗi bản ghi = 1 *Tên giấy tờ* + **nhiều** tập tin (spatie medialibrary).
3. **Trạng thái NCC**: **giữ** cột `status` + `changeStatus`/`bulkUpdateStatus`, chỉ **bỏ bảng audit** `beneficiary_status_histories` (đổi trạng thái không còn ghi lịch sử).

---

## 1. Phân tích: cái gì bị bỏ, vì sao

Thiết kế cũ ôm trọn vòng đời nghiệp vụ (công nhận → trợ cấp → theo dõi điều kiện hưởng theo tuổi → viếng thăm → báo cáo biến động). Yêu cầu mới rút về **hồ sơ cơ bản + giấy tờ**. Hệ quả dây chuyền:

| Bỏ | Kéo theo phải gỡ |
|---|---|
| `beneficiary_subsidy_policies` (danh mục mức trợ cấp) | Model, migration, controller, service, requests, resources, export/import, route, permission, endpoint `renew` |
| `beneficiary_subsidy_grants` (lịch sử cấp trợ cấp) | Như trên + quan hệ `subsidyGrants()`/`activeSubsidyGrants()` trên Beneficiary & Dependent |
| `beneficiary_status_histories` (audit trạng thái) | Model, migration, resources, nested route `GET /{id}/status-histories`, đoạn ghi audit trong `changeStatus()` |
| `beneficiary_visit_schedules` (lịch viếng thăm) | Model (`Remindable`/`HasMedia`), controller, service, request, resources, route, permission, `VisitScheduleObserver`, `GenerateVisitSchedulesCommand` |
| Điều kiện hưởng theo thời gian (`eligible_from/until`, `status` pivot) | `CheckDependentEligibilityCommand`, `BeneficiaryDependentRelationObserver`, event `DependentEligibilityExpired` |
| **Toàn bộ hạ tầng nhắc lịch riêng của module** | `NotificationModuleEnum::Beneficiary`, 3 case `NotificationEventEnum::BeneficiaryVisitReminder*`, `BeneficiaryVisitReminderContentBuilder`, route `beneficiary/notification-config`, lịch cron trong `routes/console.php` |

> **Điểm mấu chốt**: sau đơn giản hóa, module **không còn** Event/Observer/Job/Notification/Schedule nào. Đây là module CRUD thuần + đính kèm media. Việc gỡ đúng và đủ các đăng ký ở tầng chung (`app/Services/Notification/*`, `app/Providers/AppServiceProvider.php`, `routes/console.php`, `routes/api.php`) quan trọng ngang việc xóa bảng — sót lại sẽ gây lỗi "class not found" khi boot.

---

## 2. Thiết kế database mới

Còn lại **7 bảng** (5 nghiệp vụ + pivot + bảng giấy tờ mới). Media dùng bảng chung `media` của spatie.

```
organizations (phường/xã)
   └── beneficiary_residential_areas (tổ dân phố / thôn)
   └── beneficiary_households
          └── beneficiaries ──┬── beneficiary_classifications ── media (decision_documents)
          └── dependents      ├── beneficiary_documents ── media (files)   ← BẢNG MỚI
                              └── beneficiary_dependent_relations (pivot N-N, chỉ còn relationship_type + note)
```

### 2.1 `beneficiary_residential_areas` — Tổ dân phố / Thôn

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id | bigint | No | PK |
| organization_id | bigint | No | FK, TenantModel |
| name | varchar(255) | No | "Tổ 5", "Thôn Bắc"… |
| ~~code~~ | — | — | **BỎ** |
| **note** | text | Yes | **THÊM** |
| created_by / updated_by | bigint | Yes | |
| timestamps | | | |

### 2.2 `beneficiary_households` — Hộ gia đình

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id, organization_id | | | |
| residential_area_id | bigint | Yes | FK, nullOnDelete |
| ~~household_code~~ | — | — | **BỎ** (bỏ luôn unique index + logic sinh mã) |
| head_name | varchar(255) | No | Chủ hộ, `VietnameseSort` |
| head_id_number | varchar(255) | Yes | CCCD chủ hộ (giữ unique theo org) |
| address | varchar(255) | Yes | |
| latitude / longitude | decimal(10,7) | Yes | |
| phone | varchar(255) | Yes | |
| member_count | integer | No default 0 | Giữ denormalized qua `HouseholdObserver` |
| note | text | Yes | |
| created_by / updated_by, timestamps | | | |

### 2.3 `beneficiaries` — Người có công

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id, organization_id, household_id | | | |
| full_name, date_of_birth, birth_year, gender, id_number | | | Giữ nguyên (id_number unique theo org) |
| ~~injury_rate~~ | — | — | **BỎ** |
| ~~recognition_decision_no~~ | — | — | **BỎ** |
| ~~recognition_date~~ | — | — | **BỎ** |
| status | varchar(20) default 'pending' | No | **GIỮ** (`BeneficiaryStatusEnum`) |
| death_date | date | Yes | Giữ (chỉ dependents mới bỏ death_date) |
| address, latitude, longitude, phone, note | | | Giữ |
| created_by / updated_by, timestamps | | | |

### 2.4 `beneficiary_classifications` — Phân loại đối tượng (+ đính kèm quyết định)

Cột giữ nguyên (`type`, `decision_no`, `decision_date`, `issued_by`, `is_primary`). **Bổ sung khả năng đính kèm tập tin quyết định công nhận**: model `implements HasMedia`, collection `decision_documents` (nhiều file), upload qua `MediaService::uploadMany`. Không thêm cột DB (media dùng bảng chung).

### 2.5 `beneficiary_dependents` — Thân nhân

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id, organization_id, household_id | | | |
| full_name, date_of_birth, gender, id_number | | | Giữ |
| **phone** | varchar(255) | Yes | **THÊM** |
| **latitude / longitude** | decimal(10,7) | Yes | **THÊM** |
| **residential_area_id** | bigint | Yes | **THÊM** — FK, nullOnDelete |
| ~~is_alive~~ | — | — | **BỎ** |
| ~~death_date~~ | — | — | **BỎ** |
| ~~eligibility_status~~ | — | — | **BỎ** |
| note, created_by / updated_by, timestamps | | | |

### 2.6 `beneficiary_dependent_relations` — Pivot NCC–Thân nhân

| Cột | Kiểu | Ghi chú |
|---|---|---|
| id, beneficiary_id, dependent_id | | Giữ, unique `(beneficiary_id, dependent_id)` |
| relationship_type | varchar(50) | Giữ (`DependentRelationshipEnum`) |
| ~~eligible_from~~ ~~eligible_until~~ ~~status~~ | — | **BỎ cả 3** |
| note, timestamps | | |

> Bỏ `status` → không còn Observer, Job, index `(dependent_id, status)`. Pivot trở về thuần liên kết + loại quan hệ.

### 2.7 `beneficiary_documents` — Giấy tờ / Hồ sơ đính kèm (**BẢNG MỚI**)

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id | bigint | No | PK |
| organization_id | bigint | No | TenantModel |
| beneficiary_id | bigint | No | FK → beneficiaries, cascadeOnDelete |
| name | varchar(255) | No | **Tên giấy tờ** (VD "Giấy chứng nhận thương binh") |
| note | text | Yes | |
| created_by / updated_by, timestamps | | | |

Model `implements HasMedia`, collection **`files`** (nhiều tập tin). Upload/xóa qua `MediaService`. Index `(organization_id, beneficiary_id)`.

### 2.8 Enum

| Giữ | Bỏ |
|---|---|
| `GenderEnum`, `BeneficiaryTypeEnum`, `BeneficiaryStatusEnum`, `DependentRelationshipEnum` | `DependentEligibilityEnum`, `DependentRelationStatusEnum`, `SubsidyStatusEnum`, `ScheduleStatusEnum`, `VisitOccasionEnum`, `DocumentTypeEnum` (\*) |

(\*) `DocumentTypeEnum` chỉ dùng làm custom-property media trong thiết kế cũ — bảng giấy tờ mới dùng `name` tự do nên bỏ. Nếu muốn phân loại giấy tờ theo enum sau này thì mới giữ.

---

## 3. Kế hoạch triển khai từng bước

Nguyên tắc: **gỡ trước, sửa sau, thêm cuối** để tránh lỗi tham chiếu chéo. Mỗi phase có tiêu chí kiểm chứng. Toàn bộ dùng `sail`.

### Phase 0 — Chuẩn bị

1. Tạo nhánh: `git checkout -b refactor/beneficiary-simplify` (nếu repo được init — hiện `Is a git repository: false`, xác nhận lại; nếu không có git, backup thủ công thư mục module + migrations).
2. Chụp danh sách route hiện tại làm mốc so sánh: `sail artisan route:list --path=beneficiar > /tmp/routes-before.txt`.
   → **Verify**: file có ~9 nhóm prefix.

### Phase 1 — Gỡ 4 nhóm chức năng bị bỏ (subsidy policy, subsidy grant, status history, visit schedule)

Xóa file theo từng nhóm:

1. **Subsidy Policy**: xóa
   `Models/SubsidyPolicy.php`, `Controllers/SubsidyPolicyController.php`, `Services/SubsidyPolicyService.php`,
   `Requests/StoreSubsidyPolicyRequest.php`, `UpdateSubsidyPolicyRequest.php`, `BulkDestroySubsidyPolicyRequest.php`,
   `Resources/SubsidyPolicyResource.php`, `SubsidyPolicyCollection.php`,
   `Exports/SubsidyPolicyExport.php`, `Imports/SubsidyPolicyImport.php`,
   `Routes/subsidy_policy.php`, migration `..._090006_create_beneficiary_subsidy_policies_table.php`.
2. **Subsidy Grant**: xóa
   `Models/SubsidyGrant.php`, `Controllers/SubsidyGrantController.php`, `Services/SubsidyGrantService.php`,
   `Requests/StoreSubsidyGrantRequest.php`, `ChangeStatusSubsidyGrantRequest.php`,
   `Resources/SubsidyGrantResource.php`, `SubsidyGrantCollection.php`,
   `Routes/subsidy_grant.php`, migration `..._090007_...`.
3. **Status History**: xóa
   `Models/StatusHistory.php`, `Resources/StatusHistoryResource.php`, `StatusHistoryCollection.php`,
   migration `..._090008_...`.
4. **Visit Schedule**: xóa
   `Models/VisitSchedule.php`, `Controllers/VisitScheduleController.php`, `Services/VisitScheduleService.php`,
   `Requests/ChangeStatusVisitScheduleRequest.php`,
   `Resources/VisitScheduleResource.php`, `VisitScheduleCollection.php`,
   `Observers/VisitScheduleObserver.php`, `Console/Commands/GenerateVisitSchedulesCommand.php`,
   `Routes/visit_schedule.php`, migration `..._090009_...`.
5. **Điều kiện hưởng**: xóa
   `Console/Commands/CheckDependentEligibilityCommand.php`,
   `Observers/BeneficiaryDependentRelationObserver.php`.

→ **Verify**: `grep -rn "SubsidyPolicy\|SubsidyGrant\|StatusHistory\|VisitSchedule\|CheckDependentEligibility\|BeneficiaryDependentRelationObserver" app/Modules/Beneficiary` chỉ còn hit ở các file sẽ sửa ở Phase 3–5 (Beneficiary/Dependent model quan hệ, routes/api.php). Không còn file định nghĩa nào.

### Phase 2 — Gỡ hạ tầng Notification/Reminder/Observer/Cron (tầng chung)

1. `app/Services/Notification/Enums/NotificationEventEnum.php`: xóa 3 case `BeneficiaryVisitReminderBefore/On/After` + nhánh `moduleFor()` + `label()` tương ứng.
2. `app/Services/Notification/Enums/NotificationModuleEnum.php`: xóa `case Beneficiary` + nhánh label.
3. Xóa `app/Services/Notification/ContentBuilders/BeneficiaryVisitReminderContentBuilder.php`. Tìm nơi map builder theo event key (`grep -rn "BeneficiaryVisitReminderContentBuilder\|BeneficiaryVisitReminder" app/`) và gỡ đăng ký.
4. Xóa `app/Modules/Beneficiary/Routes/notification_config.php`.
5. `app/Providers/AppServiceProvider.php`: xóa dòng `BeneficiaryDependentRelation::observe(...)` + `use` tương ứng. **Giữ** `Beneficiary::observe(HouseholdObserver::class)` và `Dependent::observe(HouseholdObserver::class)` (member_count vẫn cần).
6. `routes/console.php`: xóa 2 `Schedule::command(...)` (`CheckDependentEligibilityCommand`, `beneficiary:generate-visit-schedules`) + `use` tương ứng.

→ **Verify**: `grep -rn "eneficiaryVisit\|NotificationModuleEnum::Beneficiary\|CheckDependentEligibility\|generate-visit-schedules" app/ routes/` không còn hit. `sail artisan config:clear && sail artisan route:list` chạy không lỗi class-not-found.

### Phase 3 — Viết lại migration & sửa 6 bảng còn lại

Sửa trực tiếp các file migration gốc (module chưa lên production):

1. `..._090000_..._residential_areas`: bỏ `code`, thêm `$table->text('note')->nullable();`.
2. `..._090001_..._households`: bỏ `household_code` + bỏ unique index của nó (giữ index org+area, giữ unique `head_id_number` theo org ở migration `..._100000`).
3. `..._090002_..._beneficiaries`: bỏ `injury_rate`, `recognition_decision_no`, `recognition_date`.
4. `..._090004_..._dependents`: bỏ `is_alive`, `death_date`, `eligibility_status`; thêm `phone`, `latitude`, `longitude`, `residential_area_id` (FK nullOnDelete). Xem lại migration `..._100001` (unique id_number) vẫn hợp lệ.
5. `..._090005_..._dependent_relations`: bỏ `eligible_from`, `eligible_until`, `status`; bỏ index `(dependent_id, status)`.
6. Các migration phụ `151908/151909/153000/153001/100000/100001`: rà soát — bất kỳ dòng nào đụng cột vừa bỏ (VD `153001` sửa `decision_*` vẫn ok; kiểm tra không migration nào add lại cột đã bỏ). Vì viết lại migration gốc, có thể **gộp** các migration phụ vào gốc cho gọn, hoặc giữ nguyên nếu không đụng cột bị bỏ. Khuyến nghị giữ nguyên để giảm diff.

→ **Verify**: `sail artisan migrate:fresh` chạy sạch, không lỗi cột không tồn tại.

### Phase 4 — Sửa Model, Enum, Request, Resource, Service của 6 bảng còn lại

**Enum**: xóa 6 file enum ở mục 2.8; cập nhật `Controllers/EnumController.php` bỏ các key enum đã xóa (giữ `gender`, `beneficiary_type`, `beneficiary_status`, `dependent_relationship`).

**Beneficiary** (`Models/Beneficiary.php`):
- `$fillable`: bỏ `injury_rate`, `recognition_decision_no`, `recognition_date`.
- `$casts`: bỏ `recognition_date`, `injury_rate` (giữ `death_date`).
- Xóa quan hệ `subsidyGrants()`, `activeSubsidyGrants()`, `statusHistories()`, `visitSchedules()`.
- Sửa `withPivot([...])` trong `dependents()`: chỉ còn `['id', 'relationship_type', 'note']`.
- Thêm quan hệ `documents()` → `hasMany(BeneficiaryDocument::class)` (Phase 5).

**Dependent** (`Models/Dependent.php`):
- `$fillable`: bỏ `is_alive`, `death_date`, `eligibility_status`; thêm `phone`, `latitude`, `longitude`, `residential_area_id`.
- `$casts`: bỏ `death_date`, `is_alive`; thêm `latitude`/`longitude` => `decimal:7`.
- Xóa quan hệ `subsidyGrants/activeSubsidyGrants/statusHistories/visitSchedules`.
- Thêm quan hệ `residentialArea()` → `belongsTo(ResidentialArea::class)`.
- Sửa `withPivot` như trên. Bổ sung filter theo `residential_area_id`.

**BeneficiaryDependentRelation** (pivot): bỏ `eligible_from/eligible_until/status` khỏi `$fillable`/`$casts`; xóa mọi logic tính eligibility.

**ResidentialArea**: `$fillable` bỏ `code`, thêm `note`.

**Household**: `$fillable` bỏ `household_code`. `HouseholdService`: **xóa toàn bộ logic sinh `household_code`** (`{org_code}-HGD-{seq}`) và check unique mã. Rà `StoreHouseholdRequest`/`UpdateHouseholdRequest` bỏ rule `household_code`.

**Service tầng nghiệp vụ**:
- `BeneficiaryService`: trong `changeStatus()` **bỏ đoạn ghi `beneficiary_status_histories`** (chỉ update status). Bỏ nested method trả status-histories. Bỏ nhánh tạo `dependents[].relationship_type/eligible_from` → chỉ còn `relationship_type` (+ note). Bỏ mọi tham chiếu subsidy.
- `DependentService::addRelation()`: bỏ tính `status`/`eligible_*`, chỉ set `relationship_type` + `note`.

**Request**:
- `StoreBeneficiaryRequest`/`UpdateBeneficiaryRequest`: bỏ rule `injury_rate`, `recognition_decision_no`, `recognition_date`; trong nhánh `dependents[]` bỏ `eligible_from`; cập nhật `bodyParameters()`, `messages()`, `attributes()`.
- `StoreDependentRequest`/`UpdateDependentRequest`: bỏ `is_alive`, `death_date`, `eligibility_status`; thêm `phone`, `latitude`, `longitude`, `residential_area_id` (`nullable|exists:beneficiary_residential_areas,id`).
- `StoreDependentRelationRequest`: bỏ `eligible_from`, `status`.
- `StoreResidentialAreaRequest`/`UpdateResidentialAreaRequest`: bỏ `code`, thêm `note`.
- `StoreHouseholdRequest`/`UpdateHouseholdRequest`: bỏ `household_code`.

**Resource**: gỡ field đã bỏ khỏi `BeneficiaryResource`, `DependentResource`, `DependentRelationResource`, `ResidentialAreaResource`, `HouseholdResource`; thêm field mới (`note` cho area; `phone/latitude/longitude/residential_area` cho dependent). Bỏ `BeneficiaryClassificationResource` field enum không đổi.

**Export/Import** (residential area, household, beneficiary, dependent): đồng bộ `FIELD_LABELS`, `TEMPLATE_EXAMPLES`, `REQUIRED_KEYS`, `templateNotes()`, `templateOptions()`, cột export theo schema mới (bỏ cột đã xóa, thêm cột mới). Với dependent: thêm cột `phone`, `latitude`, `longitude`, và cột "Tổ dân phố" (tra ngược `residential_area_id` theo tên như household đang làm).

→ **Verify**: `sail artisan route:list` OK; smoke test `POST /beneficiaries`, `POST /beneficiary-dependents`, `POST /beneficiary-residential-areas` với payload mới trả 201; `PATCH /beneficiaries/{id}/status` đổi status không lỗi (không còn ghi history).

### Phase 5 — Classification đính kèm quyết định + bảng giấy tờ mới

1. **Classification media**: `BeneficiaryClassification` → `implements HasMedia`, `use InteractsWithMedia`, `registerMediaCollections()` add `decision_documents`. `BeneficiaryService` khi store/update classification: nhận `decision_files[]` (base64/UploadedFile) và gọi `MediaService::uploadMany($classification, $files, 'decision_documents')`; xóa qua `removeByIds`. `BeneficiaryClassificationResource` expose danh sách media (url + name). Cập nhật `StoreBeneficiaryRequest`/`UpdateBeneficiaryRequest` nhánh `classifications[]` thêm `decision_files`/`decision_files_deleted`.

2. **Bảng giấy tờ mới** — tạo đầy đủ theo chuẩn CLAUDE.md:
   - Migration mới `2026_07_25_000000_create_beneficiary_documents_table.php` (cột ở §2.7).
   - `Models/BeneficiaryDocument.php` (`extends TenantModel implements HasMedia`, collection `files`, quan hệ `beneficiary()`, `booted()` set created_by/updated_by).
   - `Controllers/BeneficiaryDocumentController.php`: bộ `index, show, store, update, destroy, bulkDestroy` (**không** `import`/`export`/`stats`/`changeStatus` vì đây là đính kèm file, không có status, không phù hợp file phẳng — ghi rõ lý do trong PHPDoc, tương tự lập luận đã dùng cho subsidy-grants/visit-schedules ở README cũ).
   - `Services/BeneficiaryDocumentService.php`: store/update xử lý `name` + `files[]` qua `MediaService::uploadMany`; `bulkDestroy` chặn cross-tenant.
   - `Requests/StoreBeneficiaryDocumentRequest.php`, `UpdateBeneficiaryDocumentRequest.php`, `BulkDestroyBeneficiaryDocumentRequest.php` (đủ `bodyParameters/messages/attributes`). `files` => `array`, `files.*` => `file|max:10240` (hoặc base64 tùy convention hiện có — theo `MediaService::decodeBase64ToFile`).
   - `Resources/BeneficiaryDocumentResource.php`, `BeneficiaryDocumentCollection.php` (trả `name`, danh sách file `{id,name,url,size}`, creator/editor, timestamps).
   - `Routes/beneficiary_document.php`; đăng ký prefix `beneficiary-documents` trong `routes/api.php` (có `ensure.route.org`).
   - `Database/Factories/.../BeneficiaryDocumentFactory.php` (Scribe cần factory cho model `HasFactory`).

→ **Verify**: `POST /beneficiary-documents` (name + 2 file) trả 201 với 2 media; `GET /beneficiaries/{id}` (nếu eager-load documents) hoặc `GET /beneficiary-documents?beneficiary_id=..` trả đúng; upload quyết định trong classification lưu được media.

### Phase 6 — Permission, LogActivity, Seeder, routes/api.php

1. `routes/api.php`: **xóa** 4 prefix `beneficiary-subsidy-policies`, `beneficiary-subsidy-grants`, `beneficiary-visit-schedules`, `beneficiary/notification-config`; **thêm** prefix `beneficiary-documents`.
2. `database/seeders/PermissionSeeder.php`: xóa block `beneficiary-subsidy-policies`, `beneficiary-subsidy-grants`, `beneficiary-visit-schedules` (mảng `PERMISSIONS` + `resourceLabel` map dòng 332–334); thêm block `beneficiary-documents => [index, show, store, update, destroy, bulkDestroy]` + label "Giấy tờ hồ sơ". `beneficiaries` giữ nguyên (vẫn có `changeStatus`, `bulkUpdateStatus`).
3. `app/Modules/Core/Middleware/LogActivity.php`: cập nhật `resourceLabel()`, `actionLabels`, `pathActions`, route params — bỏ 4 resource đã xóa, thêm `beneficiary-documents`.
4. `database/seeders/BeneficiaryDataSeeder.php`: viết lại — bỏ seed subsidy/visit/status/eligibility, bỏ `household_code`, bỏ field đã xóa; thêm seed `note` cho area, `phone/coords/residential_area_id` cho dependent, vài `beneficiary_documents` mẫu.

→ **Verify**: `sail artisan migrate:fresh --seed` sạch; `sail artisan db:seed --class=PermissionSeeder` OK; `route:list --path=beneficiar` khớp tập route mong đợi (không còn subsidy/visit/notification-config, có documents).

### Phase 7 — Scribe + Tài liệu

1. `sail artisan scribe:generate` — kiểm tra không còn endpoint subsidy/visit; có `beneficiary-documents`.
2. Viết lại `docs/database/Beneficiary.md` theo §2 (7 bảng, bỏ mục 7–10, 12; sửa mục 11 Giấy tờ thành bảng `beneficiary_documents`; rút gọn Enum/Index/Rủi ro).
3. Viết lại `docs/modules/Beneficiary/README.md`: bỏ mục Events/Reminder/State-machine phức tạp, luồng 6.4/6.5/6.8; cập nhật bảng Entities, Permissions, API Endpoints, Phụ thuộc (bỏ Notification engine).
4. Cập nhật `docs/database/ERD.md` nếu có sơ đồ tổng.
5. Cập nhật header "Cập nhật lần cuối" theo quy ước.

→ **Verify**: `.scribe/endpoints/*.yaml` không còn nhóm bị xóa; docs không còn nhắc subsidy/visit/reminder.

### Phase 8 — Kiểm thử & rà soát cuối

1. `grep -rn "subsidy\|visit_schedule\|VisitSchedule\|status_histor\|StatusHistory\|injury_rate\|recognition_\|is_alive\|eligibility\|household_code\|eligible_" app/Modules/Beneficiary app/Services/Notification routes/ database/seeders` → **kỳ vọng 0 hit** (trừ tài liệu lịch sử nếu cố ý giữ).
2. Chạy test hiện có (nếu có): `sail artisan test --filter=Beneficiary`. Nếu test cũ tham chiếu tính năng đã bỏ → cập nhật/xóa test tương ứng (liệt kê trong PR).
3. Smoke test luồng cơ bản qua API: tạo tổ dân phố → tạo hộ → tạo NCC (+classification+file quyết định) → tạo thân nhân (gắn tổ dân phố, tọa độ) → gắn quan hệ → thêm giấy tờ (nhiều file) → đổi status NCC → export/import từng resource.

→ **Verify**: tất cả bước smoke test pass; grep sạch; Scribe generate không lỗi.

---

## 4. Rủi ro & lưu ý

1. **Sót đăng ký tầng chung** (NotificationEnum, ContentBuilder resolver, AppServiceProvider, console) là lỗi hay gặp nhất — Phase 2 phải hoàn tất và `route:list` chạy được TRƯỚC khi sang Phase 3, nếu không sẽ nhiễu lỗi.
2. **Media orphan**: khi bỏ collection cũ (`identity_documents`, `death_certificates`, `visit_evidence`…) không cần migrate media cũ (DB dev sẽ `migrate:fresh`). Trên môi trường thật thì khác — nhưng đã chốt module chưa lên production.
3. **`household_code`** có thể đang được FE dùng hiển thị/tra cứu — bỏ cột này là **breaking change với FE**. Cần 1 changelog `docs/changelogs/` báo FE (bỏ `household_code`, `injury_rate`, `recognition_*`, `is_alive`, `eligibility_status`, các endpoint subsidy/visit; thêm `beneficiary-documents`, dependent thêm `phone/lat/long/residential_area`).
4. **Giữ `status` nhưng bỏ audit**: `changeStatus` vẫn tồn tại nhưng không còn vết lịch sử — nếu nghiệp vụ sau này cần truy vết, phải khôi phục bảng audit. Ghi rõ trong README để không hiểu nhầm là "quên".
5. **`beneficiary-documents` không có import/export**: lệch bộ chuẩn CLAUDE.md §3 một cách có chủ đích (đính kèm file không phù hợp file phẳng) — ghi rõ lý do trong PHPDoc + README, giống tiền lệ subsidy-grants/visit-schedules.

## 5. Tổng kết phạm vi thay đổi

| Loại | Số lượng |
|---|---|
| Bảng xóa | 4 (subsidy_policies, subsidy_grants, status_histories, visit_schedules) |
| Bảng sửa | 5 (residential_areas, households, beneficiaries, dependents, dependent_relations) |
| Bảng thêm | 1 (beneficiary_documents) |
| Model xóa | 4 · Model sửa | 6 · Model thêm | 1 |
| Enum xóa | 6 · Enum giữ | 4 |
| Console Command xóa | 2 · Observer xóa | 2 (giữ HouseholdObserver) |
| Đăng ký Notification/Reminder gỡ | Module + Event enum + ContentBuilder + route notification-config + 2 cron |
| Route prefix: bỏ 4, thêm 1 (`beneficiary-documents`) |

Sau đơn giản hóa, module là **CRUD thuần + đính kèm media**, không còn Event/Job/Notification/Schedule — giảm đáng kể bề mặt bảo trì.
