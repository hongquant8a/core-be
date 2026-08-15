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
 * Danh mục Mối quan hệ giữa thân nhân và người có công (Vợ, Chồng, Con, Bố, Mẹ...).
 *
 * Được tham chiếu từ `beneficiary_dependents` bằng `restrictOnDelete`.
 */
class BeneficiaryRelationship extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryRelationshipFactory::new();
    }

    protected $table = 'beneficiary_relationships';

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

    public function dependents(): HasMany
    {
        return $this->hasMany(BeneficiaryDependent::class, 'relationship_id');
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
