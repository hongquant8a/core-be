# DATABASE DESIGN — Module Beneficiary (Người có công)

> Ngày tạo: 11:05:00 16/07/2026
> Cập nhật lần cuối: 11:05:00 16/07/2026

Quản lý người có công theo hộ gia đình & thân nhân (TP Đà Nẵng). Module đa tổ chức — bảng nghiệp vụ có `organization_id` scope theo tenant hiện tại (`organization_id = NULL` chỉ dùng cho catalog áp dụng toàn TP). Mọi model có `organization_id` **extends `TenantModel`**, không `extends Model`.

> Đổi so với bản nháp đầu: toàn bộ bảng danh mục/phụ trợ đã thêm tiền tố `beneficiary_` theo quy tắc CLAUDE.md §2 ("bảng danh mục và pivot phải có tiền tố module"), và bảng `visit_schedules` không tự cài cơ chế nhắc lịch riêng — dùng lại hạ tầng `reminders` / `Remindable` / `NotificationDispatcher` đã có (xem mục "Notification & Reminder — dùng hạ tầng chung").

---

## Sơ đồ quan hệ (tổng quát)

```
organizations (phường/xã)
   │
   ├── beneficiary_residential_areas (tổ dân phố)
   │        │
   ├── beneficiary_households ─────────────────┐
   │        │                                   │
   │   (1-N, nullable)                    (1-N, nullable)
   │        │                                   │
   ├── beneficiaries ───────────────────────────┘
   │        │ 1-N
   │        ├── beneficiary_classifications (loại đối tượng, có thể nhiều)
   │        │
   │        │      N-N (pivot có thuộc tính)
   │        └── beneficiary_dependent_relations ── beneficiary_dependents ── beneficiary_households
   │
   ├── beneficiary_subsidy_policies (catalog mức trợ cấp, org_id nullable)
   │        │
   │        └── beneficiary_subsidy_grants (morph: Beneficiary | Dependent)
   │
   ├── beneficiary_status_histories (morph: Beneficiary | Dependent)
   │
   ├── beneficiary_visit_schedules (morph subject: Beneficiary | Dependent | Household)
   │        └── reminders (bảng CHUNG, polymorphic remindable_type/remindable_id) ← không có bảng reminder riêng của module
   │
   └── media (spatie/laravel-medialibrary — giấy tờ, hồ sơ scan)
```

---

## 1. Bảng danh mục (catalog)

### `beneficiary_residential_areas` — Tổ dân phố / Khu vực

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id, TenantModel |
| name | varchar(255) | No | — | "Tổ 5", "Khu vực Bắc"... |
| code | varchar(255) | Yes | null | |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(organization_id)`.

---

## 2. `beneficiary_households` — Hộ gia đình

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK, TenantModel |
| residential_area_id | bigint unsigned | Yes | null | FK → beneficiary_residential_areas.id, nullOnDelete |
| household_code | varchar(255) | No | — | Format `{org_code}-HGD-{seq}` hoặc nhập tay |
| head_name | varchar(255) | No | — | Chủ hộ — dùng `VietnameseSort::apply()` khi sort |
| head_id_number | varchar(255) | Yes | null | CCCD chủ hộ |
| address | varchar(255) | No | — | |
| phone | varchar(255) | Yes | null | |
| member_count | integer | No | 0 | **Denormalized** — cập nhật qua `HouseholdObserver` khi thêm/xóa thành viên, không `COUNT()` runtime |
| note | text | Yes | null | |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(organization_id, residential_area_id)`. `household_code` **unique theo `organization_id`** (composite unique, không unique global — 2 tổ chức khác nhau được trùng mã).

---

## 3. `beneficiaries` — Người có công (chủ thể)

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK, TenantModel |
| household_id | bigint unsigned | Yes | null | FK → beneficiary_households.id, nullOnDelete — có thể chưa gán hộ |
| full_name | varchar(255) | No | — | Áp `VietnameseSort::apply()` khi sort, whitelist column trước khi truyền |
| date_of_birth | date | Yes | null | |
| gender | varchar(20) | No | — | `GenderEnum` |
| id_number | varchar(255) | Yes | null | CCCD/CMND — **unique theo `organization_id`** (không unique global, tránh chặn nhầm khi 2 tenant khác nhau) |
| injury_rate | decimal(5,2) | Yes | null | Tỷ lệ thương tật % (thương binh) |
| recognition_decision_no | varchar(255) | Yes | null | Số quyết định công nhận (chỉ mang tính tham chiếu nhanh — chi tiết từng loại nằm ở `beneficiary_classifications`) |
| recognition_date | date | Yes | null | |
| status | varchar(20) | No | 'pending' | `BeneficiaryStatusEnum` |
| death_date | date | Yes | null | |
| address | varchar(255) | Yes | null | Nếu khác địa chỉ hộ |
| phone | varchar(255) | Yes | null | |
| note | text | Yes | null | |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(organization_id, status)`, `(organization_id, household_id)`, unique composite `(organization_id, id_number)`.

---

## 4. `beneficiary_classifications` — Phân loại đối tượng (1 người có thể nhiều loại)

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK |
| beneficiary_id | bigint unsigned | No | — | FK → beneficiaries.id, cascadeOnDelete |
| type | varchar(50) | No | — | `BeneficiaryTypeEnum` — 12 nhóm theo Pháp lệnh 02/2020/UBTVQH14 |
| decision_no | varchar(255) | No | — | Số quyết định công nhận loại này |
| decision_date | date | No | — | |
| issued_by | varchar(255) | No | — | Cơ quan ban hành |
| is_primary | boolean | No | false | Loại chính dùng để tính trợ cấp ưu tiên — **chỉ 1 bản ghi `is_primary=true` / beneficiary** (validate ở Service, không ràng buộc DB unique vì cần cho phép tạm thời 0 primary khi đang sửa) |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(beneficiary_id, is_primary)`.

---

## 5. `beneficiary_dependents` — Thân nhân

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK, TenantModel |
| household_id | bigint unsigned | Yes | null | FK → beneficiary_households.id, nullOnDelete |
| full_name | varchar(255) | No | — | |
| date_of_birth | date | Yes | null | Dùng tính tuổi runtime — điều kiện hưởng tuất |
| gender | varchar(20) | No | — | `GenderEnum` |
| id_number | varchar(255) | Yes | null | |
| is_alive | boolean | No | true | |
| death_date | date | Yes | null | |
| eligibility_status | varchar(50) | No | 'normal' | `DependentEligibilityEnum` |
| note | text | Yes | null | |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(organization_id, household_id)`.

---

## 6. `beneficiary_dependent_relations` — Pivot quan hệ Beneficiary–Dependent (N-N có thuộc tính)

> Đổi tên từ `beneficiary_dependent` (bản nháp) để không nhầm với bảng `beneficiary_dependents` (số nhiều — bảng thực thể). Không dùng quy ước pivot alphabetical mặc định của Laravel vì bảng có nhiều cột thuộc tính riêng, cần custom Model + tên rõ nghĩa.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK — dùng custom pivot Model (không dùng bare pivot), vì cần Observer + quan hệ reminders sau này |
| beneficiary_id | bigint unsigned | No | — | FK → beneficiaries.id, cascadeOnDelete |
| dependent_id | bigint unsigned | No | — | FK → beneficiary_dependents.id, cascadeOnDelete |
| relationship_type | varchar(50) | No | — | `DependentRelationshipEnum` |
| eligible_from | date | No | — | Ngày bắt đầu đủ điều kiện hưởng |
| eligible_until | date | Yes | null | **Tính trước** bởi Job hàng ngày khi phát hiện hết tuổi/điều kiện — không suy ra `is_eligible` runtime bằng cách so sánh tuổi mỗi lần đọc |
| status | varchar(20) | No | 'active' | `DependentRelationStatusEnum` |
| note | text | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(beneficiary_id, dependent_id)` unique (1 cặp không lặp), `(dependent_id, status)` — phục vụ Job quét hàng ngày.

---

## 7. `beneficiary_subsidy_policies` — Danh mục mức trợ cấp

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | Yes | null | **NULL = catalog áp dụng toàn TP/quốc gia** — theo đúng nguyên tắc TenantModel cho catalog chung |
| beneficiary_type | varchar(50) | Yes | null | `BeneficiaryTypeEnum` |
| relationship_type | varchar(50) | Yes | null | `DependentRelationshipEnum` — áp dụng cho thân nhân |
| amount | decimal(15,2) | No | — | |
| unit | varchar(50) | No | 'VND/tháng' | |
| legal_basis | varchar(255) | No | — | "Nghị định 75/2021/NĐ-CP"... |
| effective_from | date | No | — | |
| effective_to | date | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(organization_id, beneficiary_type, effective_from)`.

> **Quyết định kiến trúc (ADR cần tạo):** engine "trợ cấp theo policy hiệu lực theo thời gian" không đặc thù riêng người có công — nhiều khả năng Hộ nghèo/Bảo trợ xã hội/Người khuyết tật sẽ cần cơ chế giống hệt sau này. Bản hiện tại **giữ trong module `Beneficiary`** (đúng tinh thần Simplicity First — chưa có module thứ 2 xác nhận dùng chung), nhưng cần ghi ADR (`docs/decisions/`) để khi module thứ 2 xuất hiện, tách sang `Core`/module riêng không bị quên và không phải thiết kế lại từ đầu.

---

## 8. `beneficiary_subsidy_grants` — Lịch sử cấp trợ cấp thực tế

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK, TenantModel |
| subject_type | varchar(255) | No | — | morph — `Beneficiary` \| `BeneficiaryDependent` |
| subject_id | bigint unsigned | No | — | morph |
| beneficiary_subsidy_policy_id | bigint unsigned | No | — | FK → beneficiary_subsidy_policies.id |
| amount | decimal(15,2) | No | — | Có thể khác policy nếu có điều chỉnh riêng — **không sửa trực tiếp `amount` của bản ghi cũ khi policy đổi**, luôn đóng bản ghi cũ + tạo bản ghi mới (giữ lịch sử) |
| granted_from | date | No | — | |
| granted_to | date | Yes | null | |
| status | varchar(20) | No | 'active' | `SubsidyStatusEnum` |
| termination_reason | varchar(255) | Yes | null | |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(subject_type, subject_id, status)` — bắt buộc, phục vụ cả nghiệp vụ lẫn báo cáo tổng hợp (`SUM(amount) WHERE status = active`).

---

## 9. `beneficiary_status_histories` — Audit thay đổi trạng thái (Beneficiary & Dependent)

> Đổi tên từ `status_histories` (bản nháp) — tên gốc quá chung, chắc chắn đụng module khác. Bảng này **khác** `log_activities` (Core) — `log_activities` ghi lại request HTTP (ai gọi API nào), còn bảng này ghi lại **chuyển trạng thái nghiệp vụ** (old_status → new_status kèm lý do), phục vụ báo cáo biến động (Luồng 9). Không dùng chung 1 bảng cho 2 mục đích khác nhau.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK, TenantModel |
| subject_type | varchar(255) | No | — | morph — `Beneficiary` \| `BeneficiaryDependent` |
| subject_id | bigint unsigned | No | — | morph |
| old_status | varchar(50) | Yes | null | |
| new_status | varchar(50) | No | — | |
| reason | text | Yes | null | |
| changed_by | bigint unsigned | Yes | null | FK → users.id — null nếu do Job hệ thống tự đổi |
| changed_at | datetime | No | — | |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(subject_type, subject_id, changed_at)` — phục vụ truy vấn biến động theo khoảng thời gian (Luồng 9) mà không cần tính lại từ trạng thái hiện tại.

---

## 10. `beneficiary_visit_schedules` — Lịch viếng thăm / tặng quà

> Đổi thiết kế so với bản nháp `visit_schedules`: bảng này **chỉ giữ phần "công việc cần làm"** (ai đi thăm, thăm ngày nào, đã thăm chưa) — phần "nhắc trước N ngày qua Zalo/FCM" **không** có cột/job riêng ở đây, mà model implement contract `Remindable` và dùng lại bảng `reminders` + `ReminderScheduler` + `NotificationDispatcher` đã có sẵn (xem mục "Notification & Reminder — dùng hạ tầng chung" bên dưới). Không tái tạo cơ chế PRESET/CUSTOM reminder lần thứ 2.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|---|---|---|---|---|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK, TenantModel |
| subject_type | varchar(255) | No | — | morph — `Beneficiary` \| `BeneficiaryDependent` \| `BeneficiaryHousehold` |
| subject_id | bigint unsigned | No | — | morph |
| occasion | varchar(50) | No | — | `VisitOccasionEnum` |
| scheduled_date | date | No | — | Mốc dùng làm `getReminderDeadline()` khi implement `Remindable` |
| status | varchar(20) | No | 'pending' | `ScheduleStatusEnum` |
| assigned_to | bigint unsigned | No | — | FK → users.id — cán bộ phụ trách |
| note | text | Yes | null | |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

**Index**: `(organization_id, assigned_to, status)`, `(subject_type, subject_id)`.

Ảnh xác nhận đã thăm: media collection `visit_evidence` gắn qua `MediaService`, **không** gọi `addMedia()` trực tiếp.

---

## 11. Giấy tờ / Hồ sơ đính kèm

Không tạo bảng riêng — dùng `spatie/laravel-medialibrary` (đã có trong `composer.json`), gắn `media` vào `Beneficiary`/`BeneficiaryDependent` qua collection, **luôn qua `App\Modules\Core\Services\MediaService::uploadOne/uploadMany`**:

- `decision_documents` (quyết định công nhận)
- `identity_documents` (CCCD, giấy khai sinh)
- `death_certificates`
- `medical_certificates`
- `visit_evidence` (ảnh xác nhận viếng thăm — gắn trên `beneficiary_visit_schedules`)

Custom properties trên Media: `document_type` (`DocumentTypeEnum`), `issued_date`, `issued_by`.

---

## 12. Notification & Reminder — dùng hạ tầng chung

Module này **không** có bảng `reminders` hay Job gửi thông báo riêng. Toàn bộ phần "nhắc lịch" tái dùng nguyên hạ tầng hiện có (đang chạy cho Meeting/Scheduling/TaskAssignment):

```
reminders (bảng CHUNG, polymorphic remindable_type/remindable_id)
    ← ReminderScheduler::scheduleFor() tạo/xoá PRESET dựa trên notification_event_configs
    ← ProcessRemindersCommand gửi khi đến remind_at, qua NotificationDispatcher

notification_event_configs (module_key='beneficiary' + event_key + enabled)
    └── notification_schedules (moment + offset_minutes + channels) ← cán bộ phường tự cấu hình qua UI admin có sẵn
```

**Model implement `Remindable`:** `BeneficiaryVisitSchedule` (xem mục 10) — `getReminderDeadline()` trả `scheduled_date`, `getReminderModuleKey()` trả `'beneficiary'`.

**Cần bổ sung khi triển khai** (không phải bảng mới, chỉ là đăng ký code):

| Việc cần thêm | Ở đâu | Theo mẫu |
|---|---|---|
| `NotificationModuleEnum::Beneficiary = 'beneficiary'` | `app/Services/Notification/Enums/NotificationModuleEnum.php` | Case `Scheduling` |
| Case `BeneficiaryVisitReminder` (+ có thể `BeneficiaryStatusChanged`, `DependentEligibilityExpired` nếu cần gửi Zalo — xem README module, mục Events) | `app/Services/Notification/Enums/NotificationEventEnum.php` | Case của Meeting/TaskAssignment |
| `BeneficiaryVisitReminderContentBuilder` | `app/Services/Notification/ContentBuilders/` | `ScheduleReminderContentBuilder.php` |
| `notification_config.php` (route `notification.module:beneficiary`) | `app/Modules/Beneficiary/Routes/notification_config.php` | `TaskAssignment/Routes/notification_config.php` |

Việc **sinh** `beneficiary_visit_schedules` đầu năm/đầu dịp lễ (data-integrity, chuẩn bị dữ liệu) đặt trong 1 Console Command của module, gọi `ReminderScheduler::scheduleFor()` cho từng record vừa tạo — không cần fire Event cho bước sinh lịch này (chỉ hành vi **gửi** mới bắt buộc qua Event → Listener, theo CLAUDE.md §EDA mục 3).

---

## 13. Danh sách Enum cần định nghĩa

Mỗi enum bắt buộc có `values()` + `rule()` theo convention CLAUDE.md §2.

| Enum | Giá trị gợi ý |
|---|---|
| `GenderEnum` | male, female, other |
| `BeneficiaryTypeEnum` | 12 nhóm theo Pháp lệnh 02/2020 |
| `BeneficiaryStatusEnum` | pending, active, deceased, moved_out, suspended |
| `DependentRelationshipEnum` | spouse, child, father, mother, foster_parent, guardian |
| `DependentEligibilityEnum` | studying, disabled_no_work_capacity, normal |
| `DependentRelationStatusEnum` | active, expired, suspended |
| `SubsidyStatusEnum` | active, terminated, suspended |
| `DocumentTypeEnum` | decision, id_card, death_certificate, medical_certificate, other |
| `VisitOccasionEnum` | tet, war_invalids_day_27_7, birthday, custom |
| `ScheduleStatusEnum` | pending, done, skipped |

---

## 14. Index quan trọng cần rà soát

- `beneficiaries(organization_id, status)`
- `beneficiaries(organization_id, id_number)` unique
- `beneficiary_households(organization_id, household_code)` unique
- `beneficiary_dependent_relations(beneficiary_id, dependent_id)` unique, `(dependent_id, status)`
- `beneficiary_subsidy_grants(subject_type, subject_id, status)`
- `beneficiary_status_histories(subject_type, subject_id, changed_at)`
- `beneficiary_visit_schedules(organization_id, assigned_to, status)`

---

## 15. Rủi ro kỹ thuật cần lưu ý

1. **Polymorphic + Eager Loading**: `beneficiary_subsidy_grants`/`beneficiary_status_histories` dùng morph — khi liệt kê danh sách Beneficiary kèm lịch sử trợ cấp cần `morphTo()` + `with()` cẩn thận để tránh N+1.
2. **Tính tuổi động**: điều kiện hưởng tuất phụ thuộc tuổi tại thời điểm truy vấn — không lưu cứng `is_eligible`, tính runtime hoặc qua Job định kỳ cập nhật `eligible_until` (đã áp dụng ở mục 6).
3. **2 đường ghi cho `DependentEligibilityExpired`**: Job hàng ngày update trực tiếp `beneficiary_dependent_relations.status` (không qua Service) — đây là nhánh ghi thứ 2 song song với cán bộ đổi status thủ công qua Service. Theo cây quyết định CLAUDE.md §EDA, phải dùng **Observer** trên model pivot để đảm bảo Event luôn fire dù đi qua Service hay Job (chi tiết xem README module, mục Events).
4. **Phân quyền địa bàn**: nếu sau này cần cán bộ cấp quận xem nhiều phường, `organization_id` (1 tenant = 1 phường) không đủ — dùng `withoutGlobalScope('organization')` làm escape hatch tạm, đánh giá mô hình tenant cha-con khi có yêu cầu thật, chưa nằm trong scope bản đầu.

---

*Tài liệu phục vụ thiết kế kỹ thuật. Bước tiếp theo: chi tiết hóa API endpoints (Scribe) và FormRequest validation — xem `docs/modules/Beneficiary/README.md`.*
