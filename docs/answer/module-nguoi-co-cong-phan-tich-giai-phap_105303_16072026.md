# Giải pháp toàn diện — Module Beneficiary (Người có công theo Hộ gia đình & Thân nhân)

> Ngày tạo: 10:53:03 16/07/2026
> Cập nhật lần cuối: 12:00:00 16/07/2026 — đã triển khai xong toàn bộ code theo kế hoạch (xem `/home/quandh/.claude/plans/squishy-waddling-dongarra.md`), mục 5 đã cập nhật đúng bộ action thật sự viết ra

Tài liệu này là **bản thiết kế triển khai đầy đủ** (không phải chỉ ghi chú/đối chiếu) cho module quản lý người có công, đã áp dụng đúng convention `CLAUDE.md` và tái sử dụng toàn bộ hạ tầng Reminder/Notification sẵn có (`Reminder`, `Remindable`, `ReminderScheduler`, `NotificationDispatcher`, `NotificationEventConfig`). Dùng file này làm nguồn duy nhất để bắt đầu viết migration/Model/Service — không cần lật thêm tài liệu khác để lấy quyết định thiết kế.

---

## 1. Tổng quan kiến trúc

- **Tên module:** `Beneficiary`
- **Namespace:** `App\Modules\Beneficiary`
- **Route prefix:** `/beneficiary` (chốt lại với FE nếu cần đổi, vd `/social-welfare`)
- **Multi-tenant:** mọi model có `organization_id` → `extends TenantModel`. Catalog toàn TP (`beneficiary_subsidy_policies`) dùng `organization_id = NULL`.

```
app/Modules/Beneficiary/
  Controllers/
    BeneficiaryController.php
    HouseholdController.php
    DependentController.php
    SubsidyGrantController.php        (nested dưới Beneficiary/Dependent, không route độc lập đủ CRUD)
    VisitScheduleController.php
  Services/
    BeneficiaryService.php
    HouseholdService.php
    DependentService.php
    SubsidyGrantService.php
    VisitScheduleService.php
  Models/
    Beneficiary.php
    BeneficiaryClassification.php
    Household.php
    Dependent.php
    BeneficiaryDependentRelation.php  (custom pivot model)
    SubsidyPolicy.php
    SubsidyGrant.php
    StatusHistory.php
    VisitSchedule.php                 (implements Remindable)
  Requests/
    StoreBeneficiaryRequest.php, UpdateBeneficiaryRequest.php, ChangeStatusBeneficiaryRequest.php, ...
  Resources/
    BeneficiaryResource.php, HouseholdResource.php, DependentResource.php, ...
  Enums/
    GenderEnum.php, BeneficiaryTypeEnum.php, BeneficiaryStatusEnum.php,
    DependentRelationshipEnum.php, DependentEligibilityEnum.php, DependentRelationStatusEnum.php,
    SubsidyStatusEnum.php, DocumentTypeEnum.php, VisitOccasionEnum.php, ScheduleStatusEnum.php
  Observers/
    HouseholdObserver.php
    BeneficiaryDependentRelationObserver.php   ← fire DependentEligibilityExpired (xem mục 6)
  Policies/
    BeneficiaryPolicy.php, DependentPolicy.php, HouseholdPolicy.php
  Console/Commands/
    CheckDependentEligibilityCommand.php        ← Job hàng ngày, đăng ký routes/console.php
    GenerateVisitSchedulesCommand.php            ← sinh lịch đầu năm/đầu dịp lễ
  Routes/
    beneficiary.php, household.php, dependent.php, visit_schedule.php, notification_config.php
```

**Không** có `Events/`/`Listeners/`/`Notifications/` riêng trong module — lý do và cách wiring xem mục 6-7.

---

## 2. Database Schema (migration-ready)

### 2.1 `beneficiary_residential_areas`

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | |
| organization_id | bigint FK→organizations | No | | TenantModel |
| name | varchar(255) | No | | |
| code | varchar(255) | Yes | null | |
| created_by/updated_by | bigint FK→users | Yes | null | |
| timestamps | | | | |

Index: `(organization_id)`.

### 2.2 `beneficiary_households`

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | |
| organization_id | bigint FK | No | | TenantModel |
| residential_area_id | bigint FK→beneficiary_residential_areas | Yes | null | nullOnDelete |
| household_code | varchar(255) | No | | unique theo `organization_id` |
| head_name | varchar(255) | No | | `VietnameseSort::apply()` khi sort |
| head_id_number | varchar(255) | Yes | null | |
| address | varchar(255) | No | | |
| phone | varchar(255) | Yes | null | |
| member_count | integer | No | 0 | **Chỉ ghi qua `HouseholdObserver`** |
| note | text | Yes | null | |
| created_by/updated_by | bigint FK→users | Yes | null | |
| timestamps | | | | |

Index: `(organization_id, residential_area_id)`, unique `(organization_id, household_code)`.

### 2.3 `beneficiaries`

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | |
| organization_id | bigint FK | No | | TenantModel |
| household_id | bigint FK→beneficiary_households | Yes | null | nullOnDelete |
| full_name | varchar(255) | No | | `VietnameseSort::apply()` |
| date_of_birth | date | Yes | null | |
| gender | varchar(20) | No | | `GenderEnum` |
| id_number | varchar(255) | Yes | null | unique theo `organization_id` |
| injury_rate | decimal(5,2) | Yes | null | |
| recognition_decision_no | varchar(255) | Yes | null | |
| recognition_date | date | Yes | null | |
| status | varchar(20) | No | 'pending' | `BeneficiaryStatusEnum` |
| death_date | date | Yes | null | |
| address | varchar(255) | Yes | null | |
| phone | varchar(255) | Yes | null | |
| note | text | Yes | null | |
| created_by/updated_by | bigint FK→users | Yes | null | |
| timestamps | | | | |

Index: `(organization_id, status)`, `(organization_id, household_id)`, unique `(organization_id, id_number)`.

### 2.4 `beneficiary_classifications`

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | |
| beneficiary_id | bigint FK→beneficiaries | No | | cascadeOnDelete |
| type | varchar(50) | No | | `BeneficiaryTypeEnum` |
| decision_no | varchar(255) | No | | |
| decision_date | date | No | | |
| issued_by | varchar(255) | No | | |
| is_primary | boolean | No | false | validate 1 primary/beneficiary ở Service |
| timestamps | | | | |

Index: `(beneficiary_id, is_primary)`.

### 2.5 `beneficiary_dependents`

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | |
| organization_id | bigint FK | No | | TenantModel |
| household_id | bigint FK→beneficiary_households | Yes | null | nullOnDelete |
| full_name | varchar(255) | No | | |
| date_of_birth | date | Yes | null | dùng tính tuổi runtime |
| gender | varchar(20) | No | | `GenderEnum` |
| id_number | varchar(255) | Yes | null | |
| is_alive | boolean | No | true | |
| death_date | date | Yes | null | |
| eligibility_status | varchar(50) | No | 'normal' | `DependentEligibilityEnum` |
| note | text | Yes | null | |
| created_by/updated_by | bigint FK→users | Yes | null | |
| timestamps | | | | |

Index: `(organization_id, household_id)`.

### 2.6 `beneficiary_dependent_relations` (pivot N-N có thuộc tính, custom Model)

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | dùng custom pivot Model, không bare pivot |
| beneficiary_id | bigint FK→beneficiaries | No | | cascadeOnDelete |
| dependent_id | bigint FK→beneficiary_dependents | No | | cascadeOnDelete |
| relationship_type | varchar(50) | No | | `DependentRelationshipEnum` |
| eligible_from | date | No | | |
| eligible_until | date | Yes | null | **chỉ Job (2.10) ghi** |
| status | varchar(20) | No | 'active' | `DependentRelationStatusEnum` |
| note | text | Yes | null | |
| timestamps | | | | |

Index: unique `(beneficiary_id, dependent_id)`, `(dependent_id, status)`.

### 2.7 `beneficiary_subsidy_policies`

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | |
| organization_id | bigint FK | Yes | null | NULL = catalog toàn TP |
| beneficiary_type | varchar(50) | Yes | null | `BeneficiaryTypeEnum` |
| relationship_type | varchar(50) | Yes | null | `DependentRelationshipEnum` |
| amount | decimal(15,2) | No | | |
| unit | varchar(50) | No | 'VND/tháng' | |
| legal_basis | varchar(255) | No | | |
| effective_from | date | No | | |
| effective_to | date | Yes | null | |
| timestamps | | | | |

Index: `(organization_id, beneficiary_type, effective_from)`.

> Giữ trong module `Beneficiary` ở bản đầu (không tách engine dùng chung) — xem quyết định ở mục 9.

### 2.8 `beneficiary_subsidy_grants`

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | |
| organization_id | bigint FK | No | | TenantModel |
| subject_type | varchar(255) | No | | morph: `Beneficiary` \| `BeneficiaryDependent` |
| subject_id | bigint | No | | morph |
| beneficiary_subsidy_policy_id | bigint FK | No | | |
| amount | decimal(15,2) | No | | không sửa bản ghi cũ khi policy đổi — đóng + tạo mới |
| granted_from | date | No | | |
| granted_to | date | Yes | null | |
| status | varchar(20) | No | 'active' | `SubsidyStatusEnum` |
| termination_reason | varchar(255) | Yes | null | |
| created_by/updated_by | bigint FK→users | Yes | null | |
| timestamps | | | | |

Index bắt buộc: `(subject_type, subject_id, status)`.

### 2.9 `beneficiary_status_histories`

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | |
| organization_id | bigint FK | No | | TenantModel |
| subject_type | varchar(255) | No | | morph |
| subject_id | bigint | No | | morph |
| old_status | varchar(50) | Yes | null | |
| new_status | varchar(50) | No | | |
| reason | text | Yes | null | |
| changed_by | bigint FK→users | Yes | null | null nếu do Job |
| changed_at | datetime | No | | |
| timestamps | | | | |

Index: `(subject_type, subject_id, changed_at)`.

### 2.10 `beneficiary_visit_schedules`

| Cột | Kiểu | Null | Default | Ghi chú |
|---|---|---|---|---|
| id | bigint PK | | | |
| organization_id | bigint FK | No | | TenantModel |
| subject_type | varchar(255) | No | | morph: `Beneficiary` \| `BeneficiaryDependent` \| `Household` |
| subject_id | bigint | No | | morph |
| occasion | varchar(50) | No | | `VisitOccasionEnum` |
| scheduled_date | date | No | | = `getReminderDeadline()` |
| status | varchar(20) | No | 'pending' | `ScheduleStatusEnum` |
| assigned_to | bigint FK→users | No | | |
| note | text | Yes | null | |
| created_by/updated_by | bigint FK→users | Yes | null | |
| timestamps | | | | |

Index: `(organization_id, assigned_to, status)`, `(subject_type, subject_id)`.

**Không có cột `remind_at`/`channels` ở đây** — nằm ở bảng `reminders` dùng chung (xem mục 7). Ảnh xác nhận: media collection `visit_evidence`.

### 2.11 Media (không tạo bảng riêng)

`spatie/laravel-medialibrary`, collection: `decision_documents`, `identity_documents`, `death_certificates`, `medical_certificates`, `visit_evidence`. Custom properties: `document_type`, `issued_date`, `issued_by`. Luôn qua `App\Modules\Core\Services\MediaService::uploadOne()/uploadMany()`.

---

## 3. Models & quan hệ Eloquent

```php
// Household.php
class Household extends TenantModel
{
    public function residentialArea(): BelongsTo { return $this->belongsTo(ResidentialArea::class); }
    public function beneficiaries(): HasMany { return $this->hasMany(Beneficiary::class); }
    public function dependents(): HasMany { return $this->hasMany(Dependent::class); }
}

// Beneficiary.php
class Beneficiary extends TenantModel
{
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
    public function classifications(): HasMany { return $this->hasMany(BeneficiaryClassification::class); }
    public function primaryClassification(): HasOne { return $this->hasOne(BeneficiaryClassification::class)->where('is_primary', true); }
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Dependent::class, 'beneficiary_dependent_relations')
            ->using(BeneficiaryDependentRelation::class)
            ->withPivot(['relationship_type', 'eligible_from', 'eligible_until', 'status', 'note'])
            ->withTimestamps();
    }
    public function subsidyGrants(): MorphMany { return $this->morphMany(SubsidyGrant::class, 'subject'); }
    public function activeSubsidyGrants(): MorphMany { return $this->subsidyGrants()->where('status', 'active'); }
    public function statusHistories(): MorphMany { return $this->morphMany(StatusHistory::class, 'subject'); }
    public function visitSchedules(): MorphMany { return $this->morphMany(VisitSchedule::class, 'subject'); }
}

// Dependent.php
class Dependent extends TenantModel
{
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
    public function beneficiaries(): BelongsToMany
    {
        return $this->belongsToMany(Beneficiary::class, 'beneficiary_dependent_relations')
            ->using(BeneficiaryDependentRelation::class)
            ->withPivot(['relationship_type', 'eligible_from', 'eligible_until', 'status', 'note'])
            ->withTimestamps();
    }
    public function subsidyGrants(): MorphMany { return $this->morphMany(SubsidyGrant::class, 'subject'); }
}

// BeneficiaryDependentRelation.php — custom Pivot (KHÔNG dùng bare Pivot vì cần Observer)
class BeneficiaryDependentRelation extends Model
{
    protected $table = 'beneficiary_dependent_relations';
    public function beneficiary(): BelongsTo { return $this->belongsTo(Beneficiary::class); }
    public function dependent(): BelongsTo { return $this->belongsTo(Dependent::class); }
}

// SubsidyGrant.php
class SubsidyGrant extends TenantModel
{
    public function subject(): MorphTo { return $this->morphTo(); }
    public function policy(): BelongsTo { return $this->belongsTo(SubsidyPolicy::class, 'beneficiary_subsidy_policy_id'); }
}

// VisitSchedule.php — implements Remindable (tái dùng hạ tầng reminder chung)
class VisitSchedule extends TenantModel implements Remindable
{
    public function subject(): MorphTo { return $this->morphTo(); }
    public function reminders(): MorphMany { return $this->morphMany(Reminder::class, 'remindable'); }

    public function getReminderDeadline(): ?Carbon { return $this->scheduled_date?->startOfDay(); }
    public function getReminderOrganizationId(): int { return $this->organization_id; }
    public function getReminderModuleKey(): string { return 'beneficiary'; }
    public function getReminderEventKeys(): array { return ['beneficiary.visit_reminder']; }
    public function getReminderEventKey(?string $moment): string { return 'beneficiary.visit_reminder'; }
    public function resolveReminderRecipients(): Collection { return collect([$this->assigned_to]); }
    public function resolveGuestReminderRecipients(): Collection { return collect(); }
    public function isValidForReminder(): bool { return $this->status === ScheduleStatusEnum::Pending->value; }
}
```

---

## 4. Enums (mỗi enum bắt buộc `values()` + `rule()`)

```php
enum BeneficiaryStatusEnum: string
{
    case Pending   = 'pending';
    case Active    = 'active';
    case Deceased  = 'deceased';
    case MovedOut  = 'moved_out';
    case Suspended = 'suspended';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}

enum DependentRelationStatusEnum: string
{
    case Active    = 'active';
    case Expired   = 'expired';
    case Suspended = 'suspended';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}

enum SubsidyStatusEnum: string
{
    case Active      = 'active';
    case Terminated  = 'terminated';
    case Suspended   = 'suspended';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}

enum ScheduleStatusEnum: string
{
    case Pending = 'pending';
    case Done    = 'done';
    case Skipped = 'skipped';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}
```

Các enum còn lại (`GenderEnum`, `BeneficiaryTypeEnum` — 12 nhóm theo Pháp lệnh 02/2020, `DependentRelationshipEnum`, `DependentEligibilityEnum`, `DocumentTypeEnum`, `VisitOccasionEnum`) viết theo đúng mẫu trên, giá trị lấy từ danh sách ở `docs/database/Beneficiary.md` mục 13.

---

## 5. Service/Controller layer — bộ action theo từng resource

| Resource | Controller/Service độc lập? | Bộ action đã triển khai |
|---|---|---|
| `Beneficiary` | Có, đầy đủ | `stats, index, show, store, update, destroy, bulkDestroy, bulkUpdateStatus, changeStatus, export, import` + nested `status-histories` (read-only) |
| `Household` | Có, **rút gọn** | `stats, index, show, store, update, destroy, bulkDestroy, export, import` — **không có `bulkUpdateStatus`/`changeStatus`**: bảng `beneficiary_households` không có cột `status` (không có vòng đời trạng thái theo thiết kế), thêm 2 action này sẽ vô nghĩa |
| `Dependent` | Có, **rút gọn** | `stats, index, show, store, update, destroy, bulkDestroy, export, import` + `storeRelation`/`destroyRelation` (quản lý pivot `beneficiary_dependent_relations`, thay cho `syncBeneficiaries()` — tách 2 action rõ ràng hơn 1 action sync hàng loạt) + nested `status-histories`. Không có `bulkUpdateStatus`/`changeStatus` — lý do như `Household` |
| `SubsidyPolicy` | Có, **rút gọn** | `stats, index, show, store, update, destroy, bulkDestroy, export, import` + `renew` (đóng policy cũ, tạo policy mới, nối tiếp toàn bộ grant active — Luồng 5 bước 3). Không có `bulkUpdateStatus`/`changeStatus` — không có cột `status`, hiệu lực xác định bởi `effective_from`/`effective_to` |
| `SubsidyGrant` | **Không** route độc lập đủ CRUD | Chỉ `index, store, changeStatus` — tạo/dừng gắn theo luồng nghiệp vụ, không free-form CRUD/import/export |
| `StatusHistory` | Không | Chỉ `GET /beneficiaries/{id}/status-histories`, `GET /beneficiary-dependents/{id}/status-histories` (read-only, không Controller riêng) |
| `VisitSchedule` | Có nhưng rút gọn | `index, show, changeStatus` (`done`/`skipped` + upload `visit_evidence`) — không có `store` tay (sinh tự động bởi Console Command), không có `bulkDestroy/import/export` |

**Lý do rút gọn `SubsidyGrant`/`StatusHistory`/`VisitSchedule`:** đây là bản ghi phát sinh từ hành động nghiệp vụ (cấp trợ cấp, đổi trạng thái, sinh lịch tự động), không phải danh mục người dùng tự do CRUD — thêm đủ bộ action chuẩn cho chúng sẽ tạo endpoint không ai dùng, vi phạm Simplicity First (CLAUDE.md §2). `Household`/`Dependent`/`SubsidyPolicy` không có `bulkUpdateStatus`/`changeStatus` vì không có cột `status` — quyết định khi viết migration thực tế (khác giả định ban đầu ở bản nháp đầu tiên của tài liệu này).

**Policy:** không tạo `BeneficiaryPolicy`/`HouseholdPolicy`/`DependentPolicy` — không có ownership rule nào ngoài permission (không có khái niệm "assigned_to"/"assigned_by" như `TaskAssignmentItem`), khớp tiền lệ `TaskAssignmentDepartment`/`MeetingType` (resource danh mục không có Policy riêng, chỉ dựa vào Spatie permission middleware).

`DB::transaction()` áp dụng ở:
- `HouseholdService::store()` khi tạo hộ + gán thành viên cùng lúc.
- `BeneficiaryService::store()` khi tạo hồ sơ + classifications.
- `DependentService::addRelation()` khi tạo quan hệ pivot.
- `BeneficiaryService::changeStatus()` khi đổi trạng thái + ghi status_histories + dừng grant.
- `SubsidyPolicyService::renew()` khi đóng policy cũ + tạo policy mới + nối tiếp toàn bộ grant active.

---

## 6. Event-Driven — wiring cụ thể

| Event | Fire ở đâu | Vì sao | Listener |
|---|---|---|---|
| `BeneficiaryStatusChanged` | Trực tiếp trong `BeneficiaryService::changeStatus()` | 1 đường ghi duy nhất qua Service | Ghi `beneficiary_status_histories`; rà soát lại điều kiện hưởng tuất của `dependents`; dừng `subsidyGrants` active |
| `DependentEligibilityExpired` | **`BeneficiaryDependentRelationObserver::updated()`** (không fire trong Service/Job) | 2 đường ghi độc lập vào `status`: Service (cán bộ đổi tay) + `CheckDependentEligibilityCommand` (Job update trực tiếp Eloquent) — chỉ Observer đảm bảo Event fire ở cả 2 nhánh | Ghi `beneficiary_status_histories`; chuyển `subsidyGrants` active → `terminated`; gửi Zalo OA nhắc cán bộ xác minh |
| `SubsidyGranted` / `SubsidyTerminated` | Trong `SubsidyGrantService` | 1 đường ghi | Ghi log; gửi thông báo nếu nghiệp vụ yêu cầu (xem mục 7) |

Code mẫu Observer (điểm quan trọng nhất của toàn bộ thiết kế Event, dễ làm sai nếu không đọc kỹ):

```php
// Observers/BeneficiaryDependentRelationObserver.php
class BeneficiaryDependentRelationObserver
{
    public function updated(BeneficiaryDependentRelation $relation): void
    {
        if ($relation->wasChanged('status') && $relation->status === DependentRelationStatusEnum::Expired->value) {
            event(new DependentEligibilityExpired($relation));
        }
    }
}

// Console/Commands/CheckDependentEligibilityCommand.php — chỉ update(), KHÔNG tự gọi thông báo
BeneficiaryDependentRelation::where('status', DependentRelationStatusEnum::Active->value)
    ->whereHas('dependent', fn ($q) => $q->whereDate('date_of_birth', '<=', now()->subYears(18)))
    ->get()
    ->each(function (BeneficiaryDependentRelation $r) {
        if (!in_array($r->dependent->eligibility_status, ['studying', 'disabled_no_work_capacity'])) {
            $r->update(['eligible_until' => $r->dependent->date_of_birth->addYears(18), 'status' => DependentRelationStatusEnum::Expired->value]);
            // Observer::updated() tự fire DependentEligibilityExpired — không gọi event() ở đây
        }
    });
```

Mọi Event ghi DB rồi fire notification: dùng `ShouldDispatchAfterCommit`.

---

## 7. Notification & Reminder — tái dùng hạ tầng chung, không viết mới

**Nguyên tắc:** module này không tạo bảng `reminders` riêng, không viết Job gửi thông báo riêng. Toàn bộ dùng lại:

```
reminders (bảng CHUNG, polymorphic remindable_type/remindable_id)
    ← ReminderScheduler::scheduleFor($visitSchedule)   // gọi ngay sau khi tạo VisitSchedule
    ← ProcessRemindersCommand (đã chạy nền sẵn)          // gửi khi đến remind_at

notification_event_configs (module_key='beneficiary')   // cán bộ phường tự cấu hình "nhắc trước N ngày"
    └── notification_schedules
```

**Việc cần thêm (chỉ là đăng ký code, không phải hạ tầng mới):**

| File | Thay đổi |
|---|---|
| `app/Services/Notification/Enums/NotificationModuleEnum.php` | Thêm `case Beneficiary = 'beneficiary';` + label "Người có công" trong `label()` |
| `app/Services/Notification/Enums/NotificationEventEnum.php` | Thêm case cho `beneficiary.visit_reminder` (bắt buộc — dùng cho `VisitSchedule::getReminderEventKeys()`), và `beneficiary.status_changed`/`beneficiary.eligibility_expired` nếu quyết định gửi Zalo (xem quyết định mở dưới) |
| `app/Services/Notification/ContentBuilders/BeneficiaryVisitReminderContentBuilder.php` | Copy mẫu `ScheduleReminderContentBuilder.php`, đổi nội dung nhắc viếng thăm |
| `app/Modules/Beneficiary/Routes/notification_config.php` | Copy mẫu `app/Modules/TaskAssignment/Routes/notification_config.php`, đổi middleware `notification.module:beneficiary` |
| `app/Modules/Beneficiary/Console/Commands/GenerateVisitSchedulesCommand.php` | Sinh `beneficiary_visit_schedules` đầu năm/dịp lễ (data-integrity, không fire Event) → gọi `ReminderScheduler::scheduleFor()` cho từng record |

**Quyết định còn mở (cần chốt cùng nghiệp vụ trước khi thêm case vào `NotificationEventEnum`):** `BeneficiaryStatusChanged`/`DependentEligibilityExpired` có cần gửi Zalo OA ngay bản đầu, hay bản đầu chỉ cần ghi `beneficiary_status_histories`? Nếu chưa cần gửi → để Event là internal (không đăng ký `NotificationEventEnum`), thêm sau không phải sửa lại gì đã viết.

---

## 8. Permissions (`database/seeders/PermissionSeeder.php`)

```
beneficiaries.{stats,index,show,store,update,destroy,bulkDestroy,bulkUpdateStatus,changeStatus,export,import}
beneficiary-households.{stats,index,show,store,update,destroy,bulkDestroy,bulkUpdateStatus,changeStatus,export,import}
beneficiary-dependents.{stats,index,show,store,update,destroy,bulkDestroy,bulkUpdateStatus,changeStatus,export,import}
beneficiary-subsidy-grants.{store,changeStatus}
beneficiary-visit-schedules.{index,show,changeStatus}
notifications.event-configs.{index,update}        ← dùng chung, module_key=beneficiary
notifications.schedules.{index,store}
notifications.logs.{index,show,destroy,bulkDestroy,export,stats}
```

---

## 9. Quyết định kiến trúc đã chốt cho bản đầu

1. **Tên module:** `Beneficiary`.
2. **`beneficiary_subsidy_policies`/`beneficiary_subsidy_grants`:** giữ trong module (không tách engine dùng chung), vì chưa có module thứ 2 xác nhận cần dùng. **Cần tạo ADR** `docs/decisions/ADR-xxx-subsidy-scope-trong-module-beneficiary.md` ghi lại lý do, để khi có module Hộ nghèo/Bảo trợ xã hội xuất hiện thì tách ra `Core` không phải thiết kế lại.
3. **`beneficiary_visit_schedules`:** không có Job/Notification riêng — bắt buộc implement `Remindable`, dùng `ReminderScheduler`/`ProcessRemindersCommand` có sẵn.
4. **`DependentEligibilityExpired`:** bắt buộc fire qua Observer (mục 6), không fire trong Job hay Service, vì 2 đường ghi.
5. **Còn mở:** có gửi Zalo cho `BeneficiaryStatusChanged`/`DependentEligibilityExpired`/`SubsidyGranted`/`SubsidyTerminated` ngay bản đầu không (mục 7) — cần xác nhận trước khi đăng ký `NotificationEventEnum`.

---

## 10. Thứ tự triển khai

1. Migration theo đúng thứ tự phụ thuộc FK: `beneficiary_residential_areas` → `beneficiary_households` → `beneficiaries` → `beneficiary_classifications` → `beneficiary_dependents` → `beneficiary_dependent_relations` → `beneficiary_subsidy_policies` → `beneficiary_subsidy_grants` → `beneficiary_status_histories` → `beneficiary_visit_schedules`.
2. Enums (mục 4) — viết trước Model vì Model/Request cần reference.
3. Models + quan hệ (mục 3), Factories đúng namespace `Database\Factories\Modules\Beneficiary\Models\{Model}Factory` (Scribe yêu cầu).
4. Observers (mục 6) — đăng ký trong `AppServiceProvider` hoặc `BeneficiaryServiceProvider` nếu module có provider riêng.
5. Services + Controllers + FormRequests + Resources theo bộ action ở mục 5.
6. Policies (`BeneficiaryPolicy`, `DependentPolicy`, `HouseholdPolicy`) — chặn cross-tenant, không check tay trong Service.
7. `PermissionSeeder` (mục 8) → `sail artisan db:seed --class=PermissionSeeder`.
8. Đăng ký Notification (mục 7) — `NotificationModuleEnum`, `NotificationEventEnum`, ContentBuilder, `notification_config.php`.
9. Console Commands (`CheckDependentEligibilityCommand`, `GenerateVisitSchedulesCommand`) → đăng ký `routes/console.php` với `withoutOverlapping()`.
10. `sail artisan scribe:generate` sau khi Controller/FormRequest hoàn chỉnh; kiểm tra `.scribe/endpoints/*.yaml`.
11. Cập nhật `docs/modules/Beneficiary/README.md` và `docs/database/Beneficiary.md` nếu có sai khác phát sinh khi code thật (2 file này mô tả cùng thiết kế, chi tiết hơn — dùng làm tài liệu tham chiếu song song với file này, không thay thế).

---

## 11. Checklist trước khi merge (theo CLAUDE.md §11)

- [ ] Controller chỉ validate → gọi Service → trả response.
- [ ] `HouseholdService::store()` và `SubsidyGrantService` (đổi policy hàng loạt) đã bọc `DB::transaction()`.
- [ ] Upload giấy tờ/ảnh viếng thăm đi qua `MediaService`, không gọi `addMedia()`/`Storage::put` trực tiếp.
- [ ] Mọi query scope đúng `organization_id`, chặn cross-tenant ở `show/update/destroy/changeStatus` + bulk.
- [ ] `BeneficiaryStatusChanged` fire trong Service; `DependentEligibilityExpired` fire trong Observer — không đảo ngược.
- [ ] Event ghi DB + fire notification dùng `ShouldDispatchAfterCommit`.
- [ ] `CheckDependentEligibilityCommand`/`GenerateVisitSchedulesCommand` có `withoutOverlapping()`, loop `withoutGlobalScope('organization')` theo từng tổ chức (đây là Job cross-tenant, không chạy trong context 1 tenant như request thường).
- [ ] Không tạo Controller/route CRUD đầy đủ cho `SubsidyGrant`/`StatusHistory`/`VisitSchedule` (chỉ action cần thiết — mục 5).
- [ ] `VisitSchedule` implement đủ `Remindable`, không tự viết bảng/Job reminder riêng.
