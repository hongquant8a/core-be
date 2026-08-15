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
 * Danh mục Loại đối tượng (Thương binh, Bệnh binh, Thân nhân liệt sĩ...).
 *
 * Được tham chiếu từ bảng nối `beneficiary_type_relations` bằng `restrictOnDelete`.
 */
class BeneficiaryType extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryTypeFactory::new();
    }

    protected $table = 'beneficiary_types';

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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CatalogStatusEnum::Active->value);
    }

    public function typeRelations(): HasMany
    {
        return $this->hasMany(BeneficiaryTypeRelation::class, 'beneficiary_type_id');
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
