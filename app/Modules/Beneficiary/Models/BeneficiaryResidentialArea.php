<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Danh mục Tổ dân phố/Thôn — tenant-scoped, cán bộ tự quản trị.
 *
 * Được tham chiếu từ cả `beneficiaries` và `beneficiary_dependents` bằng `restrictOnDelete`,
 * nên xoá một mục đang dùng sẽ bị DB chặn — service bắt lỗi đó và trả 409 kèm gợi ý chuyển
 * sang trạng thái "Ngừng sử dụng".
 */
class BeneficiaryResidentialArea extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryResidentialAreaFactory::new();
    }

    protected $table = 'beneficiary_residential_areas';

    protected $fillable = [
        'name', 'note', 'sort_order', 'status',
        'organization_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'status' => CatalogStatusEnum::class,
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    /** Chỉ mục đang dùng mới được chọn khi nhập hồ sơ mới. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CatalogStatusEnum::Active->value);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class, 'residential_area_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(BeneficiaryDependent::class, 'residential_area_id');
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
