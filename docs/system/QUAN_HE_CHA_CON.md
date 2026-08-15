# Xử lý quan hệ cha — con (module mới)

> Ngày tạo: 19:45:19 14/08/2026  
> Cập nhật lần cuối: 09:12:44 15/08/2026

Tài liệu chuẩn cho Claude Code và dev khi thêm quan hệ cha — con vào **module mới**. Bổ sung cho [CLAUDE.md](../../CLAUDE.md), không thay thế.

> **Vai trò của file này: quy tắc và lý do.** Nó **không chứa tập tin hoàn chỉnh** — mã đầy đủ, copy chạy được nằm ở [QUAN_HE_CHA_CON_VIDU.md](QUAN_HE_CHA_CON_VIDU.md) (44 tập tin của module mẫu `Employee`). Mọi khối mã ở đây là **khung xương** nêu đúng phần bắt buộc của quy tắc, kèm con trỏ tới mục tương ứng bên file ví dụ.
>
> Sửa quy tắc → sửa file này trước, rồi cập nhật mã bên file ví dụ. Đừng sao chép tập tin hoàn chỉnh ngược về đây: một dòng mã chỉ nên tồn tại ở một nơi.

---

## 0. Phạm vi áp dụng — đọc trước tiên

**Chỉ áp dụng cho module mới và quan hệ mới.** Module đã làm (`Auth`, `Core`, `Meeting`, `Scheduling`, `TaskAssignment`) **giữ nguyên nghiệp vụ và cách làm hiện tại** — không refactor theo tài liệu này, không coi code cũ là sai.

> `Beneficiary` (Người có công) đã gỡ bỏ ngày 15/08/2026, dựng lại từ đầu theo thiết kế đơn giản hoá → tính là **module mới**, bắt buộc theo tài liệu này. Xem [answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md](../answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md).

| | Module cũ | Module mới |
|---|---|---|
| Quy ước chung Danatec (đặt tên bảng, thư mục, `TenantModel`, `RespondsWithJson`, Enum, permission, Scribe, Export/Import) | Giữ nguyên | **Bắt buộc** |
| `SoftDeletes` cho cha + con | Giữ nguyên (phần lớn chưa có) | **Bắt buộc** |
| `belongsToMany()->sync()` cho quan hệ n–n | Giữ nguyên (3 chỗ đang dùng) | **Cấm** — xem §2 dạng D |
| Endpoint `save-full` + optimistic lock | Không thêm | **Bắt buộc** cho bảng chính có form trọn gói |
| Nested route `cha/{id}/con` | Giữ nguyên (module cũ dùng route phẳng) | **Bắt buộc** cho sub-resource của form trọn gói |

**Điều kiện tiên quyết:** không cần sửa gì trong `Core`. Tài liệu này dùng spatie trực tiếp — xem **Phụ lục A** để biết quyết định đó đánh đổi những gì và cần chỉnh câu chữ nào trong CLAUDE.md.

**Media viết thẳng trong service, không qua `MediaService`.** Ngoại lệ có chủ đích so với CLAUDE.md §4, áp dụng cho **module mới theo tài liệu này**; module cũ giữ nguyên `MediaService`.

| Việc | Cách làm |
|---|---|
| Đọc media hiện có | `$model->getMedia($collection)` |
| Ghi file mới | `$model->addMedia($file)->toMediaCollection($collection)` trong vòng lặp có `isValid()` |
| Tính sai biệt | `$existing->reject(...)` trên snapshot của **chính** record đó |
| Xoá | `$trash->each->delete()`, **sau** commit |

Lý do: phần khó của luồng này là **thứ tự** snapshot → commit → ghi → xoá, mà thứ tự nằm ở service gọi — không lớp bọc nào ép được. Bọc thêm chỉ tạo cảm giác an toàn giả và bắt người đọc mở thêm file. Đọc một file service là thấy trọn luồng, đúng nguyên tắc trình bày ở trên.

Đánh đổi phải biết: spatie lưu file theo **tên gốc đã sanitize** (`/{media_id}/hop-dong-nguyen-van-a.pdf`) chứ không hash. Xem §17.1 để biết khi nào phải đổi sang disk private.

**Cách dùng tài liệu:** đây là khuôn mẫu để copy, không phải lý thuyết để suy luận.

1. Xác định **dạng quan hệ** theo bảng §2.
2. Mở mục tương ứng ở [file ví dụ](QUAN_HE_CHA_CON_VIDU.md), copy tập tin, đổi tên module/model/bảng/`FILLABLE`.
3. Chạy checklist §22, đối chiếu bảng Cấm §23.
4. Gặp tình huống tài liệu không phủ (§25) → **dừng lại và hỏi**, không suy diễn từ khuôn mẫu gần nhất.

**Ví dụ xuyên suốt:** module giả định `Employee` (hồ sơ nhân sự) — bảng chính `employees`, ba bảng con 1–n, một bảng 1–1, một danh mục và một bảng nối. Không phải module có thật trong repo; dùng làm khuôn.

---

## 1. Bốn quy tắc

> **1.** Mọi bảng — kể cả bảng chính — có một bộ resource CRUD đầy đủ.
> **2.** Mỗi bảng chính có thêm một endpoint `save-full` gộp bản chính + toàn bộ danh sách con.
> **3.** `save-full` **không tự ghi bản chính** — gọi lại service của resource bản chính.
> **4.** Mọi endpoint ghi trả về `lock_version` (bản chính) hoặc `parent_lock_version` (dòng con) trong `data`.

**Ngoại lệ duy nhất của quy tắc 1:** quan hệ 1–1 chỉ có `GET` + `PUT` upsert. `POST` vô nghĩa vì `UNIQUE(parent_id)` khiến lần thứ hai luôn hỏng; `DELETE` vô nghĩa vì để lại bản ghi cha thiếu thông tin mà không có trạng thái nào ghi nhận.

Quy tắc 3 là thứ giữ cho mô hình không rã: chỉ có **một chỗ ghi bản chính**, nên `assertNotStale`, `lockForUpdate` và `touch()` chỉ cần đặt đúng một lần và không đường nào bỏ sót được.

### 1.1. Ranh giới với bộ chức năng chuẩn của CLAUDE.md B3

| Loại resource | Bộ action |
|---|---|
| **Bảng chính của module** | `stats, index, show, store, update, destroy, bulkDestroy, export, import` **+ `saveFull`**, cộng `changeStatus`/`bulkUpdateStatus` **nếu** nghiệp vụ có trạng thái (CLAUDE.md B3) |
| **Bảng con (dạng A, B, D)** | 6: `index, show, store, update, destroy, bulkDestroy` |
| **Bảng 1–1 (dạng C)** | 2: `show, update` (upsert) |
| **Danh mục dùng chung (dạng E)** | 1 trong module nghiệp vụ: `index` (CRUD quản trị nằm ở module hệ thống) |

Bảng con **không** làm `stats/export/import/changeStatus`: dữ liệu con đã đi kèm export của bản chính (§18), và import file phẳng không nhận mảng lồng nhau (CLAUDE.md §6). Cần thêm ngoài bộ trên → phải có lý do nghiệp vụ ghi trong PR.

### 1.2. Frontend chọn đường theo màn hình

| Màn hình | Đường |
|---|---|
| Form trọn gói, **không phân trang** | `save-full` |
| Bảng có phân trang | Sub-resource CRUD lẻ |
| Chỉ sửa vài trường của bản chính | Resource bản chính |
| Tạo mới nhiều bước, lưu từng tab | Sub-resource CRUD lẻ, lưu ngay mỗi bước |

**Ràng buộc duy nhất không kiểm chứng được ở backend:** không gọi `save-full` từ màn hình có phân trang. `whereNotIn` sẽ xoá mềm toàn bộ phần chưa load và response vẫn trả 200. Đây là quy ước khác mọi quy ước khác ở chỗ **vi phạm nó không tạo ra lỗi**.

---

## 2. Bảng quyết định — gặp quan hệ mới thì làm gì

| Dạng | Nhận biết | Mã mẫu đầy đủ | Endpoint |
|---|---|---|---|
| **A. 1–n có file** | `hasMany`, dòng con có tài liệu đính kèm | VIDU §3.2, §8.6, §9.3, §10.2 | 6 action |
| **B. 1–n không file** | `hasMany`, chỉ có cột dữ liệu | VIDU §3.3, §8.8, §9.4, §10.3 | 6 action |
| **C. 1–1** | `hasOne`, `UNIQUE(parent_id)` | VIDU §3.4, §8.9, §9.5, §10.4 | `show` + `update` |
| **D. n–n có thuộc tính** | Bảng nối mang cột nghiệp vụ | VIDU §3.6, §8.10, §9.7, §10.5 — xử lý **y hệt A** | 6 action |
| **E. Danh mục dùng chung** | `organization_id = NULL`, nhiều module dùng | VIDU §3.5, §9.6, §10.6 | `index` |

**Không có dạng "n–n thuần".** Nếu bảng nối chỉ có hai khoá ngoại hôm nay thì mai sẽ có người thêm cột `ghi_chú`. Luôn làm theo dạng D.

**Cấm `belongsToMany()->sync()` trong module mới.** Lý do ở §21.6. Ba chỗ đang dùng trong `Scheduling` và `TaskAssignment` giữ nguyên — ghi nhận là nợ kỹ thuật, không refactor kèm.

---

## 3. Đặt tên

```
Module            app/Modules/Employee/
Bảng chính        employees
Bảng con 1–n      employee_educations, employee_work_experiences, employee_family_relationships
Bảng con 1–1      employee_details                  (số nhiều, dù chỉ một dòng)
Danh mục          employee_skills                   (tiền tố module — CLAUDE.md §2)
Bảng nối dạng D   employee_skill_relations          ({bảng_cha_số_ít}_{bảng_con_số_ít}_relations)

Model             Employee, EmployeeEducation, EmployeeDetail, EmployeeSkill, EmployeeSkillRelation
Quan hệ Model     educations(), employeeDetail(), skillRelations()
Namespace         App\Modules\Employee\{Models,Controllers,Services,Requests,Resources,Enums,Routes}

Prefix route      employees                         (đăng ký trong routes/api.php)
Segment URL       /api/employees/{employee}/educations
                  /api/employees/{employee}/detail          (số ít cho 1–1)
                  /api/employees/{employee}/skill-relations
                  /api/employee-skills                      (danh mục — ngoài phạm vi {employee})

Permission        employees.*, employee-educations.*, employee-details.*,
                  employee-skill-relations.*, employee-skills.index

Field JSON        educations_json, detail_json, skill_relations_json
Field file        educations_files[i][], citizen_front, citizen_back
```

> Danh mục `employee_skills` và bảng nối `employee_skill_relations` chỉ khác một từ — luôn gọi bảng nối là "bảng nối" trong comment và docblock để người đọc không nhầm.

---

## 4. Migration

> Mã đầy đủ 7 migration: **VIDU §1**.

Bốn thứ **bắt buộc** có ở mọi bảng, cha lẫn con:

```php
$table->foreignId('organization_id')->constrained()->cascadeOnDelete();
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
$table->softDeletes();
$table->index(['organization_id', 'employee_id']);   // bảng con: index theo CẶP
```

`softDeletes()` ở **bảng cha** là bắt buộc chứ không tuỳ chọn: bảng con dùng `onDelete('cascade')`, nên cha xoá cứng sẽ khiến MySQL xoá cứng toàn bộ dòng con — bỏ qua SoftDeletes của chúng, bỏ qua model event, và để lại file media mồ côi trên đĩa (§21.10).

Ba ràng buộc riêng theo dạng:

| Dạng | Ràng buộc | Hệ quả |
|---|---|---|
| C (1–1) | `foreignId('employee_id')->unique()` | POST lần hai luôn hỏng → chỉ có GET + PUT; kèm SoftDeletes sinh bẫy §21.5 |
| D (bảng nối) | `unique(['employee_id', 'employee_skill_id'])` + `id` riêng + `softDeletes()` | Cần nhánh restore (§9.3) |
| D → danh mục | `onDelete('restrict')` | Xoá một mục danh mục đang được dùng phải bị chặn, không cascade |

Sau migration: cập nhật `docs/database/Employee.md` (CLAUDE.md §10).

---

## 5. Model

> Mã đầy đủ 7 model: **VIDU §3**.

Khung bắt buộc của **mọi** model con:

```php
class EmployeeEducation extends TenantModel implements HasMedia   // TenantModel, không phải Model
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    // Bump employees.updated_at mỗi khi dòng con đổi. Đây là cơ chế DUY NHẤT bắt
    // được xung đột giữa màn hình sub-resource và màn hình save-full. KHÔNG ĐƯỢC BỎ.
    protected $touches = ['employee'];

    // employee_id và organization_id KHÔNG nằm trong fillable:
    //   - employee_id     → luôn gán qua quan hệ, đó là cơ chế chặn IDOR
    //   - organization_id → TenantModel tự gán khi creating
    protected $fillable = ['school_name', /* ... */ 'created_by', 'updated_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->created_by = $m->updated_by = auth()->id());
        static::updating(fn (self $m) => $m->updated_by = auth()->id());
    }
}
```

Dạng B bỏ `implements HasMedia`, `InteractsWithMedia` và `registerMediaCollections()`. Dạng C thêm hai collection `singleFile()` cho hai ô ảnh cố định. Dạng D là bảng nối nhưng **không** `extends Pivot` — nó cần khoá chính riêng để spatie gắn media, và cần model event để `$touches` nổ.

---

## 6. Enum

Mọi cột giá trị giới hạn (`gender`, `marital_status`, `level`, `status`, `relationship`) phải là Enum theo CLAUDE.md §2 — **không** để `string|max:30`.

```php
public function label(): string { /* match ($this) { ... } */ }
public static function values(): array { return array_column(self::cases(), 'value'); }
public static function rule(): string { return 'in:'.implode(',', self::values()); }
```

> Mã đầy đủ 5 Enum: **VIDU §2**. Dùng trong FormRequest: `'gender' => ['nullable', GenderEnum::rule()]`.

Module có ≥1 Enum dùng cho dropdown FE → bắt buộc có `EnumController` + route `employee-enums` (CLAUDE.md §2). Enum của **dòng con** cũng gộp vào cùng endpoint đó — không tạo endpoint enum riêng cho bảng con.

---

## 7. Service bản chính

> Mã đầy đủ: **VIDU §9.1**.

`update()` là **chỗ ghi bản chính duy nhất** — cả `EmployeeController::update` lẫn `saveFull` đều đi qua đây (quy tắc 3). Ba bước dưới đây không được đổi:

```php
public function update(Employee $employee, array $data, ?string $clientLockVersion = null): Employee
{
    return DB::transaction(function () use ($employee, $data, $clientLockVersion) {
        // 1. Đọc lại kèm khoá dòng — KHÔNG kiểm tra trên instance từ route model
        //    binding (dữ liệu tại thời điểm dispatch, hai request song song cùng pass).
        $locked = Employee::whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

        // 2. Optimistic lock BÊN TRONG transaction (§21.2)
        $this->assertNotStale($locked, $clientLockVersion);

        $locked->update(Arr::only($data, self::FILLABLE));

        // 3. touch() tường minh: không field nào dirty thì update() không đổi
        //    updated_at, và optimistic lock của người khác vẫn thấy giá trị cũ.
        $locked->touch();

        return $locked;
    });
}
```

`update()` **không xử lý avatar**: nó còn được gọi từ bên trong transaction của `saveFull`, mà upload phải chạy sau commit. Avatar do `syncAvatar()` lo, và mọi caller phải gọi `syncAvatar()` **sau** khi transaction đã commit.

So `lock_version` bằng `->timestamp` (giây), **không** dùng `Carbon::ne()` — lý do ở §21.8.

`StaleRecordException` đặt ở `Core\Exceptions` (dùng lại cho mọi module mới), trả đúng format lỗi chung của `RespondsWithJson` — **không** tự chế `response()->json(['message' => ...], 409)`. Mã: **VIDU §5.1**.

---

## 8. `save-full` — endpoint gộp

> Mã đầy đủ: **VIDU §9.1** (`saveFull`).

**CẤM gọi từ màn hình có phân trang.** `whereNotIn` xoá mọi dòng không có trong mảng gửi lên; frontend chỉ giữ một trang trong state thì toàn bộ phần chưa load bị xoá mềm, và response vẫn trả 200. Ràng buộc này **không kiểm chứng được ở backend**.

Khung xương — **thứ tự bốn bước không được đổi**:

```php
$saved = DB::transaction(function () use (...) {
    // Quy tắc 3: không tự ghi bản chính, gọi lại service của resource.
    $employee = $employee ? $this->update($employee, $data, $data['lock_version'] ?? null)
                          : $this->store($data);

    // array_key_exists chứ không phải isset/!empty: mảng đến từ JSON đã decode nên
    // [] (xoá hết) và vắng mặt (không quản lý) là hai trạng thái phân biệt được.
    if (array_key_exists('educations', $data)) { /* syncEducations → gom $pendingUploads, trả $trash */ }
    if (! empty($data['detail']))              { /* $detail = $this->details->upsert(...) */ }

    // whereNotIn xoá qua Query Builder nên KHÔNG kích hoạt $touches. Request chỉ xoá
    // dòng con thì không model nào được save → optimistic lock mù (§21.4).
    $employee->touch();

    return $employee;
});

// 1. snapshot media   (đã làm trong transaction, TRƯỚC mọi ghi file)
// 2. commit           (dòng trên)
// 3. ghi file mới     ← ngoài transaction: lockForUpdate đang giữ dòng cha, copy hàng
//                       chục file trong đó khiến request thứ hai chờ tới
//                       innodb_lock_wait_timeout rồi 500, thay vì nhận 409 sạch sẽ
// 4. xoá file cũ SAU CÙNG — bước 3 ném lỗi thì file cũ vẫn còn nguyên
$trashMedia->each->delete();

// Fire event ở ĐÂY, sau khi file đã yên vị (§19).
event(new EmployeeProfileSaved($saved->id, $saved->organization_id));
```

`$detail` gán bên trong closure **bắt buộc** capture by reference (`use (..., &$detail)`) và khởi tạo `= null` trước — §21.7.

---

## 9. Các hàm sync (chỉ dùng bởi `saveFull`)

> Mã đầy đủ bốn hàm: **VIDU §9.1** (`syncEducations`, `syncWorkExperiences`, `syncFamilyRelationships`, `syncSkillRelations`).

### 9.1. Dạng A — khuôn mẫu chính

```php
// Chụp id hiện có để (1) phân biệt update với create và (2) chặn client gửi id thuộc
// bản ghi cha khác. KHÔNG withTrashed() — xem 9.3.
$existingIds = $employee->educations()->pluck('id')->all();

foreach ($rows as $index => $row) {
    // findOrFail chạy TRÊN quan hệ nên đã giới hạn trong phạm vi cha — chặn IDOR.
    // KHÔNG ĐƯỢC đổi thành EmployeeEducation::find().
    $education = ! empty($row['id']) && in_array((int) $row['id'], $existingIds, true)
        ? tap($employee->educations()->findOrFail($row['id']))->update($attributes)
        : $employee->educations()->create($attributes);

    $keepIds[] = $education->id;

    // SNAPSHOT phải chụp TRƯỚC khi có bất kỳ file mới nào được ghi (§21.9).
    $existingMedia = $education->getMedia(EmployeeEducation::MEDIA_COLLECTION);

    // File gom lại, ghi sau commit — không ghi tại chỗ.
    $pendingUploads[] = [$education, EmployeeEducation::MEDIA_COLLECTION, $filesByIndex[$index]];

    // Không có cờ → request không quản lý file → giữ nguyên toàn bộ file cũ.
    if ($row['sync_attachments'] ?? false) {
        $keep = array_map('intval', $row['kept_media_ids'] ?? []);
        // reject trên snapshot của CHÍNH record này → client gửi id lạ cũng không
        // xoá được file của bản ghi khác.
        $allTrashMedia = $allTrashMedia->merge(
            $existingMedia->reject(fn ($m) => in_array((int) $m->id, $keep, true))
        );
    }
}

$employee->educations()->whereNotIn('id', $keepIds)->delete();
```

### 9.2. Dạng B — bỏ toàn bộ phần media

Giữ nguyên `$existingIds` / `$keepIds` / `whereNotIn`, bỏ ba khối media.

### 9.3. Dạng D — chỉ khác ở nhánh restore theo khoá unique

`UNIQUE(employee_id, employee_skill_id)` cộng SoftDeletes tạo ra bẫy §21.5: dòng đã xoá mềm vẫn chiếm chỗ, `create()` thẳng sẽ ném `SQLSTATE 23000` giữa transaction.

```php
if (! empty($row['id']) && in_array((int) $row['id'], $existingIds, true)) {
    $item = tap($employee->skillRelations()->findOrFail($row['id']))->update($attributes);
} else {
    // KHÁC BIỆT DUY NHẤT so với dạng A: dòng mới có thể đụng dòng đã xoá mềm.
    // withTrashed() đặt ở ĐÂY — đúng chỗ khoá unique nằm, không phải ở pluck('id').
    $item = $employee->skillRelations()->withTrashed()
        ->where('employee_skill_id', $attributes['employee_skill_id'])->first();

    if ($item) {
        if ($item->trashed()) { $item->restore(); }
        $item->update($attributes);
    } else {
        $item = $employee->skillRelations()->create($attributes);
    }
}
```

Nhờ restore mà mức độ thành thạo, số năm kinh nghiệm và file chứng chỉ cũ quay lại nguyên vẹn — đúng lý do chọn bảng nối dày thay vì `sync()`.

**Dạng A/B không thêm nhánh này.** Bảng không có unique constraint thì `create()` chạy bình thường, còn `restore()` thừa sẽ âm thầm hồi sinh dòng người dùng đã cố ý xoá.

---

## 10. Service dòng con — dạng A và D

> Mã đầy đủ: **VIDU §9.3** (dạng A), **§9.3b** (dạng A thứ hai), **§9.7** (dạng D).

**Thứ tự ba bước không được đổi:**

```php
public function update(EmployeeEducation $education, array $data): EmployeeEducation
{
    // 1. snapshot TRƯỚC khi upload — chụp sau thì file vừa upload cũng nằm trong danh
    //    sách đối chiếu, mà nó không có trong kept_media_ids → bị xoá ngay (§21.9).
    $existing = $education->getMedia(self::MEDIA_COLLECTION);

    // 2. commit
    DB::transaction(fn () => $education->update(Arr::only($data, self::FILLABLE)));

    // 3. upload sau commit, rồi mới xoá file cũ — cả hai ngoài transaction
    $this->uploadAttachments($education, $data['attachments'] ?? []);

    if ($data['sync_attachments'] ?? false) {
        $keep = array_map('intval', $data['kept_media_ids'] ?? []);
        $existing->reject(fn ($m) => in_array((int) $m->id, $keep, true))->each->delete();
    }

    return $education->load(self::WITH);
}
```

Danh sách eager load là **bắt buộc**, mỗi phần tử bịt một lỗi khác nhau:

```php
private const WITH = ['media', 'employee', 'creator.media', 'editor.media'];
```

- `'media'` — Resource gọi `getMedia()` từng dòng, thiếu là N+1.
- `'employee'` — thiếu thì `whenLoaded` trả `MissingValue` và `parent_lock_version` biến mất khỏi response (quy tắc 4).
- `'creator.media'` / `'editor.media'` — **không phải** `'creator'` / `'editor'`: trait `FormatsUserSummary` gọi `$user->getFirstMedia('avatars')` để lấy ảnh đại diện, nên chỉ load `'creator'` thì mỗi user khác nhau trong trang sinh thêm một query. Load `creator.media` bao hàm luôn `creator`.

Xoá luôn là **xoá mềm**: file đính kèm giữ nguyên trên storage, phục hồi được khi restore bản ghi — bắt buộc với dữ liệu có giá trị pháp lý.

Service dạng D giống hệt, chỉ khác `FILLABLE`, tên quan hệ, và `store()` cần nhánh restore theo khoá unique (§9.3).

---

## 11. Service dạng C — 1–1

> Mã đầy đủ: **VIDU §9.5**.

Chỉ có `show` + `upsert`. Không có `store()` và `destroy()`:

- **POST vô nghĩa:** `UNIQUE(employee_id)` khiến lần thứ hai luôn hỏng, nên nó chỉ là PUT với thêm một cách để trả 500.
- **DELETE vô nghĩa:** xoá dòng chi tiết để lại hồ sơ thiếu thông tin định danh mà không có trạng thái nào ghi nhận việc đó.

Làm đủ 5 endpoint cho 1–1 thì hai trong năm cái cần mã xử lý riêng chỉ để từ chối đúng cách — đó là lý do bỏ.

```php
// withTrashed(): UNIQUE(employee_id) vẫn bị dòng đã xoá mềm chiếm chỗ, nên create()
// sau một lần xoá sẽ ném SQLSTATE 23000. Phải restore, không tạo mới.
$detail = $employee->employeeDetail()->withTrashed()->first();

if ($detail) {
    if ($detail->trashed()) { $detail->restore(); }
    $detail->update($attributes);
    return $detail;
}

return $employee->employeeDetail()->create($attributes);
```

`upsert()` **không xử lý ảnh**: nó còn được gọi từ bên trong transaction của `saveFull`, mà hai collection kia là `singleFile()`. Ảnh do `syncCitizenPhotos()` lo, gọi **sau commit**.

---

## 12. Service dạng E — danh mục dùng chung

> Mã đầy đủ: **VIDU §9.6**.

Danh mục dùng chung (`organization_id = NULL`) — **chỉ đọc** trong phân hệ này. CRUD quản trị danh mục thuộc module quản trị hệ thống: cho người nhập liệu tạo mục mới ngay tại form sẽ sinh rác danh mục ("PHP", "php", "PHP 8") và không có cách nào gộp lại về sau.

Query phải lấy **cả hai** nguồn:

```php
->withoutGlobalScope('organization')
->where(fn ($q) => $q->whereNull('organization_id')          // dùng chung toàn hệ thống
    ->orWhere('organization_id', getPermissionsTeamId()))    // riêng tổ chức hiện tại
```

**Khoá ngoại trỏ tới danh mục phải scope tenant** — `exists:employee_skills,id` trần là lỗ hổng cross-tenant (tổ chức A gán được danh mục riêng của tổ chức B):

```php
Rule::exists('employee_skills', 'id')
    ->where(fn ($q) => $q->whereNull('organization_id')
        ->orWhere('organization_id', getPermissionsTeamId()))
    ->whereNull('deleted_at')
```

---

## 13. FormRequest

> Mã đầy đủ 13 request: **VIDU §8**.

Mọi FormRequest bắt buộc có `rules()`, `messages()` tiếng Việt phủ **mọi** rule đang dùng, `attributes()`, `bodyParameters()` (CLAUDE.md §7). `authorize()` trả `true` — quyền đã chặn ở middleware `permission:` trên route, không kiểm hai lần ở hai nơi.

### 13.1. `save-full` — decode JSON

Mảng dòng con gửi dưới dạng **chuỗi JSON**, không phải mảng lồng FormData:

1. `max_input_vars` (mặc định 1000) cắt phần **đuôi** payload và không báo lỗi. Phần bị cắt có thể là vài phần tử cuối của `kept_media_ids[]` — số dòng vẫn khớp, validate vẫn pass, nhưng những media id bị cắt rơi vào `$trash` và **bị xoá vĩnh viễn** khỏi đĩa. Đếm số dòng không bắt được trường hợp này.
2. JSON chiếm đúng 1 input var mỗi mảng — không còn gì để cắt.
3. JSON phân biệt được `[]` (xoá hết) với vắng mặt (không quản lý) — không cần thêm cờ ở cấp danh sách.

File **không** đi qua JSON: gửi phẳng theo `educations_files[i][]`, khớp với dòng thứ `i` của mảng đã decode.

```php
private const JSON_FIELDS = ['educations', 'work_experiences', 'family_relationships',
                             'skill_relations', 'detail'];

protected function prepareForValidation(): void
{
    foreach (self::JSON_FIELDS as $key) {
        if (! $this->has("{$key}_json")) { continue; }   // vắng mặt = không quản lý

        $rows = json_decode((string) $this->input("{$key}_json"), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($rows)) {
            throw ValidationException::withMessages(["{$key}_json" => "Dữ liệu {$key} không phải JSON hợp lệ."]);
        }

        $this->merge([$key => $rows]);
    }
}
```

Thêm quan hệ mới → **nhớ thêm tên field vào `JSON_FIELDS`**. Quên bước này thì mảng không bao giờ được decode và `array_key_exists` trong `saveFull` luôn trả `false` — dữ liệu gửi lên bị bỏ qua im lặng.

Hai kiểm tra bắt buộc ở `withValidator()`: **trùng khoá unique trong cùng payload** (trả 422 thay vì `SQLSTATE 23000` giữa transaction), và **trần tổng số tệp** phải thấp hơn `max_file_uploads` của php.ini (§26).

### 13.2. Sub-resource — ba field đính kèm

Viết lại ở **mọi** request có file, không tách trait:

```php
'sync_attachments' => ['sometimes', 'boolean'],   // 'sometimes': form không có phần file
'attachments'      => ['sometimes', 'array', 'max:10'],
'attachments.*'    => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
'kept_media_ids'   => ['sometimes', 'array'],
'kept_media_ids.*' => ['integer'],
```

Dạng C **không có** ba field này — `singleFile()` tự thay thế, chỉ cần `'citizen_front' => ['nullable', 'file', ...]`.

`index/stats/export` dùng `Core\Requests\FilterRequest` — không tự viết lại bộ lọc chuẩn.

---

## 14. Resource

> Mã đầy đủ 14 resource: **VIDU §6.1, §7**.

**`updated_at` giữ đúng format hiển thị của Danatec (`H:i:s d/m/Y`); token khoá lạc quan là field riêng `lock_version` ở ISO8601.** Gộp hai vai trò vào một field là ép chọn giữa "đúng quy ước hiển thị" và "so sánh được đến giây" — không cần phải chọn.

```php
// Bản chính
'updated_at'   => $this->updated_at?->format('H:i:s d/m/Y'),   // hiển thị
'lock_version' => $this->updated_at?->toIso8601String(),       // token, KHÔNG format lại

// Dòng con — trait HasParentLockVersion
'parent_lock_version' => $this->whenLoaded('employee',
    fn () => $this->employee->updated_at?->toIso8601String()),

// Cột Enum xuất kèm nhãn, đúng tiền lệ DependentResource
'level'       => $this->level?->value,
'level_label' => $this->level?->label(),

// Ngày hiển thị d/m/Y; nhưng khi GỬI LÊN thì FormRequest nhận date_format:Y-m-d
'start_date' => $this->start_date?->format('d/m/Y'),
```

`media[].id` chính là giá trị frontend gửi lại qua `kept_media_ids[]`.

`MediaResource` chưa tồn tại trong `Core` — module đầu tiên theo tài liệu này tạo nó, đặt ở `App\Modules\Core\Resources` để module sau dùng lại (VIDU §6.1). Nó phải trả `url = null` cho tệp trên disk private và một `download_url` trỏ route `media.download`: `getUrl()` ném lỗi khi disk không cấu hình `url`.

Dạng C — ô cố định, mỗi ô một key riêng. **Không gộp thành mảng**: gộp chung thì khi người dùng thay một ô, hệ thống không biết thay ô nào.

---

## 15. Controller — mỏng, không chứa logic

> Mã đầy đủ 8 controller: **VIDU §10**.

**Cấm trong controller:** query builder, business logic, `DB::`, vòng lặp xử lý dữ liệu, check quyền sở hữu thủ công.

Chỉ ba việc: nhận request → gọi Service → trả response qua `RespondsWithJson`.

```php
// index → successCollection | show/store/update → successResource | destroy/bulk → success
return $this->successResource(
    new EmployeeEducationResource($this->service->store($employee, $request->validated())),
    'Thêm học vấn thành công'
);

// Quy tắc 4: destroy cùng hình dạng với store/update — parent_lock_version trong 'data'.
// Model::delete() có gọi touchOwners() nên fresh() đã mang giá trị mới.
return $this->success(
    ['parent_lock_version' => $employee->fresh()->updated_at?->toIso8601String()],
    'Xóa học vấn thành công'
);
```

**`bulkDestroy` phải `$employee->touch()` tay** trước khi đọc `parent_lock_version`: nó chạy qua Query Builder nên không kích hoạt `$touches` — cùng một lý do với `whereNotIn` ở §21.4.

Dạng C: `show` trả **200 với `data = null`** khi chưa nhập lần nào, **không** trả 404 — hồ sơ cha vẫn tồn tại, chỉ là phần chi tiết chưa có. 404 sẽ khiến frontend tưởng cả bản ghi cha không tồn tại.

Mọi action phải có PHPDoc Scribe đủ tag: `@group`, `@urlParam`, `@queryParam`, `@bodyParam`, `@header X-Organization-Id` (CLAUDE.md §7).

---

## 16. Routes, permission, LogActivity

> Mã đầy đủ 3 route file + đăng ký `api.php`: **VIDU §11**.

**Thứ tự đăng ký quyết định route nào chạy.** Mọi route tĩnh phải đứng **trước** `/{employee}`:

```php
// ĐÚNG thứ tự này — đặt sau thì POST /api/employees/save-full khớp vào /{employee}
// với {employee}="save-full" → model binding hỏng → 404 không giải thích được (§21.12).
Route::get('/stats', ...);
Route::delete('/bulk-delete', ...);
Route::post('/save-full', [EmployeeController::class, 'saveFull'])
    ->middleware('permission:employees.store,web');   // dùng CHUNG permission bản chính
Route::get('/', ...);

// scopeBindings(): {education} bắt buộc thuộc về {employee} — Laravel tự chặn IDOR,
// không cần check employee_id thủ công trong controller.
Route::scopeBindings()->group(function () {
    Route::get('/{employee}', ...)->whereNumber('employee')
        ->middleware('permission:employees.show,web');
    // ... sub-resource lồng trong prefix('/{employee}/educations')
});
```

`whereNumber()` trên **mọi** tham số ID: nó chặn mọi segment chữ rơi nhầm vào model binding, kể cả khi ai đó thêm route tĩnh mới ở sai chỗ.

`save-full` và `import-template` **dùng chung** permission của bản chính (`.store` / `.update` / `.import`) — không tạo permission riêng.

> PHP không parse `multipart/form-data` trên method `PUT` — `$request->all()` sẽ rỗng. Mọi endpoint có file phải dùng `POST` + `_method=PUT`. Endpoint không có file giữ đúng `PUT`/`PATCH`/`DELETE` theo CLAUDE.md §3.

**Bắt buộc kèm theo** (CLAUDE.md §8):
- `database/seeders/PermissionSeeder.php`: thêm đủ `employees.*`, `employee-educations.*`, `employee-details.*`, `employee-skill-relations.*`, `employee-skills.index` rồi `sail artisan db:seed --class=PermissionSeeder`.
- `Core\Middleware\LogActivity`: cập nhật `resourceLabel()`, `actionLabels`, `pathActions` và route param cho **cả** resource con — thiếu thì nhật ký hiện đường dẫn thô.
- Sau khi xong API: `sail artisan scribe:generate`.

---

## 17. Ba mô hình media — dùng đúng cái

| Mô hình | Đặc điểm | Cơ chế | Ba field? |
|---|---|---|---|
| **Danh sách** | n file, thêm/xoá tuỳ ý | Diff qua 3 field (§10) | Có |
| **Ô đơn** | Đúng 1 file, thay thế | `singleFile()`, `addMedia()` **sau commit** | Không |
| **Ô cố định** | Vài ô có tên riêng | Mỗi ô một collection `singleFile()` | Không |

Ô đơn và ô cố định **không dùng ba field** — `singleFile()` tự xoá file cũ. Nhưng chính vì nó tự xoá nên **bắt buộc gọi ngoài transaction**. Đây là điểm dễ sót nhất trong toàn bộ tài liệu: người viết nghĩ "mình có xoá file đâu" trong khi spatie xoá hộ.

Ô cố định **không phải danh sách n phần tử** mà là các ô có tên. Nhét chung một collection thì khi người dùng thay một ô, hệ thống không biết thay ô nào.

Mọi thao tác media viết thẳng trong service bằng API của spatie (§0) — không đi qua `MediaService`, và cũng không tự bọc lại thành helper riêng của module.

### 17.1. Hệ quả của việc lưu tên gốc — đọc trước khi chọn disk

`addMedia($file)->toMediaCollection($c)` để spatie đặt tên file theo **tên gốc đã sanitize**: `/{media_id}/hop-dong-nguyen-van-a.pdf`. Ưu điểm là cán bộ tải về nhận ra ngay tệp gì, và Resource chỉ cần trả `file_name`.

Cái giá: đường dẫn **mang nghĩa và đoán được**. `media_id` tăng tuần tự, tên tệp theo quy luật đặt tên của nghiệp vụ (`cccd-001234567890.jpg`) → dò được từ ngoài nếu file nằm trên disk `public`.

**Quy tắc chọn disk:**

| Loại tệp | Disk | Truy cập |
|---|---|---|
| Ảnh đại diện, tài liệu không nhạy cảm | `public` | URL trực tiếp |
| CCCD, hợp đồng, bảng lương, hồ sơ định danh | **`private`** | Route có `permission:` + `Storage::disk('private')->download()` |

Với module hồ sơ nhân sự trong ví dụ này, `citizen_front`/`citizen_back` và chứng chỉ **phải** nằm trên disk private. Khai báo ngay ở `registerMediaCollections()`:

```php
$this->addMediaCollection(self::CITIZEN_FRONT_COLLECTION)
    ->singleFile()
    ->useDisk('private');
```

Đặt nhầm disk là lỗi không sửa được bằng một câu `UPDATE`: file đã nằm trên đĩa public và có thể đã bị chỉ mục.

Route `media.download` phải **tự kiểm chủ sở hữu**: bảng `media` không thuộc tenant nên không có global scope bảo vệ, thiếu bước này là mọi tổ chức tải được tệp của nhau chỉ bằng cách đổi id trên URL. Mã: **VIDU §6.2**.

---

## 18. Export / Import khi có quan hệ cha — con

Theo CLAUDE.md §6, dòng con **không** có export/import riêng mà xuất hiện trong file của bản chính:

- Quan hệ **1–1** (`employee_details`): trải phẳng thành cột thường (`CCCD`, `Ngày sinh`, `Giới tính`) — **có** import.
- Quan hệ **1–n / n–n** (`educations`, `skill_relations`): gộp một ô, ngăn bởi `; `. N–n có thuộc tính kèm nhãn trong ngoặc: `PHP (Nâng cao); MySQL (Trung cấp)`.
- Cột liệt kê 1–n/n–n chỉ **tham chiếu để đọc** — **import bỏ qua**, không parse ngược. Đặt tên header khác cột nhập liệu để tránh nhầm (`Học vấn (tham khảo)`).
- File đính kèm **không** xuất ra Excel — xuất tên file thì cán bộ không tải được, xuất URL thì rò link ngoài phiên đăng nhập.

`REQUIRED_KEYS` của Import chỉ gồm cột của **bản chính** + cột 1–1 thực sự bắt buộc; mọi cột khác `nullable` (CLAUDE.md §6).

---

## 19. Event-Driven trong `save-full`

`save-full` là ca đặc biệt: transaction commit xong vẫn còn ba bước ghi/xoá file. Quy tắc:

- Fire event **sau bước xoá file cũ**, tức cuối `saveFull()` — không dùng `ShouldDispatchAfterCommit` ở đây vì "after commit" vẫn sớm hơn thời điểm file yên vị.
- Event chỉ mang `id` + `organization_id`, không mang model (CLAUDE.md EDA §9).
- Service **không** gọi Notification/Mail/Broadcast trực tiếp — mọi side-effect ở Listener.
- Với sub-resource CRUD lẻ (`store/update/destroy`) thì dùng `ShouldDispatchAfterCommit` như bình thường.
- Observer chỉ lo data-integrity; **không** gửi thông báo.

---

## 20. Hợp đồng frontend

> Phần này **không** có bản đầy đủ ở file ví dụ — file ví dụ chỉ có backend. Đây là nguồn duy nhất cho hợp đồng FE.

### 20.0. Vòng đời một lần sửa

```
GET /employees/{id}  ──→  map vào form state  ──→  người dùng thao tác (chỉ đổi state)
                                                          │
                                          build FormData (JSON + file cùng vòng lặp)
                                                          │
                                    POST /employees/{id}/save-full (_method=PUT)
                                                          │
                        ┌─────────────────┬───────────────┴──────────┬──────────────┐
                       200               409                        422            413
                 gán lại state      tải lại trang              giữ nguyên file   báo quá dung lượng
```

Khi nạp, tách bạch ba thứ cho **mỗi dòng con**: `existing[]` (file đã có trên server, để **hiển thị**), `keptMediaIds[]` (để **gửi lên**), `newFiles[]` (File người dùng vừa chọn, để **upload**). Gộp lại là mất khả năng phân biệt "file cũ giữ lại" với "file mới thêm".

Ngày tháng đi hai chiều khác nhau: server trả `d/m/Y` để hiển thị, còn validate nhận `date_format:Y-m-d`. FE phải đổi khi nạp và khi gửi.

Thao tác của người dùng — **toàn bộ chỉ đổi state, không gọi API** cho tới khi bấm Lưu:

| Người dùng làm gì | State đổi thế nào |
|---|---|
| Thêm dòng con | push `{ id: null, ..., existing: [], keptMediaIds: [], newFiles: [] }` |
| Xoá dòng con | `splice` khỏi mảng — server suy ra từ việc id đó vắng mặt |
| Thêm file vào dòng con | `row.newFiles.push(file)` |
| Xoá file cũ của dòng con | bỏ id khỏi `row.keptMediaIds` (và khỏi `existing` để UI cập nhật) |
| Đổi ảnh đại diện / CCCD | `state.avatar = file`. Không có thao tác "xoá ảnh" — ô đơn chỉ thay |
| Không đụng tới một danh sách | Không append `*_json` của danh sách đó lúc build |

### 20.1. Payload `save-full`

```js
const fd = new FormData()

fd.append('_method', 'PUT')
fd.append('lock_version', state.lockVersion)   // KHÔNG phải updated_at hiển thị
fd.append('full_name', form.fullName)
if (form.avatar) fd.append('avatar', form.avatar)

// --- Dạng A/B/D: MỘT chuỗi JSON mỗi mảng -------------------------------
// sync_attachments và kept_media_ids nằm BÊN TRONG JSON của từng dòng.
fd.append('educations_json', JSON.stringify(
  form.educations.map(r => ({
    id: r.id ?? null,
    school_name: r.schoolName,
    start_date: r.startDate,      // Y-m-d khi GỬI, dù hiển thị là d/m/Y
    sync_attachments: r.syncAttachments,
    kept_media_ids: r.keptMediaIds,
  }))
))

// File mới: gửi phẳng, khớp với dòng qua CHỈ SỐ MẢNG (không phải id).
// Build trong CÙNG vòng lặp với JSON ở trên — lọc hoặc sắp xếp lại mảng sau khi
// build sẽ làm file gắn nhầm dòng.
form.educations.forEach((row, i) => {
  (row.newFiles ?? []).forEach(f => fd.append(`educations_files[${i}][]`, f))
})

// --- Dạng C: OBJECT + file phẳng ---------------------------------------
fd.append('detail_json', JSON.stringify({
  citizen_id: form.detail.citizenId,
  birth_date: form.detail.birthDate,
}))
// Gửi file mới = thay ảnh; không gửi = giữ nguyên. KHÔNG có kept_media_ids.
if (form.citizenFront) fd.append('citizen_front', form.citizenFront)
if (form.citizenBack)  fd.append('citizen_back',  form.citizenBack)
```

**Ba trạng thái của mỗi field JSON:**

| Gửi gì | Kết quả |
|---|---|
| `"[...]"` | sync theo danh sách |
| `"[]"` | **xoá hết dòng con** |
| không gửi field | giữ nguyên dữ liệu trong DB |

Dạng C chỉ có **hai** trạng thái — không hỗ trợ `null`.

### 20.2. Payload sub-resource

```js
const fd = new FormData()
fd.append('_method', 'PUT')
fd.append('sync_attachments', '1')
existing.value.forEach(m => fd.append('kept_media_ids[]', m.id))
added.value.forEach(f => fd.append('attachments[]', f))
```

Khi **tạo mới** dòng con thì chỉ cần `attachments[]` — chưa có file cũ nào để giữ hay xoá.

### 20.3. Quy tắc 4 — gán lại state sau MỌI thao tác ghi

Response đi qua wrapper `RespondsWithJson` (`{success, message, data}`):

```js
// Bản chính (update, save-full)
state.lockVersion = res.data.data.lock_version

// Dòng con (store / update / destroy / bulk-delete / index)
state.lockVersion = res.data.data.parent_lock_version

// Sau 200 của save-full: map LẠI toàn bộ danh sách con từ response.
// Bỏ bước này thì dòng vừa tạo vẫn mang id: null trong state, và lần Lưu kế tiếp
// sẽ TẠO THÊM bản ghi trùng thay vì cập nhật. Đồng thời reset newFiles = [].
state.educations = res.data.data.educations.map(mapRow)
```

Bỏ bước gán `lock_version` thì lần ghi kế tiếp nhận 409 dù không ai tranh chấp: `$touches` làm `employees.updated_at` đổi mỗi khi một dòng con thay đổi.

Xử lý phản hồi: **409** → dialog "Dữ liệu đã thay đổi, tải lại trang", **không tự retry** (retry là ghi đè thay đổi của người kia). **422** → map lỗi theo key có chỉ số (`educations.0.school_name`), giữ nguyên `newFiles` và `keptMediaIds`. **413** → "Tổng dung lượng tệp quá lớn", không phải lỗi của trường nào.

### 20.4. Bảng tình huống ba field đính kèm

Server đang có media `41`, `42`:

| Người dùng làm gì | `sync` | `kept[]` | `attachments[]` | Kết quả |
|---|---|---|---|---|
| Sửa trường thường | `1` | `41`, `42` | — | `41`, `42` |
| Thêm 1 file | `1` | `41`, `42` | 1 File | `41`, `42`, `43` |
| Xoá `42` | `1` | `41` | — | `41` |
| Xoá cả hai | `1` | *(vắng)* | — | xoá hết |
| Form không có phần file | *(vắng)* | *(vắng)* | — | `41`, `42` |

Hai dòng cuối phân biệt được **chỉ nhờ cờ**.

### 20.5. UX file

Bấm X trên file cũ chỉ xoá khỏi mảng, **không gọi API**. File chỉ thực sự xoá khi bấm Lưu. Nhận 422 thì giữ nguyên `existing` và `added` — người dùng không phải chọn lại file.

`existing` chỉ để hiển thị, **không bao giờ** append vào `attachments`/`*_files` — làm vậy là nhân đôi tệp trên đĩa.

### 20.6. Tạo mới nhiều bước không dùng `save-full`

Được, miễn **lưu ngay từng bước**:

```
1. POST /api/employees                          → nhận {id}
2. POST /api/employees/{id}/educations          → lưu ngay khi bấm Lưu ở tab đó
3. POST /api/employees/{id}/detail              → upsert nên tạo mới cũng chạy
```

**Cấm** gom N request bắn một lượt lúc bấm Lưu cuối: không có transaction xuyên request, hỏng giữa chừng để lại dữ liệu nửa vời không dấu vết, retry tạo bản ghi trùng. Cần một request duy nhất thì dùng `save-full`.

---

## 21. Bẫy đã gặp — đọc trước khi viết

### 21.1. FormData không sinh key khi mảng rỗng
**Triệu chứng:** người dùng xoá hết dòng con, bấm Lưu, nhận "Lưu thành công", tải lại thì dữ liệu cũ quay về.
**Nguyên nhân:** `array_key_exists('educations', $data)` trả `false` vì FormData không gửi key nào cho mảng rỗng. "Đã xoá hết" và "form không quản lý danh sách này" trông giống hệt nhau trên đường truyền.
**Cách đúng:** gửi JSON (§13.1). `"[]"` phân biệt được với vắng mặt.

### 21.2. `assertNotStale` chạy ngoài transaction
**Triệu chứng:** thỉnh thoảng mất thay đổi của người khác, không tái hiện được.
**Nguyên nhân:** kiểm tra trên instance từ route model binding — dữ liệu tại thời điểm dispatch. Hai request song song cùng đọc `updated_at = T`, cả hai pass.
**Cách đúng:** đọc lại kèm `lockForUpdate()` **bên trong** transaction (§7).

### 21.3. `singleFile()` xoá file cũ ngay, không rollback
**Triệu chứng:** transaction rollback nhưng ảnh cũ đã mất khỏi đĩa.
**Nguyên nhân:** spatie xoá file cũ ngay khi file mới lưu xong. Người viết nghĩ "mình có xoá file đâu".
**Cách đúng:** mọi `addMedia()` vào collection `singleFile()` chạy **sau commit** (§7, §11).

### 21.4. `whereNotIn(...)->delete()` không kích hoạt `$touches`
**Triệu chứng:** người khác ghi đè lên kết quả xoá mà không nhận 409.
**Nguyên nhân:** chạy qua Query Builder, bỏ qua model event. Request chỉ xoá dòng con thì không model nào được save, `updated_at` đứng yên.
**Cách đúng:** `$parent->touch()` tường minh cuối transaction (§8). Áp cho **cả** `bulkDestroy` của sub-resource (§15).

### 21.5. `UNIQUE` + `SoftDeletes` đụng nhau
**Triệu chứng:** xoá một dòng rồi thêm lại → `SQLSTATE 23000`, lỗi 500 không giải thích được.
**Nguyên nhân:** dòng đã xoá mềm vẫn chiếm chỗ trong unique index. Đưa `deleted_at` vào unique **không cứu được** — MySQL coi mọi `NULL` là khác nhau.
**Cách đúng:** `withTrashed()` → `restore()` thay vì `create()` (§9.3, §11). Áp cho **mọi** bảng có unique constraint kèm SoftDeletes — và **chỉ** những bảng đó.

Bảng không có unique constraint (dạng A/B) **không** thêm nhánh này: `create()` chạy bình thường, còn `restore()` thừa sẽ âm thầm hồi sinh dòng người dùng đã cố ý xoá. Đặt `withTrashed()` đúng chỗ khoá unique nằm — trong nhánh tra theo cột unique, không phải ở `pluck('id')`.

### 21.6. `sync()` của Laravel xoá cứng
**Triệu chứng:** bỏ nhầm một mục rồi thêm lại thì lịch sử, thuộc tính, file đính kèm mất sạch.
**Nguyên nhân:** `sync()` xoá cứng dòng pivot, không có SoftDeletes, không kích hoạt event nên `$touches` không nổ.
**Cách đúng:** module mới không dùng `sync()`. Bảng nối là bảng con 1–n thật sự (§5, §9.3).

### 21.7. Biến gán trong closure không thoát ra ngoài
**Triệu chứng:** ảnh im lặng không bao giờ được ghi, không có lỗi nào.
**Nguyên nhân:** `$detail` gán bên trong closure của `DB::transaction` mà không capture by reference.
**Cách đúng:** `use (..., &$detail)` và khởi tạo `$detail = null` trước (§8).

### 21.8. So `lock_version` đến micro-giây
**Triệu chứng:** mọi request update trả 409, không cách nào lưu được.
**Nguyên nhân:** `Carbon::ne()` so đến micro-giây, `toIso8601String()` chỉ xuất đến giây. Xảy ra ngay khi ai đó đổi cột sang `timestamp(6)`.
**Cách đúng:** so bằng `->timestamp` (§7).

### 21.9. Snapshot media chụp sau khi upload
**Triệu chứng:** file vừa upload biến mất ngay sau khi lưu.
**Nguyên nhân:** file mới nằm trong `$existing`, mà nó không có trong `kept_media_ids` → rơi vào `$trash`.
**Cách đúng:** `getMedia()` **trước** mọi `addMedia()` (§10).

### 21.10. Cha xoá cứng, con cascade
**Triệu chứng:** xoá bản ghi cha thì toàn bộ dòng con biến mất khỏi DB dù có SoftDeletes, file media mồ côi trên đĩa.
**Nguyên nhân:** `onDelete('cascade')` là ràng buộc ở tầng MySQL, không biết gì về SoftDeletes hay model event.
**Cách đúng:** bảng cha **bắt buộc** có `SoftDeletes` (§4).

### 21.11. Vượt `post_max_size` → request rỗng, báo lỗi lạc đề
**Triệu chứng:** upload nhiều file thì nhận 422 "Thiếu phiên bản bản ghi", trong khi FE có gửi `lock_version`.
**Nguyên nhân:** payload vượt `post_max_size` → PHP bỏ trắng `$_POST` và `$_FILES`, không ném lỗi. Laravel thấy request rỗng nên báo thiếu field bắt buộc.
**Cách đúng:** middleware kiểm `CONTENT_LENGTH` > `post_max_size` → trả 413 với thông báo đúng bản chất; đồng thời giới hạn tổng số tệp ở `withValidator` (§13.1) và nới `php.ini` (§26).

### 21.12. Route tĩnh đứng sau route `{id}`
**Triệu chứng:** `POST /api/employees/save-full` trả 404, trong khi controller có method đó.
**Nguyên nhân:** Laravel khớp route theo thứ tự đăng ký; `/{employee}` đứng trước nên nuốt luôn segment `save-full`, model binding hỏng → 404.
**Cách đúng:** route tĩnh đứng trước, và `{employee}` có `->whereNumber()` (§16).

---

## 22. Checklist thêm quan hệ mới

1. Xác định dạng theo bảng §2.
2. Migration — `organization_id`, `created_by/updated_by`, `SoftDeletes`, index `(organization_id, parent_id)`.
3. **Bảng cha có `SoftDeletes` chưa?** (§21.10)
4. **Có unique constraint không?** Nếu có thì service cần nhánh restore (§21.5).
5. Model — `extends TenantModel`, `$touches = ['parent']`, `parent_id`/`organization_id` **không** trong `$fillable`, `booted()` gán `created_by/updated_by`, `implements HasMedia` nếu có file.
6. Enum cho mọi cột giá trị giới hạn + gộp vào endpoint `{module}-enums` (§6).
7. Service dòng con — copy VIDU §9.3, đổi `FILLABLE` và tên quan hệ; mọi `addMedia()` chạy **sau** commit, snapshot chụp **trước** khi upload.
8. Collection chứa tệp nhạy cảm khai báo `->useDisk('private')` ngay từ đầu (§17.1) — đặt nhầm không sửa lại được bằng `UPDATE`.
9. Thêm nhánh sync tương ứng vào `ParentService::saveFull()` và **thêm tên field vào `JSON_FIELDS`** (§13.1).
10. FormRequest — ba field đính kèm (§13.2) + `messages()` + `attributes()` + `bodyParameters()`; `index/stats/export` dùng `FilterRequest`.
11. Khoá ngoại trỏ danh mục: `Rule::exists` có scope tenant + `whereNull('deleted_at')` (§12).
12. Resource — `parent_lock_version`, ngày `d/m/Y`, giờ `H:i:s d/m/Y`, Enum kèm `*_label` (§14).
13. Controller — `RespondsWithJson`, PHPDoc Scribe đủ tag, `bulkDestroy` nhớ `touch()` (§15).
14. Route — trong `Route::scopeBindings()`, route tĩnh trước `{id}`, `whereNumber`, `permission:` cho **mọi** action.
15. `PermissionSeeder` + `LogActivity` + `sail artisan scribe:generate`.
16. Eager load trong `index()`: quan hệ cha (nếu không `parent_lock_version` biến mất) và `creator.media`/`editor.media` (nếu không `FormatsUserSummary` sinh N+1) — §10.
17. Factory đúng namespace `Database\Factories\Modules\{Module}\Models\` (Scribe cần).
18. Export/Import: cột liệt kê dòng con ngăn bởi `; `, import bỏ qua (§18).
19. Copy test VIDU §13, đổi tên model. Chạy `sail artisan test --filter=Employee`.
20. Cập nhật `docs/database/{Module}.md`, `docs/modules/{Module}/README.md`, và `docs/changelogs/YYYY-MM-DD-*-fe.md` nếu đổi API ảnh hưởng FE.

---

## 23. Cấm

| Anti-pattern | Hậu quả |
|---|---|
| `clearMediaCollection()` rồi add lại | Upload lỗi giữa chừng → mất sạch file |
| Snapshot media **sau** khi upload | File vừa upload bị xoá ngay |
| Xoá file **trong** transaction | Rollback → mất file vĩnh viễn |
| `singleFile()` ghi **trong** transaction | Spatie tự xoá file cũ, rollback không cứu được |
| Bọc `getMedia()` / `addMedia()` / `each->delete()` thành helper riêng | Wrapper rỗng, không ép được thứ tự, bắt người đọc mở thêm file |
| Tệp định danh/hợp đồng để trên disk `public` | Tên file mang nghĩa + id tuần tự → dò được từ ngoài (§17.1) |
| `addMedia()` không kiểm `isValid()` | File hỏng giữa đường truyền ném lỗi giữa luồng đã commit |
| `reject` trên media toàn cục thay vì snapshot của record | Xoá được file của bản ghi khác |
| Gọi `save-full` từ màn hình có phân trang | Xoá sạch phần chưa load, response vẫn 200 |
| Gửi mảng dòng con qua FormData lồng | `max_input_vars` cắt âm thầm → xoá nhầm file |
| `isset()` / `!empty()` thay `array_key_exists()` | Mảng rỗng không xoá được |
| Quên thêm field vào `JSON_FIELDS` | Mảng không được decode, dữ liệu bị bỏ qua im lặng |
| `assertNotStale` **ngoài** transaction | Hai request song song cùng pass |
| So `lock_version` bằng `Carbon::ne()` | Đổi sang `timestamp(6)` là 409 vĩnh viễn |
| Format `lock_version` thành `d/m/Y` | Mất giây → optimistic lock sai vĩnh viễn |
| Quên `$parent->touch()` sau `whereNotIn` hoặc `bulkDestroy` | Optimistic lock mù khi chỉ xoá dòng con |
| Giữ `lockForUpdate` suốt thời gian ghi file | Request thứ hai chờ tới timeout rồi 500 |
| Bỏ `$touches = ['parent']` | Mất cơ chế duy nhất bắt race giữa hai màn hình |
| `sync()` cho quan hệ n–n (module mới) | Xoá cứng dữ liệu nghiệp vụ, không nổ `$touches` |
| `create()` trên bảng có unique + SoftDeletes | `SQLSTATE 23000` sau lần xoá đầu tiên |
| Thêm nhánh restore cho bảng **không** có unique | Âm thầm hồi sinh dòng người dùng đã cố ý xoá |
| Gán biến trong closure không `&` | Ảnh im lặng không được ghi |
| `PUT` + multipart | PHP không parse, request rỗng |
| Route tĩnh đặt sau `/{id}` | `save-full` trả 404 |
| `exists:bảng,id` không scope tenant | Gán được danh mục riêng của tổ chức khác |
| Áp `kept_media_ids` cho mô hình ô đơn | Sai mô hình, thừa phức tạp |
| `save-full` tự ghi bản chính | Hai chỗ ghi lệch nhau, một chỗ quên optimistic lock |
| `parent_id` / `organization_id` trong `$fillable` | Mất cơ chế chặn IDOR và cross-tenant |
| `response()->json()` thay `RespondsWithJson` | Format response lệch cả hệ thống |
| Cho người nhập liệu tạo mục danh mục mới | Rác danh mục, không gộp lại được |
| Cha xoá cứng khi con có `SoftDeletes` | Cascade xoá cứng con, file mồ côi |
| Gom N request lúc bấm Lưu | Dữ liệu nửa vời không dấu vết |
| Frontend quên gán lại `lock_version` | Lần lưu thứ hai dính 409 |
| Frontend không map lại danh sách con sau 200 | Dòng vừa tạo vẫn `id: null` → lần Lưu sau tạo bản ghi trùng |
| Eager load `'creator'` thay vì `'creator.media'` | `FormatsUserSummary` gọi `getFirstMedia()` → N+1 theo số người tạo |
| Copy service sang module mới nhưng bỏ test | Lỗi mất file không ai phát hiện |

---

## 24. Test bắt buộc

> Mã đầy đủ 4 lớp test: **VIDU §13**.

Mọi service có file — bốn ca, không phải gợi ý:

```php
public function test_khong_gui_co_thi_giu_nguyen_file(): void        // không cờ → file cũ nguyên vẹn
public function test_xoa_mot_file(): void                            // cờ + giữ 1 → file còn lại bị xoá
public function test_gui_co_khong_gui_kept_thi_xoa_het(): void       // cờ, không kept → xoá hết
public function test_file_moi_khong_bi_xoa_nham(): void              // file MỚI không bị xoá nhầm
```

Test thứ tư quan trọng nhất: nó bắt đúng lỗi dễ mắc nhất khi copy code sang module mới, và là lỗi không khôi phục được.

Mọi `save-full`:

```php
public function test_json_rong_thi_xoa_het_dong_con(): void
public function test_khong_gui_json_thi_giu_nguyen_dong_con(): void
public function test_lock_version_cu_thi_409_va_khong_ghi_gi(): void
```

Bảng có unique + SoftDeletes:

```php
public function test_xoa_roi_them_lai_thi_restore(): void
```

Multi-tenant — bắt buộc cho mọi resource con:

```php
public function test_khong_truy_cap_duoc_du_lieu_to_chuc_khac(): void
public function test_khong_gan_duoc_danh_muc_to_chuc_khac(): void
```

Khi sửa lỗi trong một service, rà toàn bộ service cùng pattern:

```bash
grep -rln "sync_attachments" app/Modules/
sail artisan test --filter=Employee
```

---

## 25. Chưa phủ — dừng lại và hỏi

Hai dạng quan hệ tài liệu này **không** áp dụng được, đừng suy diễn từ khuôn mẫu gần nhất:

- **Tự tham chiếu** (cơ cấu tổ chức, danh mục cây) — dùng `kalnoy/nestedset`, có logic riêng. `whereNotIn` không áp được vì xoá cha để lại con mồ côi.
- **Đa hình** (một bảng đính kèm dùng chung cho nhiều loại bản ghi) — `attachable_type/attachable_id`, `scopeBindings` không ràng buộc được.

**Ngưỡng quy mô:** `saveFull` phình tuyến tính, mỗi quan hệ thêm ~40 dòng service và ~15 dòng rule. Quá **6 quan hệ** thì tách `save-full` thành nhiều endpoint gộp theo tab thay vì một cái ôm hết.

---

## 26. Cấu hình PHP

```ini
max_file_uploads    = 100     ; mặc định 20 — PHP bỏ lặng phần dư, không báo lỗi
post_max_size       = 128M
upload_max_filesize = 20M
```

`max_input_vars` không cần nâng **nếu** đã theo §13.1 — mỗi mảng dòng con chỉ chiếm 1 input var. Còn gửi mảng lồng qua FormData thì bắt buộc nâng lên ≥ 5000, và vẫn không an toàn.

Trần tổng tệp trong `withValidator` (§13.1) phải **thấp hơn** `max_file_uploads` — bằng nhau thì phần dư bị PHP cắt trước khi validate nhìn thấy.

---

## Phụ lục A — quyết định về `MediaService` và phần cần chỉnh trong CLAUDE.md

**Quyết định:** module mới theo tài liệu này gọi spatie trực tiếp, **không** đi qua `Core\Services\MediaService`. Không thêm, không sửa gì trong `Core`.

**Vì sao.** Các bản nháp trước lần lượt đề xuất thêm bốn method (`snapshot`, `trashDiff`, `deleteMedia`, `replaceSingle`) rồi rút còn một, rồi rút nốt. Cả bốn đều không mua được thứ đắt nhất: **thứ tự** snapshot → commit → ghi → xoá nằm ở service gọi, không lớp bọc nào ép được. Bọc thêm chỉ tạo cảm giác an toàn giả, và trái nguyên tắc "đọc một file service là hiểu trọn luồng".

**Cái giá, ghi ra để không ai ngạc nhiên về sau:**

| Mất gì so với `MediaService::uploadOne()` | Xử lý |
|---|---|
| `usingFileName($file->hashName())` — tên file trên đĩa không đoán được | Đổi bằng disk `private` cho tệp nhạy cảm (§17.1). Đây là biện pháp **mạnh hơn** hash tên, không phải giải pháp thay thế tạm |
| `custom_properties.original_name` | Không cần: `file_name` chính là tên gốc đã sanitize |
| Giá trị trả `[disk, path]` để `cleanupStoredFiles()` | Chấp nhận: upload lỗi giữa chừng để lại tệp mồ côi. Dọn bằng lệnh định kỳ, không dọn trong request |
| Một chỗ duy nhất để chèn xử lý về sau (strip EXIF, quét virus, quota) | Khi thật sự cần thì thêm vào `MediaService` và sửa **cả** module cũ lẫn mới cùng lúc — không dựng sẵn lớp bọc trống để chờ |

**Cần sửa CLAUDE.md cho khỏi mâu thuẫn** — hiện §4 và checklist §11 đang cấm gọi `addMedia()` trực tiếp, tài liệu này thì bảo gọi. Hai nguồn chỉ dẫn đá nhau thì Claude Code chọn theo file nào được nạp trước. Đề xuất câu chữ:

> **CLAUDE.md §4, dòng media** — thay:
> *"Mọi upload/xóa media đi qua `App\Modules\Core\Services\MediaService` — không gọi `addMedia()` hay `Storage::put/delete` trực tiếp."*
> bằng:
> *"Module hiện có: mọi upload/xóa media đi qua `Core\Services\MediaService`. Module mới có quan hệ cha–con: gọi spatie trực tiếp trong Service theo `docs/system/QUAN_HE_CHA_CON.md` §0 — tệp nhạy cảm bắt buộc `->useDisk('private')`."*
>
> **CLAUDE.md §11, dòng checklist** — thay:
> *"Upload media đi qua `Core\Services\MediaService`."*
> bằng:
> *"Upload media: module cũ qua `MediaService`; module mới theo `QUAN_HE_CHA_CON.md` — `addMedia()` sau commit, snapshot trước upload, tệp nhạy cảm trên disk private."*

Chưa sửa CLAUDE.md thì tài liệu này vẫn dùng được, nhưng mọi PR sẽ bị bật đúng dòng media.

---

## Liên quan

- [QUAN_HE_CHA_CON_VIDU.md](QUAN_HE_CHA_CON_VIDU.md) — **mã tham chiếu đầy đủ**, 44 tập tin của module mẫu `Employee`
- [CLAUDE.md](../../CLAUDE.md) — quy ước chung Danatec (bắt buộc đọc trước)
- [AUTH_TENANT.md](AUTH_TENANT.md) — multi-tenant, permission model
- [ARCHITECTURE.md](ARCHITECTURE.md) — cấu trúc modular tổng thể
- [../database/ERD.md](../database/ERD.md) — quy tắc đặt tên bảng
