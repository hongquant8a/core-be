# Ví dụ tham khảo — module `Employee` (đầy đủ mã nguồn)

> Ngày tạo: 08:08:19 15/08/2026  
> Cập nhật lần cuối: 08:41:07 15/08/2026

Bản triển khai đầy đủ đi kèm [QUAN_HE_CHA_CON.md](QUAN_HE_CHA_CON.md). Mọi tập tin viết trọn vẹn, không rút gọn, copy được thẳng vào `app/Modules/`.

---

## 0. Phạm vi bản ví dụ

Module giả định `Employee` (hồ sơ nhân sự), phủ đủ **năm dạng quan hệ** của §2 tài liệu quy tắc:

| Dạng | Bảng | Ghi chú |
|---|---|---|
| A. 1–n có file | `employee_educations` | Học vấn + file bằng cấp |
| A. 1–n có file | `employee_work_experiences` | Quá trình công tác + file quyết định/hợp đồng |
| B. 1–n không file | `employee_family_relationships` | Quan hệ gia đình |
| C. 1–1 | `employee_details` | Thông tin định danh + 2 ô ảnh CCCD |
| D. n–n có thuộc tính | `employee_skill_relations` | Kỹ năng của nhân sự + file chứng chỉ |
| E. Danh mục dùng chung | `employee_skills` | Chỉ đọc trong module này |

`employee_work_experiences` là **dạng A thứ hai**, cố ý để nguyên trùng lặp với `employee_educations`. Đọc hai bộ cạnh nhau sẽ thấy rõ phần nào là khuôn mẫu bất biến (snapshot → commit → upload → xoá, `$touches`, `whereNotIn`, `parent_lock_version`) và phần nào thay đổi theo nghiệp vụ (`FILLABLE`, tên collection, rule validate). Đây là điều một khuôn mẫu trừu tượng hoá không dạy được.

**Chốt cách lưu tệp:** theo **cách A** của §17.1 — spatie giữ tên gốc đã sanitize trên đĩa. Collection chứa tệp nhạy cảm (`employee_details`, chứng chỉ) khai báo `->useDisk('private')`, và tải về đi qua route `media.download` (có ở §14 bên dưới).

### Cây thư mục

```
app/Modules/Core/
├── Exceptions/StaleRecordException.php
├── Controllers/MediaDownloadController.php
└── Resources/MediaResource.php

app/Modules/Employee/
├── Controllers/
│   ├── EmployeeController.php
│   ├── EmployeeDetailController.php
│   ├── EmployeeEducationController.php
│   ├── EmployeeFamilyRelationshipController.php
│   ├── EmployeeSkillController.php
│   ├── EmployeeSkillRelationController.php
│   ├── EmployeeWorkExperienceController.php
│   └── EnumController.php
├── Enums/
│   ├── EmployeeStatusEnum.php
│   ├── FamilyRelationshipEnum.php
│   ├── GenderEnum.php
│   ├── MaritalStatusEnum.php
│   └── SkillLevelEnum.php
├── Models/
│   ├── Employee.php
│   ├── EmployeeDetail.php
│   ├── EmployeeEducation.php
│   ├── EmployeeFamilyRelationship.php
│   ├── EmployeeSkill.php
│   ├── EmployeeSkillRelation.php
│   └── EmployeeWorkExperience.php
├── Requests/
│   ├── BulkDestroyEmployeeEducationRequest.php
│   ├── BulkDestroyEmployeeRequest.php
│   ├── BulkDestroyEmployeeSkillRelationRequest.php
│   ├── BulkDestroyEmployeeWorkExperienceRequest.php
│   ├── BulkUpdateEmployeeStatusRequest.php
│   ├── ChangeEmployeeStatusRequest.php
│   ├── SaveEmployeeDetailRequest.php
│   ├── SaveEmployeeEducationRequest.php
│   ├── SaveEmployeeFamilyRelationshipRequest.php
│   ├── SaveEmployeeRequest.php
│   ├── SaveEmployeeSkillRelationRequest.php
│   ├── SaveEmployeeWorkExperienceRequest.php
│   └── SaveFullEmployeeRequest.php
├── Resources/
│   ├── Concerns/HasParentLockVersion.php
│   ├── EmployeeCollection.php
│   ├── EmployeeDetailResource.php
│   ├── EmployeeEducationCollection.php
│   ├── EmployeeEducationResource.php
│   ├── EmployeeFamilyRelationshipCollection.php
│   ├── EmployeeFamilyRelationshipResource.php
│   ├── EmployeeResource.php
│   ├── EmployeeSkillCollection.php
│   ├── EmployeeSkillRelationCollection.php
│   ├── EmployeeSkillRelationResource.php
│   ├── EmployeeSkillResource.php
│   ├── EmployeeWorkExperienceCollection.php
│   └── EmployeeWorkExperienceResource.php
├── Routes/
│   ├── employee.php
│   ├── employee_skill.php
│   └── enum.php
└── Services/
    ├── EmployeeDetailService.php
    ├── EmployeeEducationService.php
    ├── EmployeeFamilyRelationshipService.php
    ├── EmployeeService.php
    ├── EmployeeSkillRelationService.php
    ├── EmployeeSkillService.php
    └── EmployeeWorkExperienceService.php

database/migrations/          7 tập tin (§1)
database/factories/Modules/Employee/Models/   7 tập tin (§4)
```

---

## 1. Migrations

### 1.1. `2026_08_15_000001_create_employees_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('position')->nullable();
            $table->date('hired_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // BẮT BUỘC: bảng con dùng onDelete('cascade'). Không có softDeletes ở đây
            // thì cha xoá cứng sẽ khiến MySQL xoá cứng toàn bộ dòng con — bỏ qua
            // SoftDeletes của chúng và để lại file media mồ côi trên đĩa.
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
```

### 1.2. `2026_08_15_000002_create_employee_educations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('school_name');
            $table->string('degree')->nullable();
            $table->string('major')->nullable();
            $table->string('grade', 100)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Mọi query đều đi qua cả hai: global scope của TenantModel thêm
            // organization_id, quan hệ thêm employee_id.
            $table->index(['organization_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_educations');
    }
};
```

### 1.3. `2026_08_15_000003_create_employee_family_relationships_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_family_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('full_name');
            $table->string('relationship', 50);
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('occupation')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_family_relationships');
    }
};
```

### 1.4. `2026_08_15_000004_create_employee_details_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // UNIQUE(employee_id) là thứ khiến POST lần thứ hai luôn hỏng — đó là lý
            // do dạng C chỉ có GET + PUT. Nó cũng tạo bẫy §21.5 khi kèm SoftDeletes:
            // dòng đã xoá mềm vẫn chiếm chỗ, nên service phải restore chứ không create.
            $table->foreignId('employee_id')->unique()->constrained('employees')->onDelete('cascade');
            $table->string('citizen_id', 20)->nullable();
            $table->date('citizen_issued_date')->nullable();
            $table->string('citizen_issued_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('hometown')->nullable();
            $table->string('ethnicity', 50)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('social_insurance_no', 30)->nullable();
            $table->string('tax_code', 30)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_details');
    }
};
```

### 1.5. `2026_08_15_000005_create_employee_skills_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_skills', function (Blueprint $table) {
            $table->id();
            // NULL = danh mục dùng chung toàn hệ thống; có giá trị = riêng tổ chức.
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('group', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_skills');
    }
};
```

### 1.6. `2026_08_15_000006_create_employee_skill_relations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_skill_relations', function (Blueprint $table) {
            $table->id();                                   // ← có khoá chính riêng
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            // restrict: xoá một kỹ năng đang được dùng phải bị chặn, không cascade.
            $table->foreignId('employee_skill_id')->constrained('employee_skills')->onDelete('restrict');
            $table->string('level', 30)->nullable();        // ← thuộc tính nghiệp vụ
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->date('certified_at')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();                          // ← không xoá cứng

            $table->unique(['employee_id', 'employee_skill_id']);
            $table->index(['organization_id', 'employee_id']);
            $table->index(['employee_skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_skill_relations');
    }
};
```

### 1.7. `2026_08_15_000007_create_employee_work_experiences_table.php`

Dạng A thứ hai. So với `employee_educations` chỉ khác tập cột nghiệp vụ — mọi thứ còn lại (tenant, `created_by/updated_by`, `softDeletes`, index cặp) giữ nguyên từng dòng.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('company_name');
            $table->string('position');
            $table->string('department')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            // decimal chứ không float: lương là tiền, float làm tròn sai ở hàng đơn vị.
            $table->decimal('salary', 15, 2)->nullable();
            $table->string('leaving_reason')->nullable();
            $table->text('job_description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_experiences');
    }
};
```

---

## 2. Enums

### 2.1. `Enums/EmployeeStatusEnum.php`

```php
<?php

namespace App\Modules\Employee\Enums;

enum EmployeeStatusEnum: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Resigned = 'resigned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang làm việc',
            self::OnLeave => 'Tạm nghỉ',
            self::Resigned => 'Đã nghỉ việc',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
```

### 2.2. `Enums/GenderEnum.php`

```php
<?php

namespace App\Modules\Employee\Enums;

enum GenderEnum: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Nam',
            self::Female => 'Nữ',
            self::Other => 'Khác',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
```

### 2.3. `Enums/MaritalStatusEnum.php`

```php
<?php

namespace App\Modules\Employee\Enums;

enum MaritalStatusEnum: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Độc thân',
            self::Married => 'Đã kết hôn',
            self::Divorced => 'Ly hôn',
            self::Widowed => 'Goá',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
```

### 2.4. `Enums/FamilyRelationshipEnum.php`

```php
<?php

namespace App\Modules\Employee\Enums;

enum FamilyRelationshipEnum: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Spouse = 'spouse';
    case Child = 'child';
    case Sibling = 'sibling';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Cha',
            self::Mother => 'Mẹ',
            self::Spouse => 'Vợ/Chồng',
            self::Child => 'Con',
            self::Sibling => 'Anh/Chị/Em',
            self::Other => 'Khác',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
```

### 2.5. `Enums/SkillLevelEnum.php`

```php
<?php

namespace App\Modules\Employee\Enums;

enum SkillLevelEnum: string
{
    case Basic = 'basic';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case Expert = 'expert';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Cơ bản',
            self::Intermediate => 'Trung cấp',
            self::Advanced => 'Nâng cao',
            self::Expert => 'Chuyên gia',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
```

---

## 3. Models

### 3.1. `Models/Employee.php`

```php
<?php

namespace App\Modules\Employee\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Employee\Enums\EmployeeStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Employee extends TenantModel implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const AVATAR_COLLECTION = 'employee_avatar';

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Employee\Models\EmployeeFactory::new();
    }

    protected $table = 'employees';

    protected $fillable = [
        'code', 'full_name', 'email', 'phone', 'position', 'hired_at', 'status',
        'organization_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'hired_at' => 'date',
        'status' => EmployeeStatusEnum::class,
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class);
    }

    public function workExperiences(): HasMany
    {
        return $this->hasMany(EmployeeWorkExperience::class);
    }

    public function familyRelationships(): HasMany
    {
        return $this->hasMany(EmployeeFamilyRelationship::class);
    }

    public function employeeDetail(): HasOne
    {
        return $this->hasOne(EmployeeDetail::class);
    }

    public function skillRelations(): HasMany
    {
        return $this->hasMany(EmployeeSkillRelation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function registerMediaCollections(): void
    {
        // Ảnh đại diện không nhạy cảm → disk public, hiển thị bằng URL trực tiếp.
        $this->addMediaCollection(self::AVATAR_COLLECTION)
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections(self::AVATAR_COLLECTION)
            ->width(150)
            ->height(150)
            ->nonQueued();
    }
}
```

### 3.2. `Models/EmployeeEducation.php` — dạng A

```php
<?php

namespace App\Modules\Employee\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EmployeeEducation extends TenantModel implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const MEDIA_COLLECTION = 'employee_education_certificates';

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Employee\Models\EmployeeEducationFactory::new();
    }

    protected $table = 'employee_educations';

    /**
     * Bump employees.updated_at mỗi khi dòng con đổi. Đây là cơ chế DUY NHẤT bắt
     * được xung đột giữa màn hình sub-resource và màn hình save-full. KHÔNG ĐƯỢC BỎ.
     */
    protected $touches = ['employee'];

    /**
     * employee_id KHÔNG nằm trong fillable — luôn gán qua quan hệ
     * ($employee->educations()->create(...)), đó cũng là cơ chế chặn IDOR.
     * organization_id cũng không: TenantModel tự gán khi creating.
     */
    protected $fillable = [
        'school_name', 'degree', 'major', 'grade', 'start_date', 'end_date',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function registerMediaCollections(): void
    {
        // KHÔNG singleFile() — một bằng cấp có thể nhiều trang scan.
        // Disk private: bằng cấp là tài liệu định danh, tải về qua route media.download.
        $this->addMediaCollection(self::MEDIA_COLLECTION)
            ->useDisk('private')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }
}
```

### 3.2b. `Models/EmployeeWorkExperience.php` — dạng A thứ hai

So với `EmployeeEducation` chỉ khác `MEDIA_COLLECTION`, `$table`, `$fillable` và `$casts`. `$touches`, SoftDeletes, `booted()`, quan hệ và `useDisk('private')` giữ nguyên từng dòng — đó là phần khuôn mẫu.

```php
<?php

namespace App\Modules\Employee\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EmployeeWorkExperience extends TenantModel implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const MEDIA_COLLECTION = 'employee_work_documents';

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Employee\Models\EmployeeWorkExperienceFactory::new();
    }

    protected $table = 'employee_work_experiences';

    protected $touches = ['employee'];

    protected $fillable = [
        'company_name', 'position', 'department', 'start_date', 'end_date',
        'salary', 'leaving_reason', 'job_description', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        // decimal:2 để giá trị trả về là chuỗi "15000000.00", không phải float sai số.
        'salary' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function registerMediaCollections(): void
    {
        // Quyết định tuyển dụng, hợp đồng lao động — tài liệu nhân sự, disk private.
        $this->addMediaCollection(self::MEDIA_COLLECTION)
            ->useDisk('private')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }
}
```

---

### 3.3. `Models/EmployeeFamilyRelationship.php` — dạng B

```php
<?php

namespace App\Modules\Employee\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Employee\Enums\FamilyRelationshipEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dạng B — 1–n không file. Giống hệt dạng A trừ phần media:
 * không implements HasMedia, không InteractsWithMedia, không registerMediaCollections().
 */
class EmployeeFamilyRelationship extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Employee\Models\EmployeeFamilyRelationshipFactory::new();
    }

    protected $table = 'employee_family_relationships';

    protected $touches = ['employee'];

    protected $fillable = [
        'full_name', 'relationship', 'birth_year', 'occupation', 'phone', 'address', 'note',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'relationship' => FamilyRelationshipEnum::class,
        'birth_year' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

### 3.4. `Models/EmployeeDetail.php` — dạng C

```php
<?php

namespace App\Modules\Employee\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Employee\Enums\GenderEnum;
use App\Modules\Employee\Enums\MaritalStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EmployeeDetail extends TenantModel implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const CITIZEN_FRONT_COLLECTION = 'employee_citizen_front';
    public const CITIZEN_BACK_COLLECTION = 'employee_citizen_back';

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Employee\Models\EmployeeDetailFactory::new();
    }

    protected $table = 'employee_details';

    protected $touches = ['employee'];

    protected $fillable = [
        'citizen_id', 'citizen_issued_date', 'citizen_issued_place', 'birth_date',
        'gender', 'hometown', 'ethnicity', 'religion', 'social_insurance_no',
        'tax_code', 'marital_status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'citizen_issued_date' => 'date',
        'birth_date' => 'date',
        'gender' => GenderEnum::class,
        'marital_status' => MaritalStatusEnum::class,
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function registerMediaCollections(): void
    {
        // Ô cố định: hai ô có tên riêng, mỗi ô một collection singleFile().
        //
        // singleFile() khiến spatie tự xoá file cũ NGAY khi file mới lưu xong — hành vi
        // nằm ngoài transaction của DB. Mọi addMedia vào hai collection này BẮT BUỘC
        // chạy sau commit (xem EmployeeDetailService::syncCitizenPhotos).
        //
        // Ảnh CCCD là tài liệu định danh → disk private, không bao giờ để public.
        $this->addMediaCollection(self::CITIZEN_FRONT_COLLECTION)
            ->singleFile()
            ->useDisk('private')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection(self::CITIZEN_BACK_COLLECTION)
            ->singleFile()
            ->useDisk('private')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }
}
```

### 3.5. `Models/EmployeeSkill.php` — dạng E

```php
<?php

namespace App\Modules\Employee\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Danh mục dùng chung — organization_id = NULL nghĩa là dùng chung toàn hệ thống.
 * CHỈ ĐỌC trong module này; CRUD quản trị danh mục thuộc module quản trị hệ thống.
 */
class EmployeeSkill extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Employee\Models\EmployeeSkillFactory::new();
    }

    protected $table = 'employee_skills';

    protected $fillable = ['organization_id', 'code', 'name', 'group', 'status'];

    public function skillRelations(): HasMany
    {
        return $this->hasMany(EmployeeSkillRelation::class);
    }
}
```

### 3.6. `Models/EmployeeSkillRelation.php` — dạng D

```php
<?php

namespace App\Modules\Employee\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Employee\Enums\SkillLevelEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Bảng nối n–n CÓ THUỘC TÍNH.
 *
 * KHÔNG extends Pivot và KHÔNG dùng $employee->skills()->sync(). Lý do:
 *   - sync() xoá CỨNG dòng không có trong danh sách. Ở đây dòng mang mức độ thành
 *     thạo, số năm kinh nghiệm và file chứng chỉ — xoá cứng là mất dữ liệu nghiệp
 *     vụ, không restore được.
 *   - sync() không kích hoạt model event nên $touches không nổ, employees.updated_at
 *     đứng yên và optimistic lock mù.
 *   - spatie cần một model có khoá chính riêng để gắn media.
 *
 * Vì vậy nó được đối xử y hệt một bảng con 1–n.
 */
class EmployeeSkillRelation extends TenantModel implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const MEDIA_COLLECTION = 'employee_skill_certificates';

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Employee\Models\EmployeeSkillRelationFactory::new();
    }

    protected $table = 'employee_skill_relations';

    protected $touches = ['employee'];

    protected $fillable = [
        'employee_skill_id', 'level', 'years_experience', 'certified_at', 'note',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'certified_at' => 'date',
        'level' => SkillLevelEnum::class,
        'years_experience' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(EmployeeSkill::class, 'employee_skill_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION)
            ->useDisk('private')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }
}
```

---

## 4. Factories

Scribe báo lỗi `factoryCreate/factoryMake` nếu model dùng `HasFactory` mà không có factory đúng namespace. Viết một cái đầy đủ, năm cái còn lại theo đúng khuôn.

### 4.1. `database/factories/Modules/Employee/Models/EmployeeFactory.php`

```php
<?php

namespace Database\Factories\Modules\Employee\Models;

use App\Modules\Employee\Enums\EmployeeStatusEnum;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'code' => strtoupper($this->faker->unique()->bothify('NV####')),
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('09########'),
            'position' => $this->faker->jobTitle(),
            'hired_at' => $this->faker->date(),
            'status' => EmployeeStatusEnum::Active->value,
        ];
    }
}
```

### 4.2. `EmployeeEducationFactory.php`

```php
<?php

namespace Database\Factories\Modules\Employee\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeEducationFactory extends Factory
{
    protected $model = EmployeeEducation::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'employee_id' => Employee::factory(),
            'school_name' => 'Đại học '.$this->faker->city(),
            'degree' => 'Kỹ sư',
            'major' => 'Công nghệ thông tin',
            'grade' => 'Khá',
            'start_date' => '2018-09-01',
            'end_date' => '2022-06-30',
        ];
    }
}
```

### 4.2b. `EmployeeWorkExperienceFactory.php`

```php
<?php

namespace Database\Factories\Modules\Employee\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeWorkExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeWorkExperienceFactory extends Factory
{
    protected $model = EmployeeWorkExperience::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'employee_id' => Employee::factory(),
            'company_name' => 'Công ty '.$this->faker->company(),
            'position' => $this->faker->jobTitle(),
            'department' => 'Phòng Kỹ thuật',
            'start_date' => '2022-07-01',
            'end_date' => null,
            'salary' => 15000000,
            'job_description' => $this->faker->sentence(),
        ];
    }
}
```

### 4.3. `EmployeeFamilyRelationshipFactory.php`

```php
<?php

namespace Database\Factories\Modules\Employee\Models;

use App\Modules\Employee\Enums\FamilyRelationshipEnum;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeFamilyRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFamilyRelationshipFactory extends Factory
{
    protected $model = EmployeeFamilyRelationship::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'employee_id' => Employee::factory(),
            'full_name' => $this->faker->name(),
            'relationship' => FamilyRelationshipEnum::Father->value,
            'birth_year' => 1970,
            'occupation' => $this->faker->jobTitle(),
            'phone' => $this->faker->numerify('09########'),
        ];
    }
}
```

### 4.4. `EmployeeDetailFactory.php`

```php
<?php

namespace Database\Factories\Modules\Employee\Models;

use App\Modules\Employee\Enums\GenderEnum;
use App\Modules\Employee\Enums\MaritalStatusEnum;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeDetailFactory extends Factory
{
    protected $model = EmployeeDetail::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'employee_id' => Employee::factory(),
            'citizen_id' => $this->faker->numerify('0############'),
            'citizen_issued_date' => '2021-03-15',
            'citizen_issued_place' => 'Cục Cảnh sát QLHC về TTXH',
            'birth_date' => '1990-05-20',
            'gender' => GenderEnum::Male->value,
            'hometown' => $this->faker->city(),
            'marital_status' => MaritalStatusEnum::Single->value,
        ];
    }
}
```

### 4.5. `EmployeeSkillFactory.php`

```php
<?php

namespace Database\Factories\Modules\Employee\Models;

use App\Modules\Employee\Models\EmployeeSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeSkillFactory extends Factory
{
    protected $model = EmployeeSkill::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,          // danh mục dùng chung
            'code' => strtoupper($this->faker->unique()->bothify('SK###')),
            'name' => $this->faker->word(),
            'group' => 'Kỹ thuật',
            'status' => 'active',
        ];
    }
}
```

### 4.6. `EmployeeSkillRelationFactory.php`

```php
<?php

namespace Database\Factories\Modules\Employee\Models;

use App\Modules\Employee\Enums\SkillLevelEnum;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeSkill;
use App\Modules\Employee\Models\EmployeeSkillRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeSkillRelationFactory extends Factory
{
    protected $model = EmployeeSkillRelation::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'employee_id' => Employee::factory(),
            'employee_skill_id' => EmployeeSkill::factory(),
            'level' => SkillLevelEnum::Intermediate->value,
            'years_experience' => 3,
            'certified_at' => '2024-01-15',
        ];
    }
}
```

---

## 5. Exception dùng chung

### 5.1. `app/Modules/Core/Exceptions/StaleRecordException.php`

```php
<?php

namespace App\Modules\Core\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Ném khi lock_version client gửi lên không khớp updated_at hiện tại của bản ghi.
 *
 * Trả đúng format lỗi chung của RespondsWithJson — KHÔNG tự chế
 * response()->json(['message' => ...], 409), vì frontend đọc lỗi theo một khuôn duy nhất.
 */
class StaleRecordException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage()
                ?: 'Bản ghi đã được người khác cập nhật. Vui lòng tải lại trang.',
            'error_code' => 'STALE_RECORD',
        ], 409);
    }
}
```

---

## 6. Media dùng chung

### 6.1. `app/Modules/Core/Resources/MediaResource.php`

```php
<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * media[].id chính là giá trị frontend gửi lại qua kept_media_ids[].
 *
 * file_name là tên gốc đã qua bộ sanitize của spatie (cách A, §17.1 tài liệu quy tắc)
 * nên hiển thị thẳng được, không cần custom property original_name.
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Tệp trên disk private không có URL công khai — trả link tải qua route có
        // permission. Không gọi getUrl() cho disk private: nó ném lỗi khi disk
        // không cấu hình 'url'.
        $isPublic = $this->disk === 'public';

        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'url' => $isPublic ? $this->getUrl() : null,
            'download_url' => route('media.download', ['media' => $this->id]),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'human_size' => $this->human_readable_size,
            'thumb_url' => $isPublic && $this->hasGeneratedConversion('thumb') ? $this->getUrl('thumb') : null,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
        ];
    }
}
```

### 6.2. `app/Modules/Core/Controllers/MediaDownloadController.php`

```php
<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Core - Media
 *
 * Tải tệp đính kèm. Bắt buộc với tệp nằm trên disk private (không có URL công khai).
 */
class MediaDownloadController extends Controller
{
    /**
     * Tải tệp đính kèm
     *
     * @urlParam media integer required ID của media. Example: 41
     */
    public function download(Media $media): StreamedResponse
    {
        // Bảng media KHÔNG thuộc tenant nên không có global scope bảo vệ — phải tự
        // kiểm chủ sở hữu. Thiếu bước này là mọi tổ chức tải được tệp của nhau chỉ
        // bằng cách đổi id trên URL.
        $owner = $media->model;

        abort_if($owner === null, 404);
        abort_unless(
            isset($owner->organization_id) && (int) $owner->organization_id === (int) getPermissionsTeamId(),
            403,
            'Bạn không có quyền tải tệp này.'
        );

        return Storage::disk($media->disk)->download(
            $media->getPathRelativeToRoot(),
            $media->file_name
        );
    }
}
```

Đăng ký trong `routes/api.php`, trong nhóm `auth:sanctum`:

```php
Route::get('media/{media}/download', [MediaDownloadController::class, 'download'])
    ->whereNumber('media')
    ->name('media.download');
```

---

## 7. Resources

### 7.1. `Resources/Concerns/HasParentLockVersion.php`

```php
<?php

namespace App\Modules\Employee\Resources\Concerns;

/**
 * Quy tắc 4: mọi Resource dòng con đều xuất parent_lock_version.
 *
 * Service phải eager load quan hệ 'employee', nếu không whenLoaded trả MissingValue
 * và key này biến mất khỏi response — frontend sẽ gán undefined vào state rồi dính
 * 409 ở lần ghi kế tiếp.
 */
trait HasParentLockVersion
{
    protected function parentLockVersion(): mixed
    {
        return $this->whenLoaded('employee', fn () => $this->employee->updated_at?->toIso8601String());
    }
}
```

### 7.2. `Resources/EmployeeResource.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\Core\Resources\MediaResource;
use App\Modules\Employee\Enums\EmployeeStatusEnum;
use App\Modules\Employee\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'hired_at' => $this->hired_at?->format('d/m/Y'),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),

            'avatar' => $this->whenLoaded('media', fn () => $this->hasMedia(Employee::AVATAR_COLLECTION)
                ? new MediaResource($this->getFirstMedia(Employee::AVATAR_COLLECTION))
                : null),

            'educations' => EmployeeEducationResource::collection($this->whenLoaded('educations')),
            'work_experiences' => EmployeeWorkExperienceResource::collection($this->whenLoaded('workExperiences')),
            'family_relationships' => EmployeeFamilyRelationshipResource::collection($this->whenLoaded('familyRelationships')),
            'employee_detail' => new EmployeeDetailResource($this->whenLoaded('employeeDetail')),
            'skill_relations' => EmployeeSkillRelationResource::collection($this->whenLoaded('skillRelations')),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),

            // updated_at để HIỂN THỊ, theo format chung của Danatec.
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            // lock_version là TOKEN khoá lạc quan — ISO8601, KHÔNG format lại.
            // Format d/m/Y mất phần giây → assertNotStale so sai vĩnh viễn.
            'lock_version' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

### 7.3. `Resources/EmployeeCollection.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeCollection extends ResourceCollection
{
    public $collects = EmployeeResource::class;
}
```

### 7.4. `Resources/EmployeeEducationResource.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\Core\Resources\MediaResource;
use App\Modules\Employee\Models\EmployeeEducation;
use App\Modules\Employee\Resources\Concerns\HasParentLockVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeEducationResource extends JsonResource
{
    use FormatsUserSummary, HasParentLockVersion;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'school_name' => $this->school_name,
            'degree' => $this->degree,
            'major' => $this->major,
            'grade' => $this->grade,
            'start_date' => $this->start_date?->format('d/m/Y'),
            'end_date' => $this->end_date?->format('d/m/Y'),

            'certificates' => $this->whenLoaded('media', fn () => MediaResource::collection(
                $this->getMedia(EmployeeEducation::MEDIA_COLLECTION)
            )),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            'parent_lock_version' => $this->parentLockVersion(),
        ];
    }
}
```

### 7.5. `Resources/EmployeeEducationCollection.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeEducationCollection extends ResourceCollection
{
    public $collects = EmployeeEducationResource::class;
}
```

### 7.5b. `Resources/EmployeeWorkExperienceResource.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\Core\Resources\MediaResource;
use App\Modules\Employee\Models\EmployeeWorkExperience;
use App\Modules\Employee\Resources\Concerns\HasParentLockVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeWorkExperienceResource extends JsonResource
{
    use FormatsUserSummary, HasParentLockVersion;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'company_name' => $this->company_name,
            'position' => $this->position,
            'department' => $this->department,
            'start_date' => $this->start_date?->format('d/m/Y'),
            'end_date' => $this->end_date?->format('d/m/Y'),
            // Trả số thô, KHÔNG format tiền tệ ở backend: frontend cần giá trị để đưa
            // vào ô nhập khi sửa, format "15.000.000 ₫" là việc của tầng hiển thị.
            'salary' => $this->salary,
            'leaving_reason' => $this->leaving_reason,
            'job_description' => $this->job_description,

            'documents' => $this->whenLoaded('media', fn () => MediaResource::collection(
                $this->getMedia(EmployeeWorkExperience::MEDIA_COLLECTION)
            )),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            'parent_lock_version' => $this->parentLockVersion(),
        ];
    }
}
```

### 7.5c. `Resources/EmployeeWorkExperienceCollection.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeWorkExperienceCollection extends ResourceCollection
{
    public $collects = EmployeeWorkExperienceResource::class;
}
```

### 7.6. `Resources/EmployeeFamilyRelationshipResource.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\Employee\Resources\Concerns\HasParentLockVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeFamilyRelationshipResource extends JsonResource
{
    use FormatsUserSummary, HasParentLockVersion;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'full_name' => $this->full_name,
            'relationship' => $this->relationship?->value,
            'relationship_label' => $this->relationship?->label(),
            'birth_year' => $this->birth_year,
            'occupation' => $this->occupation,
            'phone' => $this->phone,
            'address' => $this->address,
            'note' => $this->note,

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            'parent_lock_version' => $this->parentLockVersion(),
        ];
    }
}
```

### 7.7. `Resources/EmployeeFamilyRelationshipCollection.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeFamilyRelationshipCollection extends ResourceCollection
{
    public $collects = EmployeeFamilyRelationshipResource::class;
}
```

### 7.8. `Resources/EmployeeDetailResource.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\Core\Resources\MediaResource;
use App\Modules\Employee\Models\EmployeeDetail;
use App\Modules\Employee\Resources\Concerns\HasParentLockVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDetailResource extends JsonResource
{
    use FormatsUserSummary, HasParentLockVersion;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'citizen_id' => $this->citizen_id,
            'citizen_issued_date' => $this->citizen_issued_date?->format('d/m/Y'),
            'citizen_issued_place' => $this->citizen_issued_place,
            'birth_date' => $this->birth_date?->format('d/m/Y'),
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'hometown' => $this->hometown,
            'ethnicity' => $this->ethnicity,
            'religion' => $this->religion,
            'social_insurance_no' => $this->social_insurance_no,
            'tax_code' => $this->tax_code,
            'marital_status' => $this->marital_status?->value,
            'marital_status_label' => $this->marital_status?->label(),

            // Ô cố định: mỗi ô một key riêng, KHÔNG gộp thành mảng. Gộp chung thì khi
            // người dùng thay một ô, hệ thống không biết thay ô nào.
            'citizen_front' => $this->whenLoaded('media', fn () => $this->hasMedia(EmployeeDetail::CITIZEN_FRONT_COLLECTION)
                ? new MediaResource($this->getFirstMedia(EmployeeDetail::CITIZEN_FRONT_COLLECTION))
                : null),
            'citizen_back' => $this->whenLoaded('media', fn () => $this->hasMedia(EmployeeDetail::CITIZEN_BACK_COLLECTION)
                ? new MediaResource($this->getFirstMedia(EmployeeDetail::CITIZEN_BACK_COLLECTION))
                : null),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            'parent_lock_version' => $this->parentLockVersion(),
        ];
    }
}
```

### 7.9. `Resources/EmployeeSkillResource.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'group' => $this->group,
            'status' => $this->status,
            // organization_id = null nghĩa là danh mục dùng chung toàn hệ thống.
            'is_shared' => $this->organization_id === null,
        ];
    }
}
```

### 7.10. `Resources/EmployeeSkillCollection.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeSkillCollection extends ResourceCollection
{
    public $collects = EmployeeSkillResource::class;
}
```

### 7.11. `Resources/EmployeeSkillRelationResource.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\Core\Resources\MediaResource;
use App\Modules\Employee\Models\EmployeeSkillRelation;
use App\Modules\Employee\Resources\Concerns\HasParentLockVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSkillRelationResource extends JsonResource
{
    use FormatsUserSummary, HasParentLockVersion;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_skill_id' => $this->employee_skill_id,
            'skill' => new EmployeeSkillResource($this->whenLoaded('skill')),
            'level' => $this->level?->value,
            'level_label' => $this->level?->label(),
            'years_experience' => $this->years_experience,
            'certified_at' => $this->certified_at?->format('d/m/Y'),
            'note' => $this->note,

            'certificates' => $this->whenLoaded('media', fn () => MediaResource::collection(
                $this->getMedia(EmployeeSkillRelation::MEDIA_COLLECTION)
            )),

            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            'parent_lock_version' => $this->parentLockVersion(),
        ];
    }
}
```

### 7.12. `Resources/EmployeeSkillRelationCollection.php`

```php
<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EmployeeSkillRelationCollection extends ResourceCollection
{
    public $collects = EmployeeSkillRelationResource::class;
}
```

---

## 8. FormRequests

Mọi FormRequest bắt buộc có `rules()`, `messages()` tiếng Việt phủ **mọi** rule đang dùng, `attributes()` và `bodyParameters()` (CLAUDE.md §7). `authorize()` luôn trả `true` — quyền đã chặn ở middleware `permission:` trên route, không kiểm hai lần ở hai nơi.

### 8.1. `Requests/SaveEmployeeRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Enums\EmployeeStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dùng cho cả store và update bản chính.
 *
 * 'sometimes' khi update: màn hình chỉ đổi ảnh đại diện không phải gửi kèm các
 * trường khác. Gửi kèm giá trị cũ là ghi đè ngược thay đổi của người vừa lưu xong.
 */
class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('employee')?->id;
        $orgId = getPermissionsTeamId();

        return [
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'code')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'full_name' => $id ? ['sometimes', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:255'],
            'hired_at' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', EmployeeStatusEnum::rule()],

            // Bắt buộc khi update: không có nó thì optimistic lock vô hiệu.
            'lock_version' => $id ? ['required', 'string'] : ['nullable', 'string'],

            'avatar' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.string' => 'Mã nhân sự phải là chuỗi ký tự.',
            'code.max' => 'Mã nhân sự không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã nhân sự đã tồn tại trong tổ chức.',
            'full_name.required' => 'Họ tên là bắt buộc.',
            'full_name.string' => 'Họ tên phải là chuỗi ký tự.',
            'full_name.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'email.email' => 'Email không đúng định dạng.',
            'email.max' => 'Email không được vượt quá 255 ký tự.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'position.max' => 'Chức vụ không được vượt quá 255 ký tự.',
            'hired_at.date_format' => 'Ngày vào làm phải theo định dạng Y-m-d.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'lock_version.required' => 'Thiếu phiên bản bản ghi. Vui lòng tải lại trang.',
            'lock_version.string' => 'Phiên bản bản ghi không hợp lệ.',
            'avatar.file' => 'Ảnh đại diện phải là một tệp.',
            'avatar.mimes' => 'Ảnh đại diện chỉ nhận jpeg, jpg, png, webp.',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'mã nhân sự',
            'full_name' => 'họ tên',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'position' => 'chức vụ',
            'hired_at' => 'ngày vào làm',
            'status' => 'trạng thái',
            'lock_version' => 'phiên bản bản ghi',
            'avatar' => 'ảnh đại diện',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'code' => ['description' => 'Mã nhân sự, duy nhất trong tổ chức.', 'example' => 'NV0001'],
            'full_name' => ['description' => 'Họ và tên đầy đủ.', 'example' => 'Nguyễn Văn A'],
            'hired_at' => ['description' => 'Ngày vào làm, định dạng Y-m-d.', 'example' => '2024-03-01'],
            'status' => ['description' => 'Trạng thái: active, on_leave, resigned.', 'example' => 'active'],
            'lock_version' => [
                'description' => 'Giá trị lock_version nhận từ lần đọc gần nhất. Sai → 409. Bắt buộc khi cập nhật.',
                'example' => '2026-08-15T08:08:19+07:00',
            ],
            'avatar' => ['description' => 'Ảnh đại diện. Gửi tệp mới = thay ảnh cũ; không gửi = giữ nguyên.'],
        ];
    }
}
```

### 8.2. `Requests/BulkDestroyEmployeeRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:employees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID là bắt buộc.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Phải chọn ít nhất một bản ghi.',
            'ids.max' => 'Chỉ được xóa tối đa 200 bản ghi mỗi lần.',
            'ids.*.integer' => 'ID phải là số nguyên.',
            'ids.*.exists' => 'Có bản ghi không tồn tại trong danh sách đã chọn.',
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'danh sách ID'];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Mảng ID nhân sự cần xóa.', 'example' => [1, 2, 3]],
        ];
    }
}
```

### 8.3. `Requests/BulkUpdateEmployeeStatusRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Enums\EmployeeStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateEmployeeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:employees,id'],
            'status' => ['required', EmployeeStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID là bắt buộc.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Phải chọn ít nhất một bản ghi.',
            'ids.max' => 'Chỉ được cập nhật tối đa 200 bản ghi mỗi lần.',
            'ids.*.integer' => 'ID phải là số nguyên.',
            'ids.*.exists' => 'Có bản ghi không tồn tại trong danh sách đã chọn.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'danh sách ID', 'status' => 'trạng thái'];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Mảng ID nhân sự cần đổi trạng thái.', 'example' => [1, 2]],
            'status' => ['description' => 'Trạng thái mới: active, on_leave, resigned.', 'example' => 'on_leave'],
        ];
    }
}
```

### 8.4. `Requests/ChangeEmployeeStatusRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Enums\EmployeeStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class ChangeEmployeeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', EmployeeStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'trạng thái'];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => ['description' => 'Trạng thái mới: active, on_leave, resigned.', 'example' => 'resigned'],
        ];
    }
}
```

### 8.5. `Requests/SaveFullEmployeeRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Enums\EmployeeStatusEnum;
use App\Modules\Employee\Enums\FamilyRelationshipEnum;
use App\Modules\Employee\Enums\GenderEnum;
use App\Modules\Employee\Enums\MaritalStatusEnum;
use App\Modules\Employee\Enums\SkillLevelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveFullEmployeeRequest extends FormRequest
{
    /**
     * Trần tổng tệp mỗi request. Phải THẤP HƠN max_file_uploads của php.ini (100) —
     * bằng nhau thì phần dư bị PHP cắt im lặng trước khi validate nhìn thấy.
     */
    private const MAX_TOTAL_FILES = 90;

    /**
     * Field gửi dưới dạng chuỗi JSON.
     * detail decode ra OBJECT (mảng kết hợp), ba cái còn lại ra MẢNG — cùng một cơ
     * chế, chỉ khác hình dạng.
     */
    private const JSON_FIELDS = [
        'educations', 'work_experiences', 'family_relationships', 'skill_relations', 'detail',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Vì sao KHÔNG gửi mảng lồng qua FormData:
     *
     *   1. max_input_vars (mặc định 1000) cắt phần ĐUÔI payload và không báo lỗi.
     *      Phần bị cắt có thể là vài phần tử cuối của kept_media_ids[] — số dòng vẫn
     *      khớp, validate vẫn pass, nhưng những media id bị cắt rơi vào $trash và BỊ
     *      XOÁ VĨNH VIỄN khỏi đĩa. Đếm số dòng không bắt được trường hợp này.
     *   2. JSON chiếm đúng 1 input var mỗi mảng — không còn gì để cắt.
     *   3. JSON phân biệt được [] (xoá hết) với vắng mặt (không quản lý) — không cần
     *      thêm cờ sync_* ở cấp danh sách.
     *
     * File KHÔNG đi qua JSON: gửi phẳng theo educations_files[i][], khớp với dòng thứ
     * i của mảng đã decode.
     */
    protected function prepareForValidation(): void
    {
        foreach (self::JSON_FIELDS as $key) {
            if (! $this->has("{$key}_json")) {
                continue;
            }

            $rows = json_decode((string) $this->input("{$key}_json"), true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($rows)) {
                throw ValidationException::withMessages([
                    "{$key}_json" => "Dữ liệu {$key} không phải JSON hợp lệ.",
                ]);
            }

            $this->merge([$key => $rows]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('employee')?->id;
        $orgId = getPermissionsTeamId();

        // Khoá ngoại trỏ danh mục phải scope tenant: exists:employee_skills,id trần là
        // lỗ hổng cross-tenant — tổ chức A gán được danh mục riêng của tổ chức B.
        $skillExists = Rule::exists('employee_skills', 'id')
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $orgId))
            ->whereNull('deleted_at');

        return [
            // --- Bản chính --------------------------------------------------
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'code')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'full_name' => $id ? ['sometimes', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:255'],
            'hired_at' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', EmployeeStatusEnum::rule()],
            'lock_version' => $id ? ['required', 'string'] : ['nullable', 'string'],
            'avatar' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],

            // --- Dạng A: 1–n có file ----------------------------------------
            'educations' => ['nullable', 'array', 'max:50'],
            'educations.*.id' => ['nullable', 'integer'],
            'educations.*.school_name' => ['required_with:educations', 'string', 'max:255'],
            'educations.*.degree' => ['nullable', 'string', 'max:255'],
            'educations.*.major' => ['nullable', 'string', 'max:255'],
            'educations.*.grade' => ['nullable', 'string', 'max:100'],
            'educations.*.start_date' => ['required_with:educations', 'date_format:Y-m-d'],
            'educations.*.end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:educations.*.start_date'],
            'educations.*.sync_attachments' => ['sometimes', 'boolean'],
            'educations.*.kept_media_ids' => ['sometimes', 'array'],
            'educations.*.kept_media_ids.*' => ['integer'],
            'educations_files' => ['sometimes', 'array'],
            'educations_files.*' => ['array', 'max:10'],
            'educations_files.*.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],

            // --- Dạng A thứ hai: quá trình công tác -------------------------
            'work_experiences' => ['nullable', 'array', 'max:50'],
            'work_experiences.*.id' => ['nullable', 'integer'],
            'work_experiences.*.company_name' => ['required_with:work_experiences', 'string', 'max:255'],
            'work_experiences.*.position' => ['required_with:work_experiences', 'string', 'max:255'],
            'work_experiences.*.department' => ['nullable', 'string', 'max:255'],
            'work_experiences.*.start_date' => ['required_with:work_experiences', 'date_format:Y-m-d'],
            'work_experiences.*.end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:work_experiences.*.start_date'],
            'work_experiences.*.salary' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'work_experiences.*.leaving_reason' => ['nullable', 'string', 'max:255'],
            'work_experiences.*.job_description' => ['nullable', 'string', 'max:5000'],
            'work_experiences.*.sync_attachments' => ['sometimes', 'boolean'],
            'work_experiences.*.kept_media_ids' => ['sometimes', 'array'],
            'work_experiences.*.kept_media_ids.*' => ['integer'],
            'work_experiences_files' => ['sometimes', 'array'],
            'work_experiences_files.*' => ['array', 'max:10'],
            'work_experiences_files.*.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],

            // --- Dạng B: 1–n không file -------------------------------------
            'family_relationships' => ['nullable', 'array', 'max:50'],
            'family_relationships.*.id' => ['nullable', 'integer'],
            'family_relationships.*.full_name' => ['required_with:family_relationships', 'string', 'max:255'],
            'family_relationships.*.relationship' => ['required_with:family_relationships', FamilyRelationshipEnum::rule()],
            'family_relationships.*.birth_year' => ['nullable', 'integer', 'digits:4'],
            'family_relationships.*.occupation' => ['nullable', 'string', 'max:255'],
            'family_relationships.*.phone' => ['nullable', 'string', 'max:20'],
            'family_relationships.*.address' => ['nullable', 'string', 'max:500'],
            'family_relationships.*.note' => ['nullable', 'string', 'max:500'],

            // --- Dạng C: OBJECT, không phải mảng ----------------------------
            // Hai trạng thái, KHÔNG hỗ trợ null: vắng mặt = giữ nguyên, {...} = upsert.
            // Xoá phần chi tiết của quan hệ 1–1 bắt buộc là thao tác không có nghĩa
            // nghiệp vụ; để nó tồn tại chỉ tạo thêm một đường mất dữ liệu.
            'detail' => ['sometimes', 'array'],
            'detail.citizen_id' => ['nullable', 'string', 'max:20'],
            'detail.citizen_issued_date' => ['nullable', 'date_format:Y-m-d'],
            'detail.citizen_issued_place' => ['nullable', 'string', 'max:255'],
            'detail.birth_date' => ['nullable', 'date_format:Y-m-d'],
            'detail.gender' => ['nullable', GenderEnum::rule()],
            'detail.hometown' => ['nullable', 'string', 'max:255'],
            'detail.ethnicity' => ['nullable', 'string', 'max:50'],
            'detail.religion' => ['nullable', 'string', 'max:50'],
            'detail.social_insurance_no' => ['nullable', 'string', 'max:30'],
            'detail.tax_code' => ['nullable', 'string', 'max:30'],
            'detail.marital_status' => ['nullable', MaritalStatusEnum::rule()],

            // Ô cố định: field file PHẲNG, không theo chỉ số dòng.
            'citizen_front' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'citizen_back' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],

            // --- Dạng D: n–n có thuộc tính ----------------------------------
            'skill_relations' => ['nullable', 'array', 'max:50'],
            'skill_relations.*.id' => ['nullable', 'integer'],
            'skill_relations.*.employee_skill_id' => ['required_with:skill_relations', 'integer', $skillExists],
            'skill_relations.*.level' => ['nullable', SkillLevelEnum::rule()],
            'skill_relations.*.years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'skill_relations.*.certified_at' => ['nullable', 'date_format:Y-m-d'],
            'skill_relations.*.note' => ['nullable', 'string', 'max:255'],
            'skill_relations.*.sync_attachments' => ['sometimes', 'boolean'],
            'skill_relations.*.kept_media_ids' => ['sometimes', 'array'],
            'skill_relations.*.kept_media_ids.*' => ['integer'],
            'skill_relations_files' => ['sometimes', 'array'],
            'skill_relations_files.*' => ['array', 'max:10'],
            'skill_relations_files.*.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // UNIQUE(employee_id, employee_skill_id): trùng kỹ năng trong cùng payload
            // sẽ ném SQLSTATE 23000 giữa transaction. Bắt ở đây để trả 422 có nghĩa.
            $skillIds = array_filter(array_column($this->input('skill_relations', []), 'employee_skill_id'));

            if (count($skillIds) !== count(array_unique($skillIds))) {
                $validator->errors()->add('skill_relations', 'Danh sách kỹ năng bị trùng.');
            }

            // Trần tổng tệp: rule cho phép 50 dòng × 10 tệp = 500, vượt xa
            // max_file_uploads. Không chặn ở đây thì PHP cắt im lặng phần dư.
            $total = collect($this->allFiles())->flatten(2)->count();

            if ($total > self::MAX_TOTAL_FILES) {
                $validator->errors()->add(
                    'files',
                    'Tổng số tệp trong một lần lưu không được vượt quá '.self::MAX_TOTAL_FILES.'.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Mã nhân sự đã tồn tại trong tổ chức.',
            'code.max' => 'Mã nhân sự không được vượt quá 50 ký tự.',
            'full_name.required' => 'Họ tên là bắt buộc.',
            'full_name.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'email.email' => 'Email không đúng định dạng.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'hired_at.date_format' => 'Ngày vào làm phải theo định dạng Y-m-d.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'lock_version.required' => 'Thiếu phiên bản bản ghi. Vui lòng tải lại trang.',
            'avatar.mimes' => 'Ảnh đại diện chỉ nhận jpeg, jpg, png, webp.',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 5MB.',

            'educations.array' => 'Danh sách học vấn không hợp lệ.',
            'educations.max' => 'Danh sách học vấn tối đa 50 dòng.',
            'educations.*.id.integer' => 'ID học vấn phải là số nguyên.',
            'educations.*.school_name.required_with' => 'Tên trường là bắt buộc.',
            'educations.*.school_name.max' => 'Tên trường không được vượt quá 255 ký tự.',
            'educations.*.start_date.required_with' => 'Ngày bắt đầu là bắt buộc.',
            'educations.*.start_date.date_format' => 'Ngày bắt đầu phải theo định dạng Y-m-d.',
            'educations.*.end_date.date_format' => 'Ngày kết thúc phải theo định dạng Y-m-d.',
            'educations.*.end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'educations.*.kept_media_ids.*.integer' => 'ID tệp giữ lại phải là số nguyên.',
            'educations_files.*.max' => 'Mỗi dòng học vấn tối đa 10 tệp.',
            'educations_files.*.*.file' => 'Tệp đính kèm không hợp lệ.',
            'educations_files.*.*.mimes' => 'Tệp đính kèm chỉ nhận pdf, jpg, jpeg, png.',
            'educations_files.*.*.max' => 'Mỗi tệp đính kèm không được vượt quá 10MB.',

            'work_experiences.array' => 'Danh sách quá trình công tác không hợp lệ.',
            'work_experiences.max' => 'Danh sách quá trình công tác tối đa 50 dòng.',
            'work_experiences.*.id.integer' => 'ID quá trình công tác phải là số nguyên.',
            'work_experiences.*.company_name.required_with' => 'Tên đơn vị công tác là bắt buộc.',
            'work_experiences.*.company_name.max' => 'Tên đơn vị công tác không được vượt quá 255 ký tự.',
            'work_experiences.*.position.required_with' => 'Chức vụ là bắt buộc.',
            'work_experiences.*.position.max' => 'Chức vụ không được vượt quá 255 ký tự.',
            'work_experiences.*.department.max' => 'Phòng ban không được vượt quá 255 ký tự.',
            'work_experiences.*.start_date.required_with' => 'Ngày bắt đầu là bắt buộc.',
            'work_experiences.*.start_date.date_format' => 'Ngày bắt đầu phải theo định dạng Y-m-d.',
            'work_experiences.*.end_date.date_format' => 'Ngày kết thúc phải theo định dạng Y-m-d.',
            'work_experiences.*.end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'work_experiences.*.salary.numeric' => 'Mức lương phải là số.',
            'work_experiences.*.salary.min' => 'Mức lương không được nhỏ hơn 0.',
            'work_experiences.*.salary.max' => 'Mức lương vượt quá giới hạn cho phép.',
            'work_experiences.*.leaving_reason.max' => 'Lý do nghỉ không được vượt quá 255 ký tự.',
            'work_experiences.*.job_description.max' => 'Mô tả công việc không được vượt quá 5000 ký tự.',
            'work_experiences.*.kept_media_ids.*.integer' => 'ID tệp giữ lại phải là số nguyên.',
            'work_experiences_files.*.max' => 'Mỗi dòng công tác tối đa 10 tệp.',
            'work_experiences_files.*.*.mimes' => 'Tệp hồ sơ công tác chỉ nhận pdf, jpg, jpeg, png.',
            'work_experiences_files.*.*.max' => 'Mỗi tệp hồ sơ công tác không được vượt quá 10MB.',

            'family_relationships.max' => 'Danh sách quan hệ gia đình tối đa 50 dòng.',
            'family_relationships.*.full_name.required_with' => 'Họ tên người thân là bắt buộc.',
            'family_relationships.*.relationship.required_with' => 'Quan hệ là bắt buộc.',
            'family_relationships.*.relationship.in' => 'Quan hệ không hợp lệ.',
            'family_relationships.*.birth_year.digits' => 'Năm sinh phải gồm 4 chữ số.',
            'family_relationships.*.phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'family_relationships.*.address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'family_relationships.*.note.max' => 'Ghi chú không được vượt quá 500 ký tự.',

            'detail.array' => 'Thông tin định danh không hợp lệ.',
            'detail.citizen_id.max' => 'Số CCCD không được vượt quá 20 ký tự.',
            'detail.citizen_issued_date.date_format' => 'Ngày cấp phải theo định dạng Y-m-d.',
            'detail.birth_date.date_format' => 'Ngày sinh phải theo định dạng Y-m-d.',
            'detail.gender.in' => 'Giới tính không hợp lệ.',
            'detail.marital_status.in' => 'Tình trạng hôn nhân không hợp lệ.',
            'citizen_front.mimes' => 'Ảnh mặt trước CCCD chỉ nhận jpeg, jpg, png, webp.',
            'citizen_front.max' => 'Ảnh mặt trước CCCD không được vượt quá 5MB.',
            'citizen_back.mimes' => 'Ảnh mặt sau CCCD chỉ nhận jpeg, jpg, png, webp.',
            'citizen_back.max' => 'Ảnh mặt sau CCCD không được vượt quá 5MB.',

            'skill_relations.max' => 'Danh sách kỹ năng tối đa 50 dòng.',
            'skill_relations.*.employee_skill_id.required_with' => 'Kỹ năng là bắt buộc.',
            'skill_relations.*.employee_skill_id.integer' => 'Kỹ năng không hợp lệ.',
            'skill_relations.*.employee_skill_id.exists' => 'Kỹ năng không tồn tại trong danh mục.',
            'skill_relations.*.level.in' => 'Mức độ thành thạo không hợp lệ.',
            'skill_relations.*.years_experience.integer' => 'Số năm kinh nghiệm phải là số nguyên.',
            'skill_relations.*.years_experience.max' => 'Số năm kinh nghiệm không được vượt quá 60.',
            'skill_relations.*.certified_at.date_format' => 'Ngày chứng nhận phải theo định dạng Y-m-d.',
            'skill_relations_files.*.max' => 'Mỗi dòng kỹ năng tối đa 10 tệp.',
            'skill_relations_files.*.*.mimes' => 'Tệp chứng chỉ chỉ nhận pdf, jpg, jpeg, png.',
            'skill_relations_files.*.*.max' => 'Mỗi tệp chứng chỉ không được vượt quá 10MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'mã nhân sự',
            'full_name' => 'họ tên',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'position' => 'chức vụ',
            'hired_at' => 'ngày vào làm',
            'status' => 'trạng thái',
            'lock_version' => 'phiên bản bản ghi',
            'avatar' => 'ảnh đại diện',
            'educations' => 'danh sách học vấn',
            'educations_files' => 'tệp học vấn',
            'work_experiences' => 'danh sách quá trình công tác',
            'work_experiences_files' => 'tệp hồ sơ công tác',
            'family_relationships' => 'danh sách quan hệ gia đình',
            'detail' => 'thông tin định danh',
            'citizen_front' => 'ảnh mặt trước CCCD',
            'citizen_back' => 'ảnh mặt sau CCCD',
            'skill_relations' => 'danh sách kỹ năng',
            'skill_relations_files' => 'tệp chứng chỉ kỹ năng',
        ];
    }

    /**
     * Scribe không tự suy ra được field JSON dạng chuỗi — phải mô tả tay, kèm ví dụ
     * đủ để frontend copy chạy được ngay.
     */
    public function bodyParameters(): array
    {
        return [
            'lock_version' => [
                'description' => 'Giá trị lock_version nhận từ lần đọc gần nhất. Sai → 409. Bắt buộc khi cập nhật.',
                'example' => '2026-08-15T08:08:19+07:00',
            ],
            'educations_json' => [
                'description' => 'Chuỗi JSON mảng học vấn. "[]" = xoá hết dòng con; KHÔNG gửi field = giữ nguyên. '
                    .'Mỗi phần tử: id (null = tạo mới), school_name, degree, major, grade, start_date, end_date, '
                    .'sync_attachments, kept_media_ids.',
                'example' => '[{"id":1,"school_name":"ĐH Bách khoa","start_date":"2018-09-01","end_date":"2022-06-30","sync_attachments":true,"kept_media_ids":[41]}]',
            ],
            'educations_files' => [
                'description' => 'Tệp MỚI của dòng học vấn, gửi phẳng theo chỉ số dòng: '
                    .'educations_files[0][], educations_files[1][]. Khớp với dòng thứ i của educations_json.',
            ],
            'work_experiences_json' => [
                'description' => 'Chuỗi JSON mảng quá trình công tác. Cơ chế giống educations_json. '
                    .'Mỗi phần tử: id, company_name, position, department, start_date, end_date, salary, '
                    .'leaving_reason, job_description, sync_attachments, kept_media_ids.',
                'example' => '[{"id":null,"company_name":"Công ty ABC","position":"Lập trình viên","start_date":"2022-07-01","salary":15000000,"sync_attachments":true,"kept_media_ids":[]}]',
            ],
            'work_experiences_files' => [
                'description' => 'Tệp MỚI của dòng công tác, gửi phẳng theo chỉ số dòng: work_experiences_files[0][].',
            ],
            'family_relationships_json' => [
                'description' => 'Chuỗi JSON mảng quan hệ gia đình. Không có tệp đính kèm.',
                'example' => '[{"id":null,"full_name":"Nguyễn Văn B","relationship":"father","birth_year":1965}]',
            ],
            'detail_json' => [
                'description' => 'Chuỗi JSON OBJECT thông tin định danh. Vắng mặt = giữ nguyên, {...} = upsert. '
                    .'KHÔNG hỗ trợ null.',
                'example' => '{"citizen_id":"001234567890","birth_date":"1990-05-20","gender":"male"}',
            ],
            'citizen_front' => [
                'description' => 'Ảnh mặt trước CCCD. Gửi tệp mới = thay ảnh; không gửi = giữ nguyên. '
                    .'KHÔNG có kept_media_ids cho ô này.',
            ],
            'citizen_back' => ['description' => 'Ảnh mặt sau CCCD. Cơ chế giống citizen_front.'],
            'skill_relations_json' => [
                'description' => 'Chuỗi JSON mảng kỹ năng. employee_skill_id lấy từ GET /api/employee-skills, '
                    .'KHÔNG gõ tự do.',
                'example' => '[{"id":null,"employee_skill_id":3,"level":"advanced","years_experience":5,"sync_attachments":true,"kept_media_ids":[]}]',
            ],
            'skill_relations_files' => [
                'description' => 'Tệp chứng chỉ MỚI, gửi phẳng theo chỉ số dòng: skill_relations_files[0][].',
            ],
        ];
    }
}
```

### 8.6. `Requests/SaveEmployeeEducationRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dùng cho cả store và update dòng con dạng A.
 */
class SaveEmployeeEducationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],

            // Ba field đính kèm — viết lại ở mọi request có tệp.
            // 'sometimes' vì form không có phần tệp sẽ không gửi field nào.
            'sync_attachments' => ['sometimes', 'boolean'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'kept_media_ids' => ['sometimes', 'array'],
            'kept_media_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_name.required' => 'Tên trường là bắt buộc.',
            'school_name.string' => 'Tên trường phải là chuỗi ký tự.',
            'school_name.max' => 'Tên trường không được vượt quá 255 ký tự.',
            'degree.max' => 'Bằng cấp không được vượt quá 255 ký tự.',
            'major.max' => 'Chuyên ngành không được vượt quá 255 ký tự.',
            'grade.max' => 'Xếp loại không được vượt quá 100 ký tự.',
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date_format' => 'Ngày bắt đầu phải theo định dạng Y-m-d.',
            'end_date.date_format' => 'Ngày kết thúc phải theo định dạng Y-m-d.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'sync_attachments.boolean' => 'Cờ đồng bộ tệp không hợp lệ.',
            'attachments.array' => 'Danh sách tệp không hợp lệ.',
            'attachments.max' => 'Chỉ được tải lên tối đa 10 tệp.',
            'attachments.*.file' => 'Tệp đính kèm không hợp lệ.',
            'attachments.*.mimes' => 'Tệp đính kèm chỉ nhận pdf, jpg, jpeg, png.',
            'attachments.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'kept_media_ids.array' => 'Danh sách tệp giữ lại không hợp lệ.',
            'kept_media_ids.*.integer' => 'ID tệp giữ lại phải là số nguyên.',
        ];
    }

    public function attributes(): array
    {
        return [
            'school_name' => 'tên trường',
            'degree' => 'bằng cấp',
            'major' => 'chuyên ngành',
            'grade' => 'xếp loại',
            'start_date' => 'ngày bắt đầu',
            'end_date' => 'ngày kết thúc',
            'attachments' => 'tệp đính kèm',
            'kept_media_ids' => 'tệp giữ lại',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'school_name' => ['description' => 'Tên trường học.', 'example' => 'Đại học Bách khoa'],
            'start_date' => ['description' => 'Ngày bắt đầu, định dạng Y-m-d.', 'example' => '2018-09-01'],
            'sync_attachments' => [
                'description' => 'Gửi 1 khi form có quản lý tệp. KHÔNG gửi = giữ nguyên toàn bộ tệp cũ.',
                'example' => true,
            ],
            'kept_media_ids' => [
                'description' => 'ID tệp cũ giữ lại. Tệp không có trong danh sách sẽ bị xoá khi sync_attachments = 1.',
                'example' => [41, 42],
            ],
            'attachments' => ['description' => 'Chỉ tải lên tệp MỚI. Tối đa 10 tệp, mỗi tệp 10MB.'],
        ];
    }
}
```

### 8.7. `Requests/BulkDestroyEmployeeEducationRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyEmployeeEducationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // KHÔNG dùng exists:employee_educations,id ở đây — service đã lọc qua quan
            // hệ ($employee->educations()->whereIn(...)) nên id của nhân sự khác tự
            // rơi ra ngoài. Thêm exists chỉ tốn một query mà không chặn thêm gì.
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID là bắt buộc.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Phải chọn ít nhất một bản ghi.',
            'ids.max' => 'Chỉ được xóa tối đa 200 bản ghi mỗi lần.',
            'ids.*.integer' => 'ID phải là số nguyên.',
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'danh sách ID'];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Mảng ID học vấn cần xóa.', 'example' => [5, 6]],
        ];
    }
}
```

### 8.7b. `Requests/SaveEmployeeWorkExperienceRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dạng A thứ hai. Ba field đính kèm y hệt SaveEmployeeEducationRequest — cố ý viết
 * lại thay vì tách trait: đọc một request là biết đủ hợp đồng của endpoint đó.
 */
class SaveEmployeeWorkExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'salary' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'leaving_reason' => ['nullable', 'string', 'max:255'],
            'job_description' => ['nullable', 'string', 'max:5000'],

            'sync_attachments' => ['sometimes', 'boolean'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'kept_media_ids' => ['sometimes', 'array'],
            'kept_media_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Tên đơn vị công tác là bắt buộc.',
            'company_name.string' => 'Tên đơn vị công tác phải là chuỗi ký tự.',
            'company_name.max' => 'Tên đơn vị công tác không được vượt quá 255 ký tự.',
            'position.required' => 'Chức vụ là bắt buộc.',
            'position.max' => 'Chức vụ không được vượt quá 255 ký tự.',
            'department.max' => 'Phòng ban không được vượt quá 255 ký tự.',
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date_format' => 'Ngày bắt đầu phải theo định dạng Y-m-d.',
            'end_date.date_format' => 'Ngày kết thúc phải theo định dạng Y-m-d.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'salary.numeric' => 'Mức lương phải là số.',
            'salary.min' => 'Mức lương không được nhỏ hơn 0.',
            'salary.max' => 'Mức lương vượt quá giới hạn cho phép.',
            'leaving_reason.max' => 'Lý do nghỉ không được vượt quá 255 ký tự.',
            'job_description.max' => 'Mô tả công việc không được vượt quá 5000 ký tự.',
            'sync_attachments.boolean' => 'Cờ đồng bộ tệp không hợp lệ.',
            'attachments.array' => 'Danh sách tệp không hợp lệ.',
            'attachments.max' => 'Chỉ được tải lên tối đa 10 tệp.',
            'attachments.*.file' => 'Tệp đính kèm không hợp lệ.',
            'attachments.*.mimes' => 'Tệp đính kèm chỉ nhận pdf, jpg, jpeg, png.',
            'attachments.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'kept_media_ids.array' => 'Danh sách tệp giữ lại không hợp lệ.',
            'kept_media_ids.*.integer' => 'ID tệp giữ lại phải là số nguyên.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_name' => 'tên đơn vị công tác',
            'position' => 'chức vụ',
            'department' => 'phòng ban',
            'start_date' => 'ngày bắt đầu',
            'end_date' => 'ngày kết thúc',
            'salary' => 'mức lương',
            'leaving_reason' => 'lý do nghỉ',
            'job_description' => 'mô tả công việc',
            'attachments' => 'tệp đính kèm',
            'kept_media_ids' => 'tệp giữ lại',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'company_name' => ['description' => 'Tên đơn vị công tác.', 'example' => 'Công ty ABC'],
            'position' => ['description' => 'Chức vụ đảm nhiệm.', 'example' => 'Lập trình viên'],
            'start_date' => ['description' => 'Ngày bắt đầu, định dạng Y-m-d.', 'example' => '2022-07-01'],
            'end_date' => ['description' => 'Ngày kết thúc. Để trống nếu đang làm việc.', 'example' => '2025-12-31'],
            'salary' => ['description' => 'Mức lương, số thô không định dạng.', 'example' => 15000000],
            'sync_attachments' => [
                'description' => 'Gửi 1 khi form có quản lý tệp. KHÔNG gửi = giữ nguyên toàn bộ tệp cũ.',
                'example' => true,
            ],
            'kept_media_ids' => ['description' => 'ID tệp cũ giữ lại.', 'example' => [51]],
            'attachments' => ['description' => 'Chỉ tải lên tệp MỚI. Tối đa 10 tệp, mỗi tệp 10MB.'],
        ];
    }
}
```

### 8.7c. `Requests/BulkDestroyEmployeeWorkExperienceRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyEmployeeWorkExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID là bắt buộc.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Phải chọn ít nhất một bản ghi.',
            'ids.max' => 'Chỉ được xóa tối đa 200 bản ghi mỗi lần.',
            'ids.*.integer' => 'ID phải là số nguyên.',
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'danh sách ID'];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Mảng ID quá trình công tác cần xóa.', 'example' => [11, 12]],
        ];
    }
}
```

### 8.8. `Requests/SaveEmployeeFamilyRelationshipRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Enums\FamilyRelationshipEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dạng B — không có tệp nên KHÔNG có ba field đính kèm.
 */
class SaveEmployeeFamilyRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', FamilyRelationshipEnum::rule()],
            'birth_year' => ['nullable', 'integer', 'digits:4'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ tên người thân là bắt buộc.',
            'full_name.string' => 'Họ tên người thân phải là chuỗi ký tự.',
            'full_name.max' => 'Họ tên người thân không được vượt quá 255 ký tự.',
            'relationship.required' => 'Quan hệ là bắt buộc.',
            'relationship.in' => 'Quan hệ không hợp lệ.',
            'birth_year.integer' => 'Năm sinh phải là số nguyên.',
            'birth_year.digits' => 'Năm sinh phải gồm 4 chữ số.',
            'occupation.max' => 'Nghề nghiệp không được vượt quá 255 ký tự.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'họ tên người thân',
            'relationship' => 'quan hệ',
            'birth_year' => 'năm sinh',
            'occupation' => 'nghề nghiệp',
            'phone' => 'số điện thoại',
            'address' => 'địa chỉ',
            'note' => 'ghi chú',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'full_name' => ['description' => 'Họ tên người thân.', 'example' => 'Nguyễn Văn B'],
            'relationship' => [
                'description' => 'Quan hệ: father, mother, spouse, child, sibling, other.',
                'example' => 'father',
            ],
            'birth_year' => ['description' => 'Năm sinh, 4 chữ số.', 'example' => 1965],
        ];
    }
}
```

### 8.9. `Requests/SaveEmployeeDetailRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Enums\GenderEnum;
use App\Modules\Employee\Enums\MaritalStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dạng C — ô cố định: KHÔNG có kept_media_ids / sync_attachments.
 * singleFile() tự thay thế; gửi tệp mới = thay, không gửi = giữ nguyên.
 */
class SaveEmployeeDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'citizen_id' => ['nullable', 'string', 'max:20'],
            'citizen_issued_date' => ['nullable', 'date_format:Y-m-d'],
            'citizen_issued_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'gender' => ['nullable', GenderEnum::rule()],
            'hometown' => ['nullable', 'string', 'max:255'],
            'ethnicity' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'social_insurance_no' => ['nullable', 'string', 'max:30'],
            'tax_code' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', MaritalStatusEnum::rule()],

            'citizen_front' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'citizen_back' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'citizen_id.max' => 'Số CCCD không được vượt quá 20 ký tự.',
            'citizen_issued_date.date_format' => 'Ngày cấp phải theo định dạng Y-m-d.',
            'citizen_issued_place.max' => 'Nơi cấp không được vượt quá 255 ký tự.',
            'birth_date.date_format' => 'Ngày sinh phải theo định dạng Y-m-d.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'hometown.max' => 'Quê quán không được vượt quá 255 ký tự.',
            'ethnicity.max' => 'Dân tộc không được vượt quá 50 ký tự.',
            'religion.max' => 'Tôn giáo không được vượt quá 50 ký tự.',
            'social_insurance_no.max' => 'Số BHXH không được vượt quá 30 ký tự.',
            'tax_code.max' => 'Mã số thuế không được vượt quá 30 ký tự.',
            'marital_status.in' => 'Tình trạng hôn nhân không hợp lệ.',
            'citizen_front.file' => 'Ảnh mặt trước CCCD không hợp lệ.',
            'citizen_front.mimes' => 'Ảnh mặt trước CCCD chỉ nhận jpeg, jpg, png, webp.',
            'citizen_front.max' => 'Ảnh mặt trước CCCD không được vượt quá 5MB.',
            'citizen_back.file' => 'Ảnh mặt sau CCCD không hợp lệ.',
            'citizen_back.mimes' => 'Ảnh mặt sau CCCD chỉ nhận jpeg, jpg, png, webp.',
            'citizen_back.max' => 'Ảnh mặt sau CCCD không được vượt quá 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'citizen_id' => 'số CCCD',
            'citizen_issued_date' => 'ngày cấp',
            'citizen_issued_place' => 'nơi cấp',
            'birth_date' => 'ngày sinh',
            'gender' => 'giới tính',
            'hometown' => 'quê quán',
            'ethnicity' => 'dân tộc',
            'religion' => 'tôn giáo',
            'social_insurance_no' => 'số BHXH',
            'tax_code' => 'mã số thuế',
            'marital_status' => 'tình trạng hôn nhân',
            'citizen_front' => 'ảnh mặt trước CCCD',
            'citizen_back' => 'ảnh mặt sau CCCD',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'citizen_id' => ['description' => 'Số căn cước công dân.', 'example' => '001234567890'],
            'birth_date' => ['description' => 'Ngày sinh, định dạng Y-m-d.', 'example' => '1990-05-20'],
            'gender' => ['description' => 'Giới tính: male, female, other.', 'example' => 'male'],
            'marital_status' => [
                'description' => 'Tình trạng hôn nhân: single, married, divorced, widowed.',
                'example' => 'single',
            ],
            'citizen_front' => ['description' => 'Ảnh mặt trước CCCD. Gửi tệp mới = thay; không gửi = giữ nguyên.'],
            'citizen_back' => ['description' => 'Ảnh mặt sau CCCD. Cơ chế giống citizen_front.'],
        ];
    }
}
```

### 8.10. `Requests/SaveEmployeeSkillRelationRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use App\Modules\Employee\Enums\SkillLevelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dạng D — xử lý y hệt dạng A, thêm khoá ngoại trỏ danh mục.
 */
class SaveEmployeeSkillRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orgId = getPermissionsTeamId();

        return [
            'employee_skill_id' => [
                'required', 'integer',
                // Scope tenant: không cho gán danh mục riêng của tổ chức khác.
                Rule::exists('employee_skills', 'id')
                    ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $orgId))
                    ->whereNull('deleted_at'),
            ],
            'level' => ['nullable', SkillLevelEnum::rule()],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'certified_at' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],

            'sync_attachments' => ['sometimes', 'boolean'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'kept_media_ids' => ['sometimes', 'array'],
            'kept_media_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_skill_id.required' => 'Kỹ năng là bắt buộc.',
            'employee_skill_id.integer' => 'Kỹ năng không hợp lệ.',
            'employee_skill_id.exists' => 'Kỹ năng không tồn tại trong danh mục.',
            'level.in' => 'Mức độ thành thạo không hợp lệ.',
            'years_experience.integer' => 'Số năm kinh nghiệm phải là số nguyên.',
            'years_experience.min' => 'Số năm kinh nghiệm không được nhỏ hơn 0.',
            'years_experience.max' => 'Số năm kinh nghiệm không được vượt quá 60.',
            'certified_at.date_format' => 'Ngày chứng nhận phải theo định dạng Y-m-d.',
            'note.max' => 'Ghi chú không được vượt quá 255 ký tự.',
            'sync_attachments.boolean' => 'Cờ đồng bộ tệp không hợp lệ.',
            'attachments.max' => 'Chỉ được tải lên tối đa 10 tệp.',
            'attachments.*.mimes' => 'Tệp chứng chỉ chỉ nhận pdf, jpg, jpeg, png.',
            'attachments.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'kept_media_ids.*.integer' => 'ID tệp giữ lại phải là số nguyên.',
        ];
    }

    public function attributes(): array
    {
        return [
            'employee_skill_id' => 'kỹ năng',
            'level' => 'mức độ thành thạo',
            'years_experience' => 'số năm kinh nghiệm',
            'certified_at' => 'ngày chứng nhận',
            'note' => 'ghi chú',
            'attachments' => 'tệp chứng chỉ',
            'kept_media_ids' => 'tệp giữ lại',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'employee_skill_id' => [
                'description' => 'ID kỹ năng, lấy từ GET /api/employee-skills. KHÔNG gõ tự do.',
                'example' => 3,
            ],
            'level' => [
                'description' => 'Mức độ: basic, intermediate, advanced, expert.',
                'example' => 'advanced',
            ],
            'sync_attachments' => ['description' => 'Gửi 1 khi form có quản lý tệp.', 'example' => true],
            'kept_media_ids' => ['description' => 'ID tệp cũ giữ lại.', 'example' => [41]],
            'attachments' => ['description' => 'Chỉ tải lên tệp MỚI. Tối đa 10 tệp, mỗi tệp 10MB.'],
        ];
    }
}
```

### 8.11. `Requests/BulkDestroyEmployeeSkillRelationRequest.php`

```php
<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyEmployeeSkillRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID là bắt buộc.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Phải chọn ít nhất một bản ghi.',
            'ids.max' => 'Chỉ được xóa tối đa 200 bản ghi mỗi lần.',
            'ids.*.integer' => 'ID phải là số nguyên.',
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'danh sách ID'];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Mảng ID kỹ năng của nhân sự cần xóa.', 'example' => [8, 9]],
        ];
    }
}
```

---

## 9. Services

### 9.1. `Services/EmployeeService.php`

```php
<?php

namespace App\Modules\Employee\Services;

use App\Modules\Core\Exceptions\StaleRecordException;
use App\Modules\Employee\Events\EmployeeProfileSaved;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeEducation;
use App\Modules\Employee\Models\EmployeeSkillRelation;
use App\Modules\Employee\Models\EmployeeWorkExperience;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    private const FILLABLE = ['code', 'full_name', 'email', 'phone', 'position', 'hired_at', 'status'];

    private const EDU_FILLABLE = ['school_name', 'degree', 'major', 'grade', 'start_date', 'end_date'];

    private const WORK_FILLABLE = [
        'company_name', 'position', 'department', 'start_date', 'end_date',
        'salary', 'leaving_reason', 'job_description',
    ];

    private const FAMILY_FILLABLE = ['full_name', 'relationship', 'birth_year', 'occupation', 'phone', 'address', 'note'];

    private const SKILL_FILLABLE = ['employee_skill_id', 'level', 'years_experience', 'certified_at', 'note'];

    /**
     * creator.media / editor.media chứ KHÔNG phải creator / editor:
     * FormatsUserSummary gọi $user->getFirstMedia('avatars') để lấy ảnh đại diện,
     * nên chỉ load 'creator' thì mỗi user khác nhau sinh thêm một query. Danh sách
     * 20 dòng do 20 người khác nhau tạo → tối đa 40 query thừa.
     * Load 'creator.media' bao hàm luôn 'creator', không cần liệt kê cả hai.
     */
    private const WITH_ALL = [
        'media', 'educations.media', 'workExperiences.media', 'familyRelationships',
        'employeeDetail.media', 'skillRelations.media', 'skillRelations.skill',
        'creator.media', 'editor.media',
    ];

    /**
     * Quy tắc 3 áp cho cả quan hệ 1–1: saveFull không tự ghi employee_details, nó gọi
     * lại đúng service mà endpoint PUT dùng.
     */
    public function __construct(private readonly EmployeeDetailService $details) {}

    // ----------------------------------------------------------------------
    // Bộ action chuẩn của bảng chính
    // ----------------------------------------------------------------------

    public function stats(array $filters = []): array
    {
        $base = Employee::query()
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'on_leave' => (clone $base)->where('status', 'on_leave')->count(),
            'resigned' => (clone $base)->where('status', 'resigned')->count(),
        ];
    }

    public function index(array $filters = [], int $limit = 10): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['media', 'creator.media', 'editor.media'])
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->where(
                fn ($sub) => $sub->where('full_name', 'like', "%{$kw}%")
                    ->orWhere('code', 'like', "%{$kw}%")
                    ->orWhere('email', 'like', "%{$kw}%")
            ))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_order'] ?? 'desc')
            ->paginate($limit);
    }

    public function show(Employee $employee): Employee
    {
        return $employee->load(self::WITH_ALL);
    }

    public function store(array $data): Employee
    {
        // organization_id do TenantModel tự gán — KHÔNG nhận từ client.
        return Employee::create(Arr::only($data, self::FILLABLE));
    }

    /**
     * Chỗ ghi bản chính DUY NHẤT — cả EmployeeController::update lẫn saveFull đều đi
     * qua đây (quy tắc 3). Optimistic lock nằm ở đây nên không đường nào bỏ sót được.
     *
     * KHÔNG xử lý avatar: hàm này còn được gọi từ bên trong transaction của saveFull,
     * mà upload phải chạy sau commit. Avatar do syncAvatar() lo.
     */
    public function update(Employee $employee, array $data, ?string $clientLockVersion = null): Employee
    {
        return DB::transaction(function () use ($employee, $data, $clientLockVersion) {
            // Đọc lại kèm khoá dòng. Instance từ route model binding mang dữ liệu tại
            // thời điểm dispatch — hai request song song sẽ cùng pass nếu kiểm tra
            // trên nó. whereKey đi qua global scope tenant nên không khoá nhầm dòng
            // của tổ chức khác.
            $locked = Employee::whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $this->assertNotStale($locked, $clientLockVersion);

            $locked->update(Arr::only($data, self::FILLABLE));

            // touch() tường minh: không field nào dirty thì update() không đổi
            // updated_at, và optimistic lock của người khác vẫn thấy giá trị cũ.
            $locked->touch();

            return $locked;
        });
    }

    /**
     * Tách khỏi update() vì singleFile() khiến spatie xoá tệp cũ khỏi đĩa NGAY khi tệp
     * mới lưu xong — hành vi này nằm ngoài transaction của DB. Rollback sau đó là mất
     * tệp vĩnh viễn.
     *
     * MỌI caller phải gọi hàm này SAU khi transaction đã commit.
     */
    public function syncAvatar(Employee $employee, ?UploadedFile $file): Employee
    {
        if ($file && $file->isValid()) {
            $employee->addMedia($file)->toMediaCollection(Employee::AVATAR_COLLECTION);
        }

        return $employee;
    }

    /**
     * Xoá mềm bản chính. employees có SoftDeletes nên onDelete('cascade') KHÔNG kích
     * hoạt — dòng con giữ nguyên và khôi phục lại được cùng bản chính.
     */
    public function destroy(Employee $employee): void
    {
        $employee->delete();
    }

    public function bulkDestroy(array $ids): int
    {
        // Query đi qua global scope tenant nên id của tổ chức khác tự rơi ra ngoài.
        return Employee::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        // update() qua Query Builder KHÔNG kích hoạt model event nên updated_at không
        // tự đổi — phải set tay, nếu không optimistic lock của các màn hình đang mở
        // sẽ không nhận ra dữ liệu đã đổi.
        return Employee::whereIn('id', $ids)->update([
            'status' => $status,
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ]);
    }

    public function changeStatus(Employee $employee, string $status): Employee
    {
        $employee->update(['status' => $status]);

        return $employee->load(['media', 'creator.media', 'editor.media']);
    }

    /**
     * Dữ liệu cho Export. Quan hệ 1–n/n–n gộp một ô ngăn bởi '; ' (CLAUDE.md §6) —
     * cột liệt kê chỉ để đọc, import bỏ qua.
     */
    public function exportQuery(array $filters = [])
    {
        return $this->index($filters, 1000000)
            ->getCollection()
            ->load(self::WITH_ALL);
    }

    // ----------------------------------------------------------------------
    // Endpoint gộp (quy tắc 2)
    // ----------------------------------------------------------------------

    /**
     * CẤM gọi từ màn hình có phân trang.
     *
     * whereNotIn xoá mọi dòng không có trong mảng gửi lên. Frontend chỉ giữ một trang
     * trong state thì toàn bộ phần chưa load bị xoá mềm. Ràng buộc này KHÔNG kiểm
     * chứng được ở backend — màn hình có phân trang phải dùng sub-resource CRUD lẻ.
     */
    public function saveFull(?Employee $employee, array $data): Employee
    {
        $trashMedia = collect();
        $pendingUploads = [];
        $detail = null;             // gán trong closure → BẮT BUỘC capture by reference

        $saved = DB::transaction(function () use ($employee, $data, &$trashMedia, &$pendingUploads, &$detail) {
            // Quy tắc 3: không tự ghi bản chính.
            $employee = $employee
                ? $this->update($employee, $data, $data['lock_version'] ?? null)
                : $this->store($data);

            // array_key_exists chứ không phải cờ: mảng đến từ JSON đã decode nên
            // [] (xoá hết) và vắng mặt (không quản lý) là hai trạng thái phân biệt được.
            if (array_key_exists('educations', $data)) {
                $trashMedia = $trashMedia->merge($this->syncEducations(
                    $employee, $data['educations'], $data['educations_files'] ?? [], $pendingUploads
                ));
            }

            // Dạng A thứ hai — cùng một khuôn, chỉ khác tên quan hệ và tên field file.
            if (array_key_exists('work_experiences', $data)) {
                $trashMedia = $trashMedia->merge($this->syncWorkExperiences(
                    $employee, $data['work_experiences'], $data['work_experiences_files'] ?? [], $pendingUploads
                ));
            }

            if (array_key_exists('family_relationships', $data)) {
                $this->syncFamilyRelationships($employee, $data['family_relationships']);
            }

            // Dạng D dùng đúng pattern của dạng A.
            if (array_key_exists('skill_relations', $data)) {
                $trashMedia = $trashMedia->merge($this->syncSkillRelations(
                    $employee, $data['skill_relations'], $data['skill_relations_files'] ?? [], $pendingUploads
                ));
            }

            // Dạng C: object chứ không phải mảng. Vắng mặt = giữ nguyên, {...} = upsert.
            // Quy tắc 3 → gọi lại service của resource, không tự ghi.
            if (! empty($data['detail'])) {
                $detail = $this->details->upsert($employee, $data['detail']);
            }

            // whereNotIn xoá qua Query Builder nên KHÔNG kích hoạt $touches. Nếu request
            // chỉ xoá dòng con thì không model nào được save và employees.updated_at
            // đứng yên — optimistic lock mù đúng vào thao tác phá hoại nhất.
            $employee->touch();

            return $employee;
        });

        // THỨ TỰ KHÔNG ĐƯỢC ĐỔI:
        //   1. snapshot media  (đã làm trong transaction, TRƯỚC mọi ghi tệp)
        //   2. commit
        //   3. ghi tệp mới
        //   4. xoá tệp cũ
        //
        // Ghi tệp nằm ngoài transaction vì trong đó có lockForUpdate() trên dòng cha —
        // giữ khoá suốt thời gian copy hàng chục tệp khiến request thứ hai chờ tới
        // innodb_lock_wait_timeout (mặc định 50s) rồi 500, thay vì nhận 409 sạch sẽ.
        foreach ($pendingUploads as [$model, $collection, $files]) {
            foreach ($files as $file) {
                if ($file->isValid()) {
                    $model->addMedia($file)->toMediaCollection($collection);
                }
            }
        }

        // Mọi collection singleFile() ghi ở đây, sau commit.
        $this->syncAvatar($saved, $data['avatar'] ?? null);

        if ($detail) {
            $this->details->syncCitizenPhotos(
                $detail, $data['citizen_front'] ?? null, $data['citizen_back'] ?? null
            );
        }

        // Xoá tệp cũ SAU CÙNG: bước ghi trên ném lỗi thì tệp cũ vẫn còn nguyên.
        $trashMedia->each->delete();

        // Fire event ở ĐÂY, không dùng ShouldDispatchAfterCommit: "after commit" vẫn
        // sớm hơn thời điểm tệp yên vị. Event chỉ mang id, không mang model.
        event(new EmployeeProfileSaved($saved->id, $saved->organization_id));

        return $saved->fresh(self::WITH_ALL);
    }

    // ----------------------------------------------------------------------
    // Bulk sync danh sách con (chỉ dùng bởi saveFull)
    // ----------------------------------------------------------------------

    /**
     * Dạng A — khuôn mẫu chính.
     *
     * @param  array  $filesByIndex    educations_files[i][] — khớp theo CHỈ SỐ dòng
     * @param  array  $pendingUploads  gom lại để ghi sau commit
     */
    private function syncEducations(Employee $employee, array $rows, array $filesByIndex, array &$pendingUploads): Collection
    {
        // Chụp id hiện có để (1) phân biệt update với create và (2) chặn client gửi id
        // thuộc bản ghi cha khác.
        //
        // KHÔNG withTrashed() ở dạng A/B: bảng này không có unique constraint nên không
        // dính bẫy SoftDeletes, và id của dòng đã xoá mềm chỉ tới được đây khi client
        // lệch trạng thái — mà đường đó đã bị optimistic lock chặn. Thêm nhánh restore
        // chỉ tạo hành vi âm thầm hồi sinh dòng người dùng đã cố ý xoá.
        $existingIds = $employee->educations()->pluck('id')->all();
        $keepIds = [];
        $allTrashMedia = collect();

        foreach ($rows as $index => $row) {
            $attributes = Arr::only($row, self::EDU_FILLABLE);

            // findOrFail chạy TRÊN quan hệ nên đã giới hạn trong phạm vi cha — chặn
            // IDOR. KHÔNG ĐƯỢC đổi thành EmployeeEducation::find().
            $education = ! empty($row['id']) && in_array((int) $row['id'], $existingIds, true)
                ? tap($employee->educations()->findOrFail($row['id']))->update($attributes)
                : $employee->educations()->create($attributes);

            $keepIds[] = $education->id;

            // SNAPSHOT phải chụp TRƯỚC khi có bất kỳ tệp mới nào được ghi.
            $existingMedia = $education->getMedia(EmployeeEducation::MEDIA_COLLECTION);

            if (! empty($filesByIndex[$index])) {
                $pendingUploads[] = [$education, EmployeeEducation::MEDIA_COLLECTION, $filesByIndex[$index]];
            }

            if ($row['sync_attachments'] ?? false) {
                // Duyệt trên $existingMedia (media của CHÍNH record này) nên client gửi
                // id lạ cũng không xoá được tệp của bản ghi khác.
                $keep = array_map('intval', $row['kept_media_ids'] ?? []);
                $allTrashMedia = $allTrashMedia->merge(
                    $existingMedia->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                );
            }
        }

        $employee->educations()->whereNotIn('id', $keepIds)->delete();

        return $allTrashMedia;
    }

    /**
     * Dạng A thứ hai — giống syncEducations từng dòng, chỉ đổi quan hệ, FILLABLE và
     * collection. Cố ý không tách hàm dùng chung: gộp lại thì phải truyền vào 5 tham
     * số (quan hệ, fillable, collection, tên field file, tên khoá) và người đọc phải
     * dịch ngược từng cái để hiểu một luồng vốn đã khó.
     */
    private function syncWorkExperiences(Employee $employee, array $rows, array $filesByIndex, array &$pendingUploads): Collection
    {
        $existingIds = $employee->workExperiences()->pluck('id')->all();
        $keepIds = [];
        $allTrashMedia = collect();

        foreach ($rows as $index => $row) {
            $attributes = Arr::only($row, self::WORK_FILLABLE);

            $work = ! empty($row['id']) && in_array((int) $row['id'], $existingIds, true)
                ? tap($employee->workExperiences()->findOrFail($row['id']))->update($attributes)
                : $employee->workExperiences()->create($attributes);

            $keepIds[] = $work->id;

            // SNAPSHOT phải chụp TRƯỚC khi có bất kỳ tệp mới nào được ghi.
            $existingMedia = $work->getMedia(EmployeeWorkExperience::MEDIA_COLLECTION);

            if (! empty($filesByIndex[$index])) {
                $pendingUploads[] = [$work, EmployeeWorkExperience::MEDIA_COLLECTION, $filesByIndex[$index]];
            }

            if ($row['sync_attachments'] ?? false) {
                $keep = array_map('intval', $row['kept_media_ids'] ?? []);
                $allTrashMedia = $allTrashMedia->merge(
                    $existingMedia->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                );
            }
        }

        $employee->workExperiences()->whereNotIn('id', $keepIds)->delete();

        return $allTrashMedia;
    }

    /** Dạng B — bỏ toàn bộ phần media. */
    private function syncFamilyRelationships(Employee $employee, array $rows): void
    {
        $existingIds = $employee->familyRelationships()->pluck('id')->all();
        $keepIds = [];

        foreach ($rows as $row) {
            $attributes = Arr::only($row, self::FAMILY_FILLABLE);

            $item = ! empty($row['id']) && in_array((int) $row['id'], $existingIds, true)
                ? tap($employee->familyRelationships()->findOrFail($row['id']))->update($attributes)
                : $employee->familyRelationships()->create($attributes);

            $keepIds[] = $item->id;
        }

        $employee->familyRelationships()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * Dạng D — n–n có thuộc tính, xử lý y hệt 1–n.
     *
     * Khác biệt duy nhất: UNIQUE(employee_id, employee_skill_id) cộng SoftDeletes tạo
     * ra bẫy. MySQL coi mọi NULL là khác nhau nên đưa deleted_at vào unique KHÔNG giải
     * quyết được gì — dòng đã xoá mềm vẫn chiếm chỗ. Người dùng bỏ một kỹ năng rồi
     * thêm lại sẽ nhận SQLSTATE 23000 giữa transaction nếu create() thẳng.
     *
     * Xử lý: tìm cả bản ghi đã xoá mềm theo cột unique, gặp thì restore() thay vì
     * create(). Nhờ vậy mức độ thành thạo và tệp chứng chỉ cũ quay lại nguyên vẹn —
     * đúng lý do chọn bảng nối dày thay vì sync().
     */
    private function syncSkillRelations(Employee $employee, array $rows, array $filesByIndex, array &$pendingUploads): Collection
    {
        // Giống hệt dạng A — KHÔNG withTrashed() ở đây. Dòng đã xoá mềm được xử lý ở
        // nhánh else bên dưới, đúng chỗ khoá unique nằm.
        $existingIds = $employee->skillRelations()->pluck('id')->all();
        $keepIds = [];
        $allTrashMedia = collect();

        foreach ($rows as $index => $row) {
            $attributes = Arr::only($row, self::SKILL_FILLABLE);

            if (! empty($row['id']) && in_array((int) $row['id'], $existingIds, true)) {
                $item = tap($employee->skillRelations()->findOrFail($row['id']))->update($attributes);
            } else {
                // KHÁC BIỆT DUY NHẤT so với dạng A nằm ở đây: dòng mới (id rỗng) có thể
                // đụng dòng đã xoá mềm qua UNIQUE(employee_id, employee_skill_id).
                $item = $employee->skillRelations()
                    ->withTrashed()
                    ->where('employee_skill_id', $attributes['employee_skill_id'])
                    ->first();

                if ($item) {
                    if ($item->trashed()) {
                        $item->restore();
                    }
                    $item->update($attributes);
                } else {
                    $item = $employee->skillRelations()->create($attributes);
                }
            }

            $keepIds[] = $item->id;

            $existingMedia = $item->getMedia(EmployeeSkillRelation::MEDIA_COLLECTION);

            if (! empty($filesByIndex[$index])) {
                $pendingUploads[] = [$item, EmployeeSkillRelation::MEDIA_COLLECTION, $filesByIndex[$index]];
            }

            if ($row['sync_attachments'] ?? false) {
                $keep = array_map('intval', $row['kept_media_ids'] ?? []);
                $allTrashMedia = $allTrashMedia->merge(
                    $existingMedia->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                );
            }
        }

        $employee->skillRelations()->whereNotIn('id', $keepIds)->delete();

        return $allTrashMedia;
    }

    /**
     * So theo Unix timestamp (giây), KHÔNG dùng Carbon::ne().
     *
     * ne() so đến micro-giây, trong khi lock_version gửi cho frontend chỉ xuất đến
     * giây. Cột timestamps() hiện không có phần thập phân nên còn khớp, nhưng đổi sang
     * timestamp(6) là mọi request update 409 vĩnh viễn.
     */
    private function assertNotStale(Employee $employee, ?string $clientLockVersion): void
    {
        if (! $clientLockVersion) {
            return;
        }

        if ($employee->updated_at?->timestamp !== Carbon::parse($clientLockVersion)->timestamp) {
            throw new StaleRecordException();
        }
    }
}
```

### 9.2. `Events/EmployeeProfileSaved.php`

```php
<?php

namespace App\Modules\Employee\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Chỉ mang ID — Listener tự truy vấn lại. Không mang model để tránh serialize dữ liệu
 * nhạy cảm vào queue và tránh đọc trạng thái cũ khi Listener chạy trễ.
 *
 * KHÔNG implements ShouldDispatchAfterCommit: saveFull fire event tay ở cuối, sau khi
 * đã ghi và xoá tệp xong — "after commit" vẫn sớm hơn mốc đó.
 */
class EmployeeProfileSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $employeeId,
        public readonly int $organizationId,
    ) {}
}
```

### 9.3. `Services/EmployeeEducationService.php` — dạng A

```php
<?php

namespace App\Modules\Employee\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeEducation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EmployeeEducationService
{
    private const FILLABLE = ['school_name', 'degree', 'major', 'grade', 'start_date', 'end_date'];

    private const MEDIA_COLLECTION = EmployeeEducation::MEDIA_COLLECTION;

    /**
     * Ba thứ trong danh sách này đều bắt buộc, thiếu cái nào cũng hỏng một kiểu khác:
     *   - 'media'          → Resource gọi getMedia() từng dòng, thiếu là N+1
     *   - 'employee'       → Resource xuất parent_lock_version (quy tắc 4); thiếu thì
     *                        whenLoaded trả MissingValue và key biến mất khỏi response
     *   - 'creator.media'  → FormatsUserSummary gọi getFirstMedia('avatars') trên user;
     *     'editor.media'     chỉ load 'creator' thì mỗi user sinh thêm một query
     */
    private const WITH = ['media', 'employee', 'creator.media', 'editor.media'];
    public function index(Employee $employee, array $filters = [], int $limit = 10): LengthAwarePaginator
    {
        return $employee->educations()
            ->with(self::WITH)
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->where('school_name', 'like', "%{$kw}%"))
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '<=', $d))
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_order'] ?? 'desc')
            ->paginate($limit);
    }

    public function show(EmployeeEducation $education): EmployeeEducation
    {
        return $education->load(self::WITH);
    }

    public function store(Employee $employee, array $data): EmployeeEducation
    {
        $education = DB::transaction(
            fn () => $employee->educations()->create(Arr::only($data, self::FILLABLE))
        );

        // Upload SAU commit — thống nhất một quy tắc với saveFull, không phải nhớ hai
        // trường hợp. Lỗi upload để lại dòng DB không tệp, sửa được bằng UI; upload
        // trong transaction rồi rollback để lại tệp rác không ai dọn.
        $this->uploadAttachments($education, $data['attachments'] ?? []);

        return $education->load(self::WITH);
    }

    /**
     * THỨ TỰ BA BƯỚC KHÔNG ĐƯỢC ĐỔI:
     *   1. snapshot media TRƯỚC khi upload
     *   2. commit transaction
     *   3. mới xoá tệp
     *
     * Xoá tệp vật lý KHÔNG rollback theo transaction.
     */
    public function update(EmployeeEducation $education, array $data): EmployeeEducation
    {
        // Snapshot TRƯỚC khi upload: chụp sau thì tệp vừa upload cũng nằm trong danh
        // sách đối chiếu, mà nó không có trong kept_media_ids → bị xoá ngay lập tức.
        $existing = $education->getMedia(self::MEDIA_COLLECTION);

        DB::transaction(fn () => $education->update(Arr::only($data, self::FILLABLE)));

        $this->uploadAttachments($education, $data['attachments'] ?? []);

        // Không có cờ → request không quản lý tệp → giữ nguyên toàn bộ tệp cũ.
        if ($data['sync_attachments'] ?? false) {
            $keep = array_map('intval', $data['kept_media_ids'] ?? []);

            // Duyệt trên $existing (media của CHÍNH record này) nên client gửi id lạ
            // cũng không xoá được tệp của bản ghi khác.
            $existing->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                ->each->delete();       // ngoài transaction
        }

        return $education->load(self::WITH);
    }

    /**
     * Xoá mềm. Tệp đính kèm giữ nguyên trên storage, phục hồi được khi restore bản
     * ghi — bắt buộc với dữ liệu có giá trị pháp lý.
     */
    public function destroy(EmployeeEducation $education): void
    {
        $education->delete();
    }

    /** Chạy qua quan hệ nên không đụng được dòng của nhân sự khác. */
    public function bulkDestroy(Employee $employee, array $ids): int
    {
        return $employee->educations()->whereIn('id', $ids)->delete();
    }

    /**
     * isValid() bắt buộc: tệp hỏng giữa đường truyền vẫn tới đây dưới dạng
     * UploadedFile, đưa thẳng vào spatie sẽ ném lỗi giữa luồng đã commit.
     */
    private function uploadAttachments(EmployeeEducation $education, array $files): void
    {
        foreach ($files as $file) {
            if ($file->isValid()) {
                $education->addMedia($file)->toMediaCollection(self::MEDIA_COLLECTION);
            }
        }
    }
}
```

### 9.3b. `Services/EmployeeWorkExperienceService.php` — dạng A thứ hai

```php
<?php

namespace App\Modules\Employee\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeWorkExperience;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Bản sao có chủ đích của EmployeeEducationService. Khác đúng bốn chỗ: FILLABLE,
 * MEDIA_COLLECTION, tên quan hệ và bộ lọc index. Thứ tự ba bước (snapshot → commit →
 * xoá) và vị trí upload (sau commit) giữ nguyên tuyệt đối.
 */
class EmployeeWorkExperienceService
{
    private const FILLABLE = [
        'company_name', 'position', 'department', 'start_date', 'end_date',
        'salary', 'leaving_reason', 'job_description',
    ];

    private const MEDIA_COLLECTION = EmployeeWorkExperience::MEDIA_COLLECTION;

    private const WITH = ['media', 'employee', 'creator.media', 'editor.media'];

    public function index(Employee $employee, array $filters = [], int $limit = 10): LengthAwarePaginator
    {
        return $employee->workExperiences()
            ->with(self::WITH)
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->where(
                fn ($sub) => $sub->where('company_name', 'like', "%{$kw}%")
                    ->orWhere('position', 'like', "%{$kw}%")
            ))
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '<=', $d))
            ->orderBy($filters['sort_by'] ?? 'start_date', $filters['sort_order'] ?? 'desc')
            ->paginate($limit);
    }

    public function show(EmployeeWorkExperience $work): EmployeeWorkExperience
    {
        return $work->load(self::WITH);
    }

    public function store(Employee $employee, array $data): EmployeeWorkExperience
    {
        $work = DB::transaction(
            fn () => $employee->workExperiences()->create(Arr::only($data, self::FILLABLE))
        );

        // Upload SAU commit — thống nhất một quy tắc với saveFull.
        $this->uploadAttachments($work, $data['attachments'] ?? []);

        return $work->load(self::WITH);
    }

    /**
     * THỨ TỰ BA BƯỚC KHÔNG ĐƯỢC ĐỔI:
     *   1. snapshot media TRƯỚC khi upload
     *   2. commit transaction
     *   3. mới xoá tệp
     */
    public function update(EmployeeWorkExperience $work, array $data): EmployeeWorkExperience
    {
        $existing = $work->getMedia(self::MEDIA_COLLECTION);

        DB::transaction(fn () => $work->update(Arr::only($data, self::FILLABLE)));

        $this->uploadAttachments($work, $data['attachments'] ?? []);

        if ($data['sync_attachments'] ?? false) {
            $keep = array_map('intval', $data['kept_media_ids'] ?? []);

            $existing->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                ->each->delete();       // ngoài transaction
        }

        return $work->load(self::WITH);
    }

    public function destroy(EmployeeWorkExperience $work): void
    {
        $work->delete();
    }

    public function bulkDestroy(Employee $employee, array $ids): int
    {
        return $employee->workExperiences()->whereIn('id', $ids)->delete();
    }

    private function uploadAttachments(EmployeeWorkExperience $work, array $files): void
    {
        foreach ($files as $file) {
            if ($file->isValid()) {
                $work->addMedia($file)->toMediaCollection(self::MEDIA_COLLECTION);
            }
        }
    }
}
```

### 9.4. `Services/EmployeeFamilyRelationshipService.php` — dạng B

```php
<?php

namespace App\Modules\Employee\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeFamilyRelationship;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

/**
 * Dạng B — giống dạng A trừ toàn bộ phần media. Không transaction: mỗi action chỉ một
 * lệnh ghi, bọc transaction không mua thêm gì.
 */
class EmployeeFamilyRelationshipService
{
    private const FILLABLE = ['full_name', 'relationship', 'birth_year', 'occupation', 'phone', 'address', 'note'];

    private const WITH = ['employee', 'creator.media', 'editor.media'];

    public function index(Employee $employee, array $filters = [], int $limit = 10): LengthAwarePaginator
    {
        return $employee->familyRelationships()
            ->with(self::WITH)
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->where('full_name', 'like', "%{$kw}%"))
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_order'] ?? 'desc')
            ->paginate($limit);
    }

    public function show(EmployeeFamilyRelationship $item): EmployeeFamilyRelationship
    {
        return $item->load(self::WITH);
    }

    public function store(Employee $employee, array $data): EmployeeFamilyRelationship
    {
        return $employee->familyRelationships()
            ->create(Arr::only($data, self::FILLABLE))
            ->load(self::WITH);
    }

    public function update(EmployeeFamilyRelationship $item, array $data): EmployeeFamilyRelationship
    {
        $item->update(Arr::only($data, self::FILLABLE));

        return $item->load(self::WITH);
    }

    public function destroy(EmployeeFamilyRelationship $item): void
    {
        $item->delete();
    }

    public function bulkDestroy(Employee $employee, array $ids): int
    {
        return $employee->familyRelationships()->whereIn('id', $ids)->delete();
    }
}
```

### 9.5. `Services/EmployeeDetailService.php` — dạng C

```php
<?php

namespace App\Modules\Employee\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeDetail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

/**
 * Quan hệ 1–1 — NGOẠI LỆ của quy tắc 1.
 *
 * Chỉ có show + update (upsert). Không có store() và destroy():
 *   - POST vô nghĩa: UNIQUE(employee_id) khiến lần thứ hai luôn hỏng, nên nó chỉ là
 *     PUT với thêm một cách để trả 500.
 *   - DELETE vô nghĩa: xoá dòng chi tiết để lại hồ sơ thiếu thông tin định danh mà
 *     không có trạng thái nào ghi nhận việc đó.
 *
 * Làm đủ 5 endpoint cho 1–1 thì hai trong năm cái cần mã xử lý riêng chỉ để từ chối
 * đúng cách — đó là lý do bỏ.
 */
class EmployeeDetailService
{
    private const FILLABLE = [
        'citizen_id', 'citizen_issued_date', 'citizen_issued_place', 'birth_date',
        'gender', 'hometown', 'ethnicity', 'religion', 'social_insurance_no',
        'tax_code', 'marital_status',
    ];

    public function show(Employee $employee): ?EmployeeDetail
    {
        return $employee->employeeDetail()->with(['media', 'employee', 'creator.media', 'editor.media'])->first();
    }

    /**
     * Chỗ ghi employee_details DUY NHẤT — cả EmployeeDetailController::update lẫn
     * EmployeeService::saveFull đều đi qua đây (quy tắc 3).
     *
     * withTrashed(): UNIQUE(employee_id) vẫn bị dòng đã xoá mềm chiếm chỗ, nên create()
     * sau một lần xoá sẽ ném SQLSTATE 23000. Phải restore, không tạo mới.
     *
     * KHÔNG xử lý ảnh ở đây: hàm này còn được gọi từ bên trong transaction của
     * saveFull, mà hai collection kia là singleFile().
     */
    public function upsert(Employee $employee, array $data): EmployeeDetail
    {
        $attributes = Arr::only($data, self::FILLABLE);

        $detail = $employee->employeeDetail()->withTrashed()->first();

        if ($detail) {
            if ($detail->trashed()) {
                $detail->restore();
            }

            $detail->update($attributes);

            return $detail;
        }

        return $employee->employeeDetail()->create($attributes);
    }

    /**
     * Ô cố định: hai ô có tên riêng, mỗi ô một collection singleFile().
     * KHÔNG dùng kept_media_ids — gửi tệp mới là thay, không gửi là giữ nguyên.
     *
     * MỌI caller phải gọi hàm này SAU khi transaction đã commit: singleFile() khiến
     * spatie xoá tệp cũ khỏi đĩa ngay, và rollback không cứu được.
     */
    public function syncCitizenPhotos(EmployeeDetail $detail, ?UploadedFile $front, ?UploadedFile $back): EmployeeDetail
    {
        if ($front && $front->isValid()) {
            $detail->addMedia($front)->toMediaCollection(EmployeeDetail::CITIZEN_FRONT_COLLECTION);
        }

        if ($back && $back->isValid()) {
            $detail->addMedia($back)->toMediaCollection(EmployeeDetail::CITIZEN_BACK_COLLECTION);
        }

        return $detail;
    }
}
```

### 9.6. `Services/EmployeeSkillService.php` — dạng E

```php
<?php

namespace App\Modules\Employee\Services;

use App\Modules\Employee\Models\EmployeeSkill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Danh mục dùng chung — CHỈ ĐỌC trong phân hệ này.
 *
 * CRUD quản trị danh mục thuộc module quản trị hệ thống. Cho người nhập liệu tạo mục
 * mới ngay tại form sẽ sinh rác danh mục ("PHP", "php", "PHP 8") và không có cách nào
 * gộp lại về sau.
 */
class EmployeeSkillService
{
    public function index(array $filters = [], int $limit = 50): LengthAwarePaginator
    {
        return EmployeeSkill::query()
            // TenantModel lọc organization_id = tổ chức hiện tại. Bỏ scope rồi tự viết
            // điều kiện để lấy THÊM danh mục dùng chung (organization_id = NULL).
            ->withoutGlobalScope('organization')
            ->where(fn ($q) => $q->whereNull('organization_id')
                ->orWhere('organization_id', getPermissionsTeamId()))
            ->where('status', 'active')
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->where('name', 'like', "%{$kw}%"))
            ->when($filters['group'] ?? null, fn ($q, $group) => $q->where('group', $group))
            ->orderBy('name')
            ->paginate($limit);
    }
}
```

### 9.7. `Services/EmployeeSkillRelationService.php` — dạng D

```php
<?php

namespace App\Modules\Employee\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeSkillRelation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * n–n có thuộc tính — đối xử như một bảng con 1–n bình thường.
 * Giống hệt EmployeeEducationService, chỉ khác FILLABLE và nhánh restore trong store().
 */
class EmployeeSkillRelationService
{
    private const FILLABLE = ['employee_skill_id', 'level', 'years_experience', 'certified_at', 'note'];

    private const MEDIA_COLLECTION = EmployeeSkillRelation::MEDIA_COLLECTION;

    private const WITH = ['media', 'employee', 'skill', 'creator.media', 'editor.media'];

    public function index(Employee $employee, array $filters = [], int $limit = 20): LengthAwarePaginator
    {
        return $employee->skillRelations()
            ->with(self::WITH)
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->whereHas(
                'skill', fn ($sub) => $sub->where('name', 'like', "%{$kw}%")
            ))
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_order'] ?? 'desc')
            ->paginate($limit);
    }

    public function show(EmployeeSkillRelation $item): EmployeeSkillRelation
    {
        return $item->load(self::WITH);
    }

    /**
     * UNIQUE(employee_id, employee_skill_id) vẫn bị dòng đã xoá mềm chiếm chỗ. Không
     * có bước restore thì người dùng bỏ một kỹ năng rồi thêm lại sẽ nhận 500.
     */
    public function store(Employee $employee, array $data): EmployeeSkillRelation
    {
        $item = DB::transaction(function () use ($employee, $data) {
            $attributes = Arr::only($data, self::FILLABLE);

            $item = $employee->skillRelations()
                ->withTrashed()
                ->where('employee_skill_id', $attributes['employee_skill_id'])
                ->first();

            if ($item) {
                if ($item->trashed()) {
                    $item->restore();
                }

                $item->update($attributes);

                return $item;
            }

            return $employee->skillRelations()->create($attributes);
        });

        $this->uploadAttachments($item, $data['attachments'] ?? []);

        return $item->load(self::WITH);
    }

    public function update(EmployeeSkillRelation $item, array $data): EmployeeSkillRelation
    {
        $existing = $item->getMedia(self::MEDIA_COLLECTION);

        DB::transaction(fn () => $item->update(Arr::only($data, self::FILLABLE)));

        $this->uploadAttachments($item, $data['attachments'] ?? []);

        if ($data['sync_attachments'] ?? false) {
            $keep = array_map('intval', $data['kept_media_ids'] ?? []);

            $existing->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                ->each->delete();
        }

        return $item->load(self::WITH);
    }

    public function destroy(EmployeeSkillRelation $item): void
    {
        $item->delete();
    }

    public function bulkDestroy(Employee $employee, array $ids): int
    {
        return $employee->skillRelations()->whereIn('id', $ids)->delete();
    }

    private function uploadAttachments(EmployeeSkillRelation $item, array $files): void
    {
        foreach ($files as $file) {
            if ($file->isValid()) {
                $item->addMedia($file)->toMediaCollection(self::MEDIA_COLLECTION);
            }
        }
    }
}
```

---

## 10. Controllers

Controller chỉ làm: nhận request → validate (FormRequest) → gọi Service → trả response chuẩn. **Cấm** trong controller: query builder, business logic, `DB::`, vòng lặp xử lý dữ liệu, check quyền sở hữu thủ công.

### 10.1. `Controllers/EmployeeController.php`

```php
<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Requests\BulkDestroyEmployeeRequest;
use App\Modules\Employee\Requests\BulkUpdateEmployeeStatusRequest;
use App\Modules\Employee\Requests\ChangeEmployeeStatusRequest;
use App\Modules\Employee\Requests\SaveEmployeeRequest;
use App\Modules\Employee\Requests\SaveFullEmployeeRequest;
use App\Modules\Employee\Resources\EmployeeCollection;
use App\Modules\Employee\Resources\EmployeeResource;
use App\Modules\Employee\Services\EmployeeService;

/**
 * @group Employee - Nhân sự
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý hồ sơ nhân sự: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa,
 * xóa hàng loạt, đổi trạng thái, lưu trọn gói (save-full), xuất/nhập.
 */
class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $service) {}

    /**
     * Thống kê nhân sự
     *
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": {"total": 120, "active": 100, "on_leave": 5, "resigned": 15}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->service->stats($request->validated()));
    }

    /**
     * Danh sách nhân sự
     *
     * @queryParam search string Tìm theo họ tên, mã nhân sự hoặc email.
     * @queryParam status string Lọc theo trạng thái: active, on_leave, resigned.
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, full_name, hired_at, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang, -1 = không phân trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Employee\Resources\EmployeeCollection
     * @apiResourceModel App\Modules\Employee\Models\Employee paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->service->index($request->validated(), (int) ($request->limit ?? 10));

        return $this->successCollection(new EmployeeCollection($items));
    }

    /**
     * Chi tiết nhân sự
     *
     * Trả kèm toàn bộ danh sách con — dùng cho màn hình form trọn gói.
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function show(Employee $employee)
    {
        return $this->successResource(new EmployeeResource($this->service->show($employee)));
    }

    /**
     * Tạo nhân sự
     *
     * Chỉ tạo bản chính. Cần tạo kèm danh sách con trong một request thì dùng save-full.
     */
    public function store(SaveEmployeeRequest $request)
    {
        $employee = $this->service->store($request->validated());

        // Sau commit — singleFile() xoá ảnh cũ khỏi đĩa ngay lập tức.
        $this->service->syncAvatar($employee, $request->file('avatar'));

        return $this->successResource(
            new EmployeeResource($employee->load(['media', 'creator.media', 'editor.media'])),
            'Thêm nhân sự thành công'
        );
    }

    /**
     * Cập nhật nhân sự
     *
     * Gọi bằng POST kèm _method=PUT — PHP không parse multipart trên PUT.
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @response 409 {"success": false, "message": "Bản ghi đã được người khác cập nhật. Vui lòng tải lại trang.", "error_code": "STALE_RECORD"}
     */
    public function update(SaveEmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();

        $updated = $this->service->update($employee, $data, $data['lock_version'] ?? null);

        $this->service->syncAvatar($updated, $request->file('avatar'));

        return $this->successResource(
            new EmployeeResource($updated->fresh(['media', 'creator.media', 'editor.media'])),
            'Cập nhật nhân sự thành công'
        );
    }

    /**
     * Xóa nhân sự
     *
     * Xóa mềm; dòng con giữ nguyên và khôi phục lại được cùng bản chính.
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function destroy(Employee $employee)
    {
        $this->service->destroy($employee);

        return $this->success(null, 'Xóa nhân sự thành công');
    }

    /** Xóa nhân sự hàng loạt */
    public function bulkDestroy(BulkDestroyEmployeeRequest $request)
    {
        $deleted = $this->service->bulkDestroy($request->validated()['ids']);

        return $this->success(['deleted' => $deleted], 'Xóa nhân sự thành công');
    }

    /** Cập nhật trạng thái hàng loạt */
    public function bulkUpdateStatus(BulkUpdateEmployeeStatusRequest $request)
    {
        $data = $request->validated();
        $updated = $this->service->bulkUpdateStatus($data['ids'], $data['status']);

        return $this->success(['updated' => $updated], 'Cập nhật trạng thái thành công');
    }

    /**
     * Đổi trạng thái một nhân sự
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function changeStatus(ChangeEmployeeStatusRequest $request, Employee $employee)
    {
        $updated = $this->service->changeStatus($employee, $request->validated()['status']);

        return $this->successResource(new EmployeeResource($updated), 'Cập nhật trạng thái thành công');
    }

    /**
     * Lưu trọn gói hồ sơ nhân sự
     *
     * Gộp bản chính + toàn bộ danh sách con trong MỘT request.
     *
     * CHỈ dùng cho form trọn gói không phân trang: endpoint này xoá mềm mọi dòng con
     * không có trong payload. Màn hình có phân trang phải dùng sub-resource CRUD lẻ.
     *
     * Gửi "[]" cho một field JSON = xoá hết dòng con của quan hệ đó.
     * KHÔNG gửi field = giữ nguyên dữ liệu trong DB.
     *
     * @urlParam employee integer ID nhân sự. Bỏ trống để tạo mới. Example: 1
     * @response 409 {"success": false, "message": "Bản ghi đã được người khác cập nhật. Vui lòng tải lại trang.", "error_code": "STALE_RECORD"}
     */
    public function saveFull(SaveFullEmployeeRequest $request, ?Employee $employee = null)
    {
        $data = $request->validated();

        // Mọi tệp của collection singleFile() — service ghi tất cả sau commit.
        $data['avatar'] = $request->file('avatar');
        $data['citizen_front'] = $request->file('citizen_front');
        $data['citizen_back'] = $request->file('citizen_back');

        return $this->successResource(
            new EmployeeResource($this->service->saveFull($employee, $data)),
            'Lưu hồ sơ thành công'
        );
    }
}
```

> **Ngoài phạm vi bản ví dụ:** `export`, `import`, `importTemplate` và các lớp `Exports/EmployeeExport`, `Imports/EmployeeImport` viết theo CLAUDE.md §6 — không lặp lại ở đây vì chúng không liên quan tới cơ chế cha–con. Điểm duy nhất cần nhớ khi viết chúng: quan hệ 1–n/n–n gộp **một ô** ngăn bởi `; ` và **import bỏ qua** (xem §18 tài liệu quy tắc); quan hệ 1–1 trải phẳng thành cột thường và **có** import.

### 10.2. `Controllers/EmployeeEducationController.php` — dạng A

```php
<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeEducation;
use App\Modules\Employee\Requests\BulkDestroyEmployeeEducationRequest;
use App\Modules\Employee\Requests\SaveEmployeeEducationRequest;
use App\Modules\Employee\Resources\EmployeeEducationCollection;
use App\Modules\Employee\Resources\EmployeeEducationResource;
use App\Modules\Employee\Services\EmployeeEducationService;

/**
 * @group Employee - Học vấn
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Học vấn của nhân sự. Màn hình có phân trang dùng các endpoint này; form trọn gói
 * dùng POST /api/employees/{employee}/save-full.
 *
 * Mọi endpoint ghi trả parent_lock_version trong data — frontend phải gán lại vào
 * state sau MỖI thao tác, nếu không lần ghi kế tiếp sẽ nhận 409.
 */
class EmployeeEducationController extends Controller
{
    public function __construct(private readonly EmployeeEducationService $service) {}

    /**
     * Danh sách học vấn
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @queryParam search string Tìm theo tên trường.
     * @queryParam sort_by string Sắp xếp theo: id, start_date, created_at. Example: id
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Employee\Resources\EmployeeEducationCollection
     * @apiResourceModel App\Modules\Employee\Models\EmployeeEducation paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request, Employee $employee)
    {
        $items = $this->service->index($employee, $request->validated(), (int) ($request->limit ?? 10));

        return $this->successCollection(new EmployeeEducationCollection($items));
    }

    /**
     * Chi tiết học vấn
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam education integer required ID học vấn. Example: 5
     */
    public function show(Employee $employee, EmployeeEducation $education)
    {
        return $this->successResource(new EmployeeEducationResource($this->service->show($education)));
    }

    /**
     * Tạo học vấn
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function store(SaveEmployeeEducationRequest $request, Employee $employee)
    {
        return $this->successResource(
            new EmployeeEducationResource($this->service->store($employee, $request->validated())),
            'Thêm học vấn thành công'
        );
    }

    /**
     * Cập nhật học vấn
     *
     * Gọi bằng POST kèm _method=PUT — PHP không parse multipart trên PUT.
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam education integer required ID học vấn. Example: 5
     */
    public function update(SaveEmployeeEducationRequest $request, Employee $employee, EmployeeEducation $education)
    {
        return $this->successResource(
            new EmployeeEducationResource($this->service->update($education, $request->validated())),
            'Cập nhật học vấn thành công'
        );
    }

    /**
     * Xóa học vấn
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam education integer required ID học vấn. Example: 5
     */
    public function destroy(Employee $employee, EmployeeEducation $education)
    {
        $this->service->destroy($education);

        // Model::delete() có gọi touchOwners() nên fresh() đã mang giá trị mới.
        return $this->success(
            ['parent_lock_version' => $employee->fresh()->updated_at?->toIso8601String()],
            'Xóa học vấn thành công'
        );
    }

    /**
     * Xóa học vấn hàng loạt
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function bulkDestroy(BulkDestroyEmployeeEducationRequest $request, Employee $employee)
    {
        $deleted = $this->service->bulkDestroy($employee, $request->validated()['ids']);

        // bulkDestroy chạy qua Query Builder nên KHÔNG kích hoạt $touches — phải touch
        // tay, nếu không optimistic lock mù đúng vào thao tác xoá hàng loạt.
        $employee->touch();

        return $this->success([
            'deleted' => $deleted,
            'parent_lock_version' => $employee->fresh()->updated_at?->toIso8601String(),
        ], 'Xóa học vấn thành công');
    }
}
```

### 10.2b. `Controllers/EmployeeWorkExperienceController.php` — dạng A thứ hai

```php
<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeWorkExperience;
use App\Modules\Employee\Requests\BulkDestroyEmployeeWorkExperienceRequest;
use App\Modules\Employee\Requests\SaveEmployeeWorkExperienceRequest;
use App\Modules\Employee\Resources\EmployeeWorkExperienceCollection;
use App\Modules\Employee\Resources\EmployeeWorkExperienceResource;
use App\Modules\Employee\Services\EmployeeWorkExperienceService;

/**
 * @group Employee - Quá trình công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quá trình công tác của nhân sự, kèm tệp quyết định/hợp đồng. Màn hình có phân trang
 * dùng các endpoint này; form trọn gói dùng POST /api/employees/{employee}/save-full.
 */
class EmployeeWorkExperienceController extends Controller
{
    public function __construct(private readonly EmployeeWorkExperienceService $service) {}

    /**
     * Danh sách quá trình công tác
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @queryParam search string Tìm theo tên đơn vị hoặc chức vụ.
     * @queryParam sort_by string Sắp xếp theo: id, start_date, created_at. Example: start_date
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Employee\Resources\EmployeeWorkExperienceCollection
     * @apiResourceModel App\Modules\Employee\Models\EmployeeWorkExperience paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request, Employee $employee)
    {
        $items = $this->service->index($employee, $request->validated(), (int) ($request->limit ?? 10));

        return $this->successCollection(new EmployeeWorkExperienceCollection($items));
    }

    /**
     * Chi tiết quá trình công tác
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam workExperience integer required ID quá trình công tác. Example: 11
     */
    public function show(Employee $employee, EmployeeWorkExperience $workExperience)
    {
        return $this->successResource(
            new EmployeeWorkExperienceResource($this->service->show($workExperience))
        );
    }

    /**
     * Tạo quá trình công tác
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function store(SaveEmployeeWorkExperienceRequest $request, Employee $employee)
    {
        return $this->successResource(
            new EmployeeWorkExperienceResource($this->service->store($employee, $request->validated())),
            'Thêm quá trình công tác thành công'
        );
    }

    /**
     * Cập nhật quá trình công tác
     *
     * Gọi bằng POST kèm _method=PUT — PHP không parse multipart trên PUT.
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam workExperience integer required ID quá trình công tác. Example: 11
     */
    public function update(
        SaveEmployeeWorkExperienceRequest $request,
        Employee $employee,
        EmployeeWorkExperience $workExperience
    ) {
        return $this->successResource(
            new EmployeeWorkExperienceResource($this->service->update($workExperience, $request->validated())),
            'Cập nhật quá trình công tác thành công'
        );
    }

    /**
     * Xóa quá trình công tác
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam workExperience integer required ID quá trình công tác. Example: 11
     */
    public function destroy(Employee $employee, EmployeeWorkExperience $workExperience)
    {
        $this->service->destroy($workExperience);

        return $this->success(
            ['parent_lock_version' => $employee->fresh()->updated_at?->toIso8601String()],
            'Xóa quá trình công tác thành công'
        );
    }

    /**
     * Xóa quá trình công tác hàng loạt
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function bulkDestroy(BulkDestroyEmployeeWorkExperienceRequest $request, Employee $employee)
    {
        $deleted = $this->service->bulkDestroy($employee, $request->validated()['ids']);

        // Chạy qua Query Builder nên KHÔNG kích hoạt $touches — phải touch tay.
        $employee->touch();

        return $this->success([
            'deleted' => $deleted,
            'parent_lock_version' => $employee->fresh()->updated_at?->toIso8601String(),
        ], 'Xóa quá trình công tác thành công');
    }
}
```

### 10.3. `Controllers/EmployeeFamilyRelationshipController.php` — dạng B

```php
<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeFamilyRelationship;
use App\Modules\Employee\Requests\SaveEmployeeFamilyRelationshipRequest;
use App\Modules\Employee\Resources\EmployeeFamilyRelationshipCollection;
use App\Modules\Employee\Resources\EmployeeFamilyRelationshipResource;
use App\Modules\Employee\Services\EmployeeFamilyRelationshipService;

/**
 * @group Employee - Quan hệ gia đình
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quan hệ gia đình của nhân sự. Không có tệp đính kèm nên update dùng PUT thẳng.
 */
class EmployeeFamilyRelationshipController extends Controller
{
    public function __construct(private readonly EmployeeFamilyRelationshipService $service) {}

    /**
     * Danh sách quan hệ gia đình
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @queryParam search string Tìm theo họ tên người thân.
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Employee\Resources\EmployeeFamilyRelationshipCollection
     * @apiResourceModel App\Modules\Employee\Models\EmployeeFamilyRelationship paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request, Employee $employee)
    {
        $items = $this->service->index($employee, $request->validated(), (int) ($request->limit ?? 10));

        return $this->successCollection(new EmployeeFamilyRelationshipCollection($items));
    }

    /**
     * Chi tiết quan hệ gia đình
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam relationship integer required ID quan hệ. Example: 3
     */
    public function show(Employee $employee, EmployeeFamilyRelationship $relationship)
    {
        return $this->successResource(
            new EmployeeFamilyRelationshipResource($this->service->show($relationship))
        );
    }

    /**
     * Tạo quan hệ gia đình
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function store(SaveEmployeeFamilyRelationshipRequest $request, Employee $employee)
    {
        return $this->successResource(
            new EmployeeFamilyRelationshipResource($this->service->store($employee, $request->validated())),
            'Thêm quan hệ gia đình thành công'
        );
    }

    /**
     * Cập nhật quan hệ gia đình
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam relationship integer required ID quan hệ. Example: 3
     */
    public function update(
        SaveEmployeeFamilyRelationshipRequest $request,
        Employee $employee,
        EmployeeFamilyRelationship $relationship
    ) {
        return $this->successResource(
            new EmployeeFamilyRelationshipResource($this->service->update($relationship, $request->validated())),
            'Cập nhật quan hệ gia đình thành công'
        );
    }

    /**
     * Xóa quan hệ gia đình
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam relationship integer required ID quan hệ. Example: 3
     */
    public function destroy(Employee $employee, EmployeeFamilyRelationship $relationship)
    {
        $this->service->destroy($relationship);

        return $this->success(
            ['parent_lock_version' => $employee->fresh()->updated_at?->toIso8601String()],
            'Xóa quan hệ gia đình thành công'
        );
    }
}
```

### 10.4. `Controllers/EmployeeDetailController.php` — dạng C

```php
<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Requests\SaveEmployeeDetailRequest;
use App\Modules\Employee\Resources\EmployeeDetailResource;
use App\Modules\Employee\Services\EmployeeDetailService;

/**
 * @group Employee - Thông tin định danh
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quan hệ 1–1 — chỉ có show + update (upsert). Không có store(), không có destroy():
 * POST vô nghĩa vì UNIQUE(employee_id) khiến lần thứ hai luôn hỏng; DELETE vô nghĩa
 * vì để lại hồ sơ thiếu thông tin định danh mà không có trạng thái nào ghi nhận.
 */
class EmployeeDetailController extends Controller
{
    public function __construct(private readonly EmployeeDetailService $service) {}

    /**
     * Xem thông tin định danh
     *
     * Chưa nhập lần nào thì trả 200 với data = null, KHÔNG trả 404 — hồ sơ nhân sự
     * vẫn tồn tại, chỉ là phần chi tiết chưa có. 404 sẽ khiến frontend tưởng cả nhân
     * sự không tồn tại.
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @response 200 {"success": true, "data": null}
     */
    public function show(Employee $employee)
    {
        $detail = $this->service->show($employee);

        if (! $detail) {
            return $this->success(null);
        }

        return $this->successResource(new EmployeeDetailResource($detail));
    }

    /**
     * Cập nhật thông tin định danh (upsert)
     *
     * Gọi bằng POST kèm _method=PUT — PHP không parse multipart trên PUT.
     * Gửi tệp mới = thay ảnh; không gửi = giữ nguyên. KHÔNG có kept_media_ids.
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function update(SaveEmployeeDetailRequest $request, Employee $employee)
    {
        $detail = $this->service->upsert($employee, $request->validated());

        // Sau commit — singleFile() xoá ảnh cũ khỏi đĩa ngay lập tức.
        $this->service->syncCitizenPhotos(
            $detail,
            $request->file('citizen_front'),
            $request->file('citizen_back')
        );

        return $this->successResource(
            new EmployeeDetailResource($detail->fresh(['media', 'employee', 'creator.media', 'editor.media'])),
            'Cập nhật thông tin định danh thành công'
        );
    }
}
```

### 10.5. `Controllers/EmployeeSkillRelationController.php` — dạng D

```php
<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeSkillRelation;
use App\Modules\Employee\Requests\BulkDestroyEmployeeSkillRelationRequest;
use App\Modules\Employee\Requests\SaveEmployeeSkillRelationRequest;
use App\Modules\Employee\Resources\EmployeeSkillRelationCollection;
use App\Modules\Employee\Resources\EmployeeSkillRelationResource;
use App\Modules\Employee\Services\EmployeeSkillRelationService;

/**
 * @group Employee - Kỹ năng của nhân sự
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Bảng nối n–n có thuộc tính, đối xử y hệt một bảng con 1–n: CRUD đầy đủ, có tệp
 * chứng chỉ, xoá mềm. employee_skill_id lấy từ GET /api/employee-skills.
 */
class EmployeeSkillRelationController extends Controller
{
    public function __construct(private readonly EmployeeSkillRelationService $service) {}

    /**
     * Danh sách kỹ năng của nhân sự
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @queryParam search string Tìm theo tên kỹ năng.
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 20
     *
     * @apiResourceCollection App\Modules\Employee\Resources\EmployeeSkillRelationCollection
     * @apiResourceModel App\Modules\Employee\Models\EmployeeSkillRelation paginate=20
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request, Employee $employee)
    {
        $items = $this->service->index($employee, $request->validated(), (int) ($request->limit ?? 20));

        return $this->successCollection(new EmployeeSkillRelationCollection($items));
    }

    /**
     * Chi tiết kỹ năng của nhân sự
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam skillRelation integer required ID bản ghi kỹ năng. Example: 8
     */
    public function show(Employee $employee, EmployeeSkillRelation $skillRelation)
    {
        return $this->successResource(new EmployeeSkillRelationResource($this->service->show($skillRelation)));
    }

    /**
     * Gán kỹ năng cho nhân sự
     *
     * Kỹ năng đã bị xoá trước đó sẽ được khôi phục kèm thuộc tính và tệp chứng chỉ cũ,
     * không tạo bản ghi mới — UNIQUE(employee_id, employee_skill_id) không cho phép.
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function store(SaveEmployeeSkillRelationRequest $request, Employee $employee)
    {
        return $this->successResource(
            new EmployeeSkillRelationResource($this->service->store($employee, $request->validated())),
            'Thêm kỹ năng thành công'
        );
    }

    /**
     * Cập nhật kỹ năng của nhân sự
     *
     * Gọi bằng POST kèm _method=PUT — PHP không parse multipart trên PUT.
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam skillRelation integer required ID bản ghi kỹ năng. Example: 8
     */
    public function update(
        SaveEmployeeSkillRelationRequest $request,
        Employee $employee,
        EmployeeSkillRelation $skillRelation
    ) {
        return $this->successResource(
            new EmployeeSkillRelationResource($this->service->update($skillRelation, $request->validated())),
            'Cập nhật kỹ năng thành công'
        );
    }

    /**
     * Xóa kỹ năng của nhân sự
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     * @urlParam skillRelation integer required ID bản ghi kỹ năng. Example: 8
     */
    public function destroy(Employee $employee, EmployeeSkillRelation $skillRelation)
    {
        $this->service->destroy($skillRelation);

        return $this->success(
            ['parent_lock_version' => $employee->fresh()->updated_at?->toIso8601String()],
            'Xóa kỹ năng thành công'
        );
    }

    /**
     * Xóa kỹ năng hàng loạt
     *
     * @urlParam employee integer required ID nhân sự. Example: 1
     */
    public function bulkDestroy(BulkDestroyEmployeeSkillRelationRequest $request, Employee $employee)
    {
        $deleted = $this->service->bulkDestroy($employee, $request->validated()['ids']);

        $employee->touch();

        return $this->success([
            'deleted' => $deleted,
            'parent_lock_version' => $employee->fresh()->updated_at?->toIso8601String(),
        ], 'Xóa kỹ năng thành công');
    }
}
```

### 10.6. `Controllers/EmployeeSkillController.php` — dạng E

```php
<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Employee\Resources\EmployeeSkillCollection;
use App\Modules\Employee\Services\EmployeeSkillService;

/**
 * @group Employee - Danh mục kỹ năng
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Danh mục dùng chung — CHỈ ĐỌC, phục vụ ô chọn kỹ năng.
 *
 * Thêm/sửa/xoá kỹ năng nằm ở module quản trị hệ thống: cho người nhập hồ sơ tạo kỹ
 * năng mới ngay tại form sẽ sinh rác danh mục ("PHP", "php", "PHP 8") và không có
 * cách nào gộp lại về sau.
 */
class EmployeeSkillController extends Controller
{
    public function __construct(private readonly EmployeeSkillService $service) {}

    /**
     * Danh sách kỹ năng
     *
     * Trả cả danh mục dùng chung (organization_id = null) lẫn danh mục riêng của tổ chức.
     *
     * @queryParam search string Tìm theo tên kỹ năng.
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 50
     *
     * @apiResourceCollection App\Modules\Employee\Resources\EmployeeSkillCollection
     * @apiResourceModel App\Modules\Employee\Models\EmployeeSkill paginate=50
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->service->index($request->validated(), (int) ($request->limit ?? 50));

        return $this->successCollection(new EmployeeSkillCollection($items));
    }
}
```

### 10.7. `Controllers/EnumController.php`

```php
<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Enums\EmployeeStatusEnum;
use App\Modules\Employee\Enums\FamilyRelationshipEnum;
use App\Modules\Employee\Enums\GenderEnum;
use App\Modules\Employee\Enums\MaritalStatusEnum;
use App\Modules\Employee\Enums\SkillLevelEnum;

/**
 * @group Employee - Enum
 *
 * Tra cứu toàn bộ Enum của module để frontend dựng dropdown, không hardcode value/label.
 * Enum của dòng con gộp chung vào endpoint này — không tạo endpoint enum riêng cho bảng con.
 */
class EnumController extends Controller
{
    /**
     * Danh sách Enum của module nhân sự
     *
     * @response 200 {"success": true, "data": {"employee_status": [{"value": "active", "label": "Đang làm việc"}]}}
     */
    public function index()
    {
        return $this->success([
            'employee_status' => $this->mapEnum(EmployeeStatusEnum::cases()),
            'gender' => $this->mapEnum(GenderEnum::cases()),
            'marital_status' => $this->mapEnum(MaritalStatusEnum::cases()),
            'family_relationship' => $this->mapEnum(FamilyRelationshipEnum::cases()),
            'skill_level' => $this->mapEnum(SkillLevelEnum::cases()),
        ]);
    }

    private function mapEnum(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
```

---

## 11. Routes

### 11.1. `Routes/employee.php`

```php
<?php

use App\Modules\Employee\Controllers\EmployeeController;
use App\Modules\Employee\Controllers\EmployeeDetailController;
use App\Modules\Employee\Controllers\EmployeeEducationController;
use App\Modules\Employee\Controllers\EmployeeFamilyRelationshipController;
use App\Modules\Employee\Controllers\EmployeeSkillRelationController;
use App\Modules\Employee\Controllers\EmployeeWorkExperienceController;
use Illuminate\Support\Facades\Route;

// THỨ TỰ QUAN TRỌNG: mọi route tĩnh phải đứng TRƯỚC /{employee}.
// Đặt sau thì POST /api/employees/save-full khớp vào /{employee} với
// {employee}="save-full" → model binding hỏng → 404 không giải thích được.
Route::get('/stats', [EmployeeController::class, 'stats'])
    ->middleware('permission:employees.stats,web');
Route::get('/export', [EmployeeController::class, 'export'])
    ->middleware('permission:employees.export,web');
Route::post('/import', [EmployeeController::class, 'import'])
    ->middleware('permission:employees.import,web');
Route::get('/import-template', [EmployeeController::class, 'importTemplate'])
    ->middleware('permission:employees.import,web');   // dùng chung permission .import
Route::delete('/bulk-delete', [EmployeeController::class, 'bulkDestroy'])
    ->middleware('permission:employees.bulkDestroy,web');
Route::patch('/bulk-status', [EmployeeController::class, 'bulkUpdateStatus'])
    ->middleware('permission:employees.bulkUpdateStatus,web');

// save-full dùng CHUNG permission .store/.update của bản chính — không tạo permission
// riêng (cùng lý lẽ với import-template).
Route::post('/save-full', [EmployeeController::class, 'saveFull'])
    ->middleware('permission:employees.store,web');

Route::get('/', [EmployeeController::class, 'index'])
    ->middleware('permission:employees.index,web');
Route::post('/', [EmployeeController::class, 'store'])
    ->middleware('permission:employees.store,web');

// scopeBindings(): {education} bắt buộc thuộc về {employee} — Laravel tự chặn IDOR,
// không cần check employee_id thủ công trong controller.
Route::scopeBindings()->group(function () {

    // --- Bản chính theo ID -------------------------------------------------
    Route::get('/{employee}', [EmployeeController::class, 'show'])
        ->whereNumber('employee')->middleware('permission:employees.show,web');
    Route::post('/{employee}', [EmployeeController::class, 'update'])          // _method=PUT
        ->whereNumber('employee')->middleware('permission:employees.update,web');
    Route::delete('/{employee}', [EmployeeController::class, 'destroy'])
        ->whereNumber('employee')->middleware('permission:employees.destroy,web');
    Route::patch('/{employee}/status', [EmployeeController::class, 'changeStatus'])
        ->whereNumber('employee')->middleware('permission:employees.changeStatus,web');
    Route::post('/{employee}/save-full', [EmployeeController::class, 'saveFull'])
        ->whereNumber('employee')->middleware('permission:employees.update,web');

    // --- Dạng A: 1–n có tệp ------------------------------------------------
    Route::prefix('/{employee}/educations')->whereNumber('employee')->group(function () {
        Route::delete('/bulk-delete', [EmployeeEducationController::class, 'bulkDestroy'])
            ->middleware('permission:employee-educations.bulkDestroy,web');
        Route::get('/', [EmployeeEducationController::class, 'index'])
            ->middleware('permission:employee-educations.index,web');
        Route::post('/', [EmployeeEducationController::class, 'store'])
            ->middleware('permission:employee-educations.store,web');
        Route::get('/{education}', [EmployeeEducationController::class, 'show'])
            ->whereNumber('education')->middleware('permission:employee-educations.show,web');
        Route::post('/{education}', [EmployeeEducationController::class, 'update'])   // _method=PUT
            ->whereNumber('education')->middleware('permission:employee-educations.update,web');
        Route::delete('/{education}', [EmployeeEducationController::class, 'destroy'])
            ->whereNumber('education')->middleware('permission:employee-educations.destroy,web');
    });

    // --- Dạng A thứ hai: quá trình công tác --------------------------------
    Route::prefix('/{employee}/work-experiences')->whereNumber('employee')->group(function () {
        Route::delete('/bulk-delete', [EmployeeWorkExperienceController::class, 'bulkDestroy'])
            ->middleware('permission:employee-work-experiences.bulkDestroy,web');
        Route::get('/', [EmployeeWorkExperienceController::class, 'index'])
            ->middleware('permission:employee-work-experiences.index,web');
        Route::post('/', [EmployeeWorkExperienceController::class, 'store'])
            ->middleware('permission:employee-work-experiences.store,web');
        Route::get('/{workExperience}', [EmployeeWorkExperienceController::class, 'show'])
            ->whereNumber('workExperience')->middleware('permission:employee-work-experiences.show,web');
        Route::post('/{workExperience}', [EmployeeWorkExperienceController::class, 'update'])   // _method=PUT
            ->whereNumber('workExperience')->middleware('permission:employee-work-experiences.update,web');
        Route::delete('/{workExperience}', [EmployeeWorkExperienceController::class, 'destroy'])
            ->whereNumber('workExperience')->middleware('permission:employee-work-experiences.destroy,web');
    });

    // --- Dạng B: 1–n không tệp → PUT thẳng được ----------------------------
    Route::prefix('/{employee}/family-relationships')->whereNumber('employee')->group(function () {
        Route::get('/', [EmployeeFamilyRelationshipController::class, 'index'])
            ->middleware('permission:employee-family-relationships.index,web');
        Route::post('/', [EmployeeFamilyRelationshipController::class, 'store'])
            ->middleware('permission:employee-family-relationships.store,web');
        Route::get('/{relationship}', [EmployeeFamilyRelationshipController::class, 'show'])
            ->whereNumber('relationship')->middleware('permission:employee-family-relationships.show,web');
        Route::put('/{relationship}', [EmployeeFamilyRelationshipController::class, 'update'])
            ->whereNumber('relationship')->middleware('permission:employee-family-relationships.update,web');
        Route::delete('/{relationship}', [EmployeeFamilyRelationshipController::class, 'destroy'])
            ->whereNumber('relationship')->middleware('permission:employee-family-relationships.destroy,web');
    });

    // --- Dạng C: ngoại lệ quy tắc 1, chỉ show + update ---------------------
    Route::get('/{employee}/detail', [EmployeeDetailController::class, 'show'])
        ->whereNumber('employee')->middleware('permission:employee-details.show,web');
    Route::post('/{employee}/detail', [EmployeeDetailController::class, 'update'])    // _method=PUT
        ->whereNumber('employee')->middleware('permission:employee-details.update,web');

    // --- Dạng D: CRUD đầy đủ như một bảng con 1–n --------------------------
    Route::prefix('/{employee}/skill-relations')->whereNumber('employee')->group(function () {
        Route::delete('/bulk-delete', [EmployeeSkillRelationController::class, 'bulkDestroy'])
            ->middleware('permission:employee-skill-relations.bulkDestroy,web');
        Route::get('/', [EmployeeSkillRelationController::class, 'index'])
            ->middleware('permission:employee-skill-relations.index,web');
        Route::post('/', [EmployeeSkillRelationController::class, 'store'])
            ->middleware('permission:employee-skill-relations.store,web');
        Route::get('/{skillRelation}', [EmployeeSkillRelationController::class, 'show'])
            ->whereNumber('skillRelation')->middleware('permission:employee-skill-relations.show,web');
        Route::post('/{skillRelation}', [EmployeeSkillRelationController::class, 'update'])   // _method=PUT
            ->whereNumber('skillRelation')->middleware('permission:employee-skill-relations.update,web');
        Route::delete('/{skillRelation}', [EmployeeSkillRelationController::class, 'destroy'])
            ->whereNumber('skillRelation')->middleware('permission:employee-skill-relations.destroy,web');
    });
});
```

### 11.2. `Routes/employee_skill.php`

```php
<?php

use App\Modules\Employee\Controllers\EmployeeSkillController;
use Illuminate\Support\Facades\Route;

// Danh mục dùng chung — ngoài phạm vi {employee}, chỉ đọc.
Route::get('/', [EmployeeSkillController::class, 'index'])
    ->middleware('permission:employee-skills.index,web');
```

### 11.3. `Routes/enum.php`

```php
<?php

use App\Modules\Employee\Controllers\EnumController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EnumController::class, 'index']);
```

### 11.4. Đăng ký trong `routes/api.php`

```php
// Trong nhóm middleware('auth:sanctum')
Route::prefix('employees')->middleware('ensure.route.org')->group(function () {
    require base_path('app/Modules/Employee/Routes/employee.php');
});

Route::prefix('employee-skills')->middleware('ensure.route.org')->group(function () {
    require base_path('app/Modules/Employee/Routes/employee_skill.php');
});

// Enum lookup: KHÔNG ensure.route.org (dữ liệu không tenant-scoped), KHÔNG permission
// (dữ liệu tra cứu dùng chung cho nhiều form/permission khác nhau trong module).
Route::prefix('employee-enums')->group(function () {
    require base_path('app/Modules/Employee/Routes/enum.php');
});
```

---

## 12. PermissionSeeder

Thêm vào mảng `PERMISSIONS` của `database/seeders/PermissionSeeder.php` rồi chạy `sail artisan db:seed --class=PermissionSeeder`:

```php
'employees' => [
    'stats', 'index', 'show', 'store', 'update', 'destroy',
    'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
],
'employee-educations' => ['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'],
'employee-work-experiences' => ['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'],
'employee-family-relationships' => ['index', 'show', 'store', 'update', 'destroy'],
'employee-details' => ['show', 'update'],
'employee-skill-relations' => ['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'],
'employee-skills' => ['index'],
```

Không có permission riêng cho `saveFull` và `importTemplate` — chúng dùng chung `employees.store` / `employees.update` / `employees.import`.

Song song, cập nhật `Core\Middleware\LogActivity`: `resourceLabel()`, `actionLabels`, `pathActions` và route param cho **cả** resource con. Thiếu thì nhật ký hoạt động hiện đường dẫn thô thay vì tên tiếng Việt.

---

## 13. Tests bắt buộc

Đặt tại `tests/Feature/Employee/`. Bốn nhóm dưới đây là tối thiểu, không phải gợi ý.

### 13.1. `EmployeeEducationMediaTest.php` — bốn ca đính kèm

```php
<?php

namespace Tests\Feature\Employee;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeEducation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeEducationMediaTest extends TestCase
{
    use RefreshDatabase;

    private function education(): EmployeeEducation
    {
        $education = EmployeeEducation::factory()->create();
        $education->addMedia(UploadedFile::fake()->create('bang-1.pdf', 100))
            ->toMediaCollection(EmployeeEducation::MEDIA_COLLECTION);
        $education->addMedia(UploadedFile::fake()->create('bang-2.pdf', 100))
            ->toMediaCollection(EmployeeEducation::MEDIA_COLLECTION);

        return $education->refresh();
    }

    /** Không gửi cờ → tệp cũ nguyên vẹn. */
    public function test_khong_gui_co_thi_giu_nguyen_file(): void
    {
        $education = $this->education();

        app(\App\Modules\Employee\Services\EmployeeEducationService::class)
            ->update($education, ['school_name' => 'Tên mới', 'start_date' => '2018-09-01']);

        $this->assertCount(2, $education->fresh()->getMedia(EmployeeEducation::MEDIA_COLLECTION));
    }

    /** Gửi cờ + giữ 1 tệp → tệp còn lại bị xoá. */
    public function test_xoa_mot_file(): void
    {
        $education = $this->education();
        $keepId = $education->getMedia(EmployeeEducation::MEDIA_COLLECTION)->first()->id;

        app(\App\Modules\Employee\Services\EmployeeEducationService::class)->update($education, [
            'school_name' => 'Tên mới',
            'start_date' => '2018-09-01',
            'sync_attachments' => true,
            'kept_media_ids' => [$keepId],
        ]);

        $remaining = $education->fresh()->getMedia(EmployeeEducation::MEDIA_COLLECTION);

        $this->assertCount(1, $remaining);
        $this->assertSame($keepId, $remaining->first()->id);
    }

    /** Gửi cờ, không gửi kept_media_ids → xoá hết. */
    public function test_gui_co_khong_gui_kept_thi_xoa_het(): void
    {
        $education = $this->education();

        app(\App\Modules\Employee\Services\EmployeeEducationService::class)->update($education, [
            'school_name' => 'Tên mới',
            'start_date' => '2018-09-01',
            'sync_attachments' => true,
        ]);

        $this->assertCount(0, $education->fresh()->getMedia(EmployeeEducation::MEDIA_COLLECTION));
    }

    /**
     * Tệp MỚI upload không bị xoá nhầm — khoá lỗi snapshot sai thời điểm.
     *
     * Đây là test quan trọng nhất: nó bắt đúng lỗi dễ mắc nhất khi copy code sang
     * module mới, và là lỗi không khôi phục được.
     */
    public function test_file_moi_khong_bi_xoa_nham(): void
    {
        $education = $this->education();
        $keepId = $education->getMedia(EmployeeEducation::MEDIA_COLLECTION)->first()->id;

        app(\App\Modules\Employee\Services\EmployeeEducationService::class)->update($education, [
            'school_name' => 'Tên mới',
            'start_date' => '2018-09-01',
            'sync_attachments' => true,
            'kept_media_ids' => [$keepId],
            'attachments' => [UploadedFile::fake()->create('bang-moi.pdf', 100)],
        ]);

        $remaining = $education->fresh()->getMedia(EmployeeEducation::MEDIA_COLLECTION);

        // 1 tệp cũ giữ lại + 1 tệp mới = 2. Snapshot chụp sau upload sẽ ra 1.
        $this->assertCount(2, $remaining);
        $this->assertTrue($remaining->pluck('file_name')->contains('bang-moi.pdf'));
    }
}
```

### 13.2. `EmployeeSaveFullTest.php` — ba ca của endpoint gộp

```php
<?php

namespace Tests\Feature\Employee;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeEducation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSaveFullTest extends TestCase
{
    use RefreshDatabase;

    /** educations_json = "[]" → xoá hết dòng con. */
    public function test_json_rong_thi_xoa_het_dong_con(): void
    {
        $employee = Employee::factory()->create();
        EmployeeEducation::factory()->count(3)->create(['employee_id' => $employee->id]);

        $this->postJson("/api/employees/{$employee->id}/save-full", [
            '_method' => 'PUT',
            'lock_version' => $employee->updated_at->toIso8601String(),
            'educations_json' => '[]',
        ])->assertOk();

        $this->assertCount(0, $employee->fresh()->educations);
    }

    /** Không gửi educations_json → dòng con nguyên vẹn. */
    public function test_khong_gui_json_thi_giu_nguyen_dong_con(): void
    {
        $employee = Employee::factory()->create();
        EmployeeEducation::factory()->count(3)->create(['employee_id' => $employee->id]);

        $this->postJson("/api/employees/{$employee->id}/save-full", [
            '_method' => 'PUT',
            'lock_version' => $employee->updated_at->toIso8601String(),
            'full_name' => 'Tên mới',
        ])->assertOk();

        $this->assertCount(3, $employee->fresh()->educations);
    }

    /** lock_version cũ → 409, không dòng nào bị đụng tới. */
    public function test_lock_version_cu_thi_409_va_khong_ghi_gi(): void
    {
        $employee = Employee::factory()->create(['full_name' => 'Tên gốc']);
        EmployeeEducation::factory()->count(2)->create(['employee_id' => $employee->id]);

        $staleVersion = $employee->updated_at->copy()->subMinute()->toIso8601String();

        $this->postJson("/api/employees/{$employee->id}/save-full", [
            '_method' => 'PUT',
            'lock_version' => $staleVersion,
            'full_name' => 'Tên bị ghi đè',
            'educations_json' => '[]',
        ])->assertStatus(409);

        $this->assertSame('Tên gốc', $employee->fresh()->full_name);
        $this->assertCount(2, $employee->fresh()->educations);
    }
}
```

### 13.3. `EmployeeSkillRelationRestoreTest.php` — unique + SoftDeletes

```php
<?php

namespace Tests\Feature\Employee;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeSkill;
use App\Modules\Employee\Models\EmployeeSkillRelation;
use App\Modules\Employee\Services\EmployeeSkillRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSkillRelationRestoreTest extends TestCase
{
    use RefreshDatabase;

    /** Xoá rồi thêm lại → restore, không SQLSTATE 23000. */
    public function test_xoa_roi_them_lai_thi_restore(): void
    {
        $employee = Employee::factory()->create();
        $skill = EmployeeSkill::factory()->create();

        $service = app(EmployeeSkillRelationService::class);

        $first = $service->store($employee, [
            'employee_skill_id' => $skill->id,
            'level' => 'advanced',
            'years_experience' => 5,
        ]);

        $service->destroy($first);

        $second = $service->store($employee, [
            'employee_skill_id' => $skill->id,
            'level' => 'expert',
        ]);

        // Cùng một dòng được khôi phục, KHÔNG phải bản ghi mới.
        $this->assertSame($first->id, $second->id);
        $this->assertNull($second->fresh()->deleted_at);
        $this->assertSame(1, EmployeeSkillRelation::withTrashed()
            ->where('employee_id', $employee->id)->count());
    }
}
```

### 13.4. `EmployeeTenantTest.php` — cách ly đa tổ chức

```php
<?php

namespace Tests\Feature\Employee;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeEducation;
use App\Modules\Employee\Models\EmployeeSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTenantTest extends TestCase
{
    use RefreshDatabase;

    /** Tổ chức A không đọc/sửa/xoá được dòng con của tổ chức B. */
    public function test_khong_truy_cap_duoc_du_lieu_to_chuc_khac(): void
    {
        $employeeB = Employee::factory()->create(['organization_id' => 2]);
        $educationB = EmployeeEducation::factory()->create([
            'organization_id' => 2,
            'employee_id' => $employeeB->id,
        ]);

        // Phiên đăng nhập thuộc tổ chức 1 (header X-Organization-Id: 1)
        $this->actingAsOrganization(1);

        $this->getJson("/api/employees/{$employeeB->id}/educations/{$educationB->id}")
            ->assertStatus(404);

        $this->deleteJson("/api/employees/{$employeeB->id}/educations/{$educationB->id}")
            ->assertStatus(404);
    }

    /** Không gán được danh mục riêng của tổ chức khác. */
    public function test_khong_gan_duoc_danh_muc_to_chuc_khac(): void
    {
        $employee = Employee::factory()->create(['organization_id' => 1]);
        $skillOfOtherOrg = EmployeeSkill::factory()->create(['organization_id' => 2]);

        $this->actingAsOrganization(1);

        $this->postJson("/api/employees/{$employee->id}/skill-relations", [
            'employee_skill_id' => $skillOfOtherOrg->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('employee_skill_id');
    }
}
```

Khi sửa lỗi trong một service, rà toàn bộ service cùng pattern:

```bash
grep -rln "sync_attachments" app/Modules/
sail artisan test --filter=Employee
```

---

## Liên quan

- [QUAN_HE_CHA_CON.md](QUAN_HE_CHA_CON.md) — quy tắc (đọc trước file này)
- [CLAUDE.md](../../CLAUDE.md) — quy ước chung Danatec
- [AUTH_TENANT.md](AUTH_TENANT.md) — multi-tenant, permission model
