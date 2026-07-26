# DATABASE DESIGN — Module Beneficiary (Người có công)

> Ngày tạo: 11:05:00 16/07/2026
> Cập nhật lần cuối: 07:05:00 26/07/2026 — thêm `beneficiaries.residential_area_id` (tổ dân phố / thôn là trường riêng của người có công).

Quản lý người có công theo hộ gia đình & thân nhân (TP Đà Nẵng). Module đa tổ chức — bảng nghiệp vụ có `organization_id` scope theo tenant hiện tại. Mọi model có `organization_id` **extends `TenantModel`**.

Sau đơn giản hóa, module là **CRUD thuần + đính kèm media** — không còn Event/Observer nghiệp vụ (trừ `HouseholdObserver` giữ `member_count`), Job, Notification hay Schedule. Chi tiết quyết định & kế hoạch: [docs/answer/module-nguoi-co-cong-thiet-ke-don-gian-hoa_133739_25072026.md](../answer/module-nguoi-co-cong-thiet-ke-don-gian-hoa_133739_25072026.md).

---

## Sơ đồ quan hệ (tổng quát)

```
organizations (phường/xã)
   ├── beneficiary_residential_areas (tổ dân phố / thôn)
   ├── beneficiary_households
   │        │ 1-N (nullable)
   ├── beneficiaries ─────┬── beneficiary_classifications ── media (decision_documents)
   │        │             ├── beneficiary_documents ── media (files)
   │        │             ├── beneficiary_residential_areas (FK riêng, độc lập với hộ)
   │        │ N-N (pivot) │
   │        └── beneficiary_dependent_relations ── beneficiary_dependents ── beneficiary_residential_areas
   │
   └── media (spatie/laravel-medialibrary — giấy tờ, hồ sơ scan)
```

> Tổ dân phố xuất hiện ở **3 nơi độc lập**: trên hộ (`beneficiary_households`), trên người có công
> (`beneficiaries`) và trên thân nhân (`beneficiary_dependents`) — mỗi bảng giữ địa bàn của riêng nó,
> không tự đồng bộ lẫn nhau.

---

## 1. `beneficiary_residential_areas` — Tổ dân phố / Thôn

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id | bigint unsigned | No | PK |
| organization_id | bigint unsigned | No | FK → organizations.id, TenantModel |
| name | varchar(255) | No | "Tổ 5", "Thôn Bắc"… |
| note | text | Yes | |
| created_by / updated_by | bigint unsigned | Yes | FK → users.id |
| created_at / updated_at | timestamp | Yes | |

**Index**: `(organization_id)`. **API**: `stats, index, show, store, update, destroy, bulkDestroy, export, import` (+ `import-template`). Không có `changeStatus`/`bulkUpdateStatus` (không có cột `status`).

---

## 2. `beneficiary_households` — Hộ gia đình

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id | bigint unsigned | No | PK |
| organization_id | bigint unsigned | No | FK, TenantModel |
| residential_area_id | bigint unsigned | Yes | FK → beneficiary_residential_areas.id, nullOnDelete |
| head_name | varchar(255) | No | Chủ hộ — `VietnameseSort::apply()` khi sort |
| head_id_number | varchar(255) | Yes | CCCD chủ hộ — **unique theo `organization_id`**; dùng làm khóa tra hộ khi import |
| address | varchar(255) | Yes | Cho phép tạo hộ trước, bổ sung sau khi xác minh thực địa |
| latitude / longitude | decimal(10,7) | Yes | Tra cứu bản đồ |
| phone | varchar(255) | Yes | |
| member_count | integer | No (0) | **Denormalized** — cập nhật qua `HouseholdObserver`, không `COUNT()` runtime |
| note | text | Yes | |
| created_by / updated_by, created_at / updated_at | | | |

**Index**: `(organization_id, residential_area_id)`, unique `(organization_id, head_id_number)`.

---

## 3. `beneficiaries` — Người có công

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id | bigint unsigned | No | PK |
| organization_id | bigint unsigned | No | FK, TenantModel |
| household_id | bigint unsigned | Yes | FK → beneficiary_households.id, nullOnDelete |
| residential_area_id | bigint unsigned | Yes | FK → beneficiary_residential_areas.id, nullOnDelete — tổ dân phố/thôn **riêng của người có công**, độc lập với tổ dân phố của hộ |
| full_name | varchar(255) | No | `VietnameseSort::apply()` khi sort |
| date_of_birth | date | Yes | Khi biết đầy đủ ngày/tháng/năm |
| birth_year | varchar(20) | Yes | Năm sinh dạng text (nhiều người chỉ nhớ năm/ước lượng) |
| gender | varchar(20) | No | `GenderEnum` |
| id_number | varchar(255) | Yes | CCCD/CMND — **unique theo `organization_id`** |
| status | varchar(20) | No ('pending') | `BeneficiaryStatusEnum` — đổi qua `changeStatus`; **không còn ghi audit** |
| death_date | date | Yes | |
| address | varchar(255) | Yes | Nếu khác địa chỉ hộ |
| latitude / longitude | decimal(10,7) | Yes | Tra cứu bản đồ |
| phone | varchar(255) | Yes | |
| note | text | Yes | |
| created_by / updated_by, created_at / updated_at | | | |

**Index**: `(organization_id, status)`, `(organization_id, household_id)`, `(organization_id, residential_area_id)`, unique `(organization_id, id_number)`.

> **Bỏ** so với bản trước: `injury_rate`, `recognition_decision_no`, `recognition_date`.

> **`residential_area_id`** (thêm 26/07/2026): trước đây tổ dân phố của người có công chỉ suy ra qua hộ
> (`household.residential_area_id`) — nhưng `household_id` nullable nên hồ sơ chưa gán hộ không có địa bàn,
> và không lọc/thống kê được. Nay là trường riêng, đối xứng với `beneficiary_dependents.residential_area_id`.
> Migration đã **backfill** từ hộ cho dữ liệu cũ. Đây là nguồn dữ liệu duy nhất cho `StatisticsService::byResidentialArea()`
> — sửa hộ **không** tự cập nhật lại trường này.

---

## 4. `beneficiary_classifications` — Phân loại đối tượng (1 người nhiều loại)

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id | bigint unsigned | No | PK |
| beneficiary_id | bigint unsigned | No | FK → beneficiaries.id, cascadeOnDelete |
| type | varchar(50) | No | `BeneficiaryTypeEnum` — 12 nhóm theo Pháp lệnh 02/2020 |
| decision_no | varchar(255) | Yes | Số quyết định công nhận (bổ sung sau khi có giấy tờ) |
| decision_date | date | Yes | |
| issued_by | varchar(255) | Yes | Cơ quan ban hành |
| is_primary | boolean | No (false) | Loại chính — chỉ 1 bản ghi `true`/beneficiary (validate ở Service) |
| created_at / updated_at | | | |

**Đính kèm quyết định công nhận**: model `implements HasMedia`, collection **`decision_documents`** (nhiều file). Upload/xóa qua endpoint riêng của Beneficiary (xem README mục API) → `MediaService`. **Index**: `(beneficiary_id, is_primary)`.

---

## 5. `beneficiary_dependents` — Thân nhân

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id | bigint unsigned | No | PK |
| organization_id | bigint unsigned | No | FK, TenantModel |
| household_id | bigint unsigned | Yes | FK → beneficiary_households.id, nullOnDelete |
| residential_area_id | bigint unsigned | Yes | FK → beneficiary_residential_areas.id, nullOnDelete |
| full_name | varchar(255) | No | |
| date_of_birth | date | Yes | |
| gender | varchar(20) | No | `GenderEnum` |
| id_number | varchar(255) | Yes | CCCD — unique theo `organization_id` |
| phone | varchar(255) | Yes | |
| latitude / longitude | decimal(10,7) | Yes | Tra cứu bản đồ |
| note | text | Yes | |
| created_by / updated_by, created_at / updated_at | | | |

**Index**: `(organization_id, household_id)`, unique `(organization_id, id_number)`.

> **Bỏ**: `is_alive`, `death_date`, `eligibility_status`. **Thêm**: `phone`, `latitude`, `longitude`, `residential_area_id`.

---

## 6. `beneficiary_dependent_relations` — Pivot Beneficiary–Dependent (N-N)

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id | bigint unsigned | No | PK (custom pivot Model) |
| beneficiary_id | bigint unsigned | No | FK → beneficiaries.id, cascadeOnDelete |
| dependent_id | bigint unsigned | No | FK → beneficiary_dependents.id, cascadeOnDelete |
| relationship_type | varchar(50) | No | `DependentRelationshipEnum` |
| note | text | Yes | |
| created_at / updated_at | | | |

**Index**: unique `(beneficiary_id, dependent_id)`.

> **Bỏ**: `eligible_from`, `eligible_until`, `status` (và index `(dependent_id, status)`). Pivot chỉ còn liên kết + loại quan hệ.

---

## 7. `beneficiary_documents` — Giấy tờ / Hồ sơ đính kèm (BẢNG MỚI)

| Cột | Kiểu | Nullable | Ghi chú |
|---|---|---|---|
| id | bigint unsigned | No | PK |
| organization_id | bigint unsigned | No | TenantModel |
| beneficiary_id | bigint unsigned | No | FK → beneficiaries.id, cascadeOnDelete |
| name | varchar(255) | No | **Tên giấy tờ** (VD "Giấy chứng nhận thương binh") |
| note | text | Yes | |
| created_by / updated_by, created_at / updated_at | | | |

Model `implements HasMedia`, collection **`files`** (nhiều tập tin). Upload/xóa qua `MediaService`. **Index**: `(organization_id, beneficiary_id)`. **API**: `index, show, store, update, destroy, bulkDestroy` (không import/export — đính kèm file không phù hợp file phẳng).

---

## 8. Enum cần định nghĩa

| Enum | Giá trị gợi ý |
|---|---|
| `GenderEnum` | male, female, other |
| `BeneficiaryTypeEnum` | 12 nhóm theo Pháp lệnh 02/2020 |
| `BeneficiaryStatusEnum` | pending, active, deceased, moved_out, suspended |
| `DependentRelationshipEnum` | wife, husband, child, grandchild, father, mother, older_brother, older_sister, younger_sibling, foster_parent, guardian |

> **Đã bỏ**: `DependentEligibilityEnum`, `DependentRelationStatusEnum`, `SubsidyStatusEnum`, `ScheduleStatusEnum`, `VisitOccasionEnum`, `DocumentTypeEnum`.

Endpoint tra cứu enum: `GET /api/beneficiary-enums` (`EnumController`) — trả `beneficiary_type`, `beneficiary_status`, `gender`, `dependent_relationship`.

---

## 9. Media (spatie/laravel-medialibrary)

Không tạo bảng media riêng — dùng bảng chung `media`, luôn qua `App\Modules\Core\Services\MediaService::uploadMany/removeByIds` (disk `public`):

| Model | Collection | Ý nghĩa |
|---|---|---|
| `BeneficiaryClassification` | `decision_documents` | File quyết định công nhận từng loại đối tượng |
| `BeneficiaryDocument` | `files` | Tập tin của mỗi giấy tờ hồ sơ |

Alias morph (`AppServiceProvider::morphMap`): `beneficiary`, `beneficiary_dependent`, `beneficiary_household`, `beneficiary_classification`, `beneficiary_document`.

---

## 10. Bảng đã BỎ (so với thiết kế cũ)

`beneficiary_subsidy_policies`, `beneficiary_subsidy_grants`, `beneficiary_status_histories`, `beneficiary_visit_schedules` — cùng toàn bộ Model/Controller/Service/Request/Resource/Route/Permission/Command/Observer/ContentBuilder liên quan, và đăng ký `NotificationModuleEnum::Beneficiary` + `NotificationEventEnum::BeneficiaryVisitReminder*`.

---

*Bước tiếp theo khi mở rộng: xem README module. Nếu sau này cần truy vết đổi trạng thái hoặc quản lý trợ cấp, phải khôi phục bảng tương ứng — đã bỏ có chủ đích để giữ hồ sơ ở mức cơ bản.*
