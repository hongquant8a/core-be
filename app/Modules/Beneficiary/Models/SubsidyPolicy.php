<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Support\VietnameseSort;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubsidyPolicy extends TenantModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\SubsidyPolicyFactory::new();
    }

    protected $table = 'beneficiary_subsidy_policies';

    protected $fillable = [
        'beneficiary_type', 'relationship_type', 'amount', 'unit', 'legal_basis',
        'effective_from', 'effective_to', 'organization_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function grants()
    {
        return $this->hasMany(SubsidyGrant::class, 'beneficiary_subsidy_policy_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where('legal_basis', 'like', '%'.$search.'%'))
            ->when($filters['beneficiary_type'] ?? null, fn ($q, $type) => $q->where('beneficiary_type', $type))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('effective_from', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('effective_from', '<=', $date))
            ->when($filters['sort_by'] ?? 'effective_from', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'amount', 'effective_from', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'effective_from';
                VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
