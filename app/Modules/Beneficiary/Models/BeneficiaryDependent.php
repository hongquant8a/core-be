<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Thân nhân — dạng B (1–n không tệp).
 *
 * Là dòng con trực thuộc một hồ sơ chứ không phải thực thể dùng chung: hai người có công
 * cùng khai một người con sẽ có hai dòng độc lập. Vì vậy `id_number` không unique.
 */
class BeneficiaryDependent extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryDependentFactory::new();
    }

    protected $table = 'beneficiary_dependents';

    protected $touches = ['beneficiary'];

    protected $fillable = [
        'full_name', 'birth_date', 'birth_year', 'gender', 'id_number', 'phone',
        'residential_area_id', 'address', 'latitude', 'longitude', 'note',
        'relationship_id', 'is_primary',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'birth_year' => 'integer',
        'gender' => GenderEnum::class,
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());

        // Cùng quy tắc với Beneficiary: birth_year suy ra từ birth_date khi có.
        static::saving(function (self $model) {
            if ($model->birth_date) {
                $model->birth_year = $model->birth_date->year;
            }
        });
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function residentialArea(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryResidentialArea::class, 'residential_area_id');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryRelationship::class, 'relationship_id');
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
