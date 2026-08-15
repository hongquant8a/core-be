<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hồ sơ người có công — bảng chính của module.
 *
 * KHÔNG có cột `status`: nghiệp vụ không có trạng thái cho hồ sơ, hoặc còn trong danh sách
 * quản lý, hoặc đã xoá mềm. Ba bảng danh mục thì ngược lại — xem `CatalogStatusEnum`.
 *
 * KHÔNG implements HasMedia: tệp đính kèm nằm ở dòng con (`BeneficiaryTypeRelation` và
 * `BeneficiaryDocument`), không gắn thẳng vào hồ sơ.
 */
class Beneficiary extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryFactory::new();
    }

    protected $table = 'beneficiaries';

    protected $fillable = [
        'full_name', 'birth_date', 'birth_year', 'gender', 'id_number', 'phone',
        'residential_area_id', 'address', 'latitude', 'longitude', 'note',
        'organization_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'birth_year' => 'integer',
        'gender' => GenderEnum::class,
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());

        // birth_year luôn suy ra từ birth_date khi có, để hai cột không bao giờ lệch nhau.
        // Chỉ khi không biết ngày/tháng thì cán bộ mới nhập riêng birth_year.
        static::saving(function (self $model) {
            if ($model->birth_date) {
                $model->birth_year = $model->birth_date->year;
            }
        });
    }

    public function residentialArea(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryResidentialArea::class, 'residential_area_id');
    }

    public function typeRelations(): HasMany
    {
        return $this->hasMany(BeneficiaryTypeRelation::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(BeneficiaryDependent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BeneficiaryDocument::class);
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
