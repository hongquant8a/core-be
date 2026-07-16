<?php

namespace App\Modules\Beneficiary\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot N-N Beneficiary <-> Dependent, có cột thuộc tính riêng (relationship_type, eligible_from/until, status).
 * Có PK tự tăng riêng (không phải composite key) nên $incrementing = true theo đúng convention Laravel
 * cho custom pivot model có primary key riêng.
 */
class BeneficiaryDependentRelation extends Pivot
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryDependentRelationFactory::new();
    }

    public $incrementing = true;

    protected $table = 'beneficiary_dependent_relations';

    protected $fillable = [
        'beneficiary_id', 'dependent_id', 'relationship_type', 'eligible_from', 'eligible_until', 'status', 'note',
    ];

    protected $casts = [
        'eligible_from' => 'date',
        'eligible_until' => 'date',
    ];

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function dependent()
    {
        return $this->belongsTo(Dependent::class);
    }
}
