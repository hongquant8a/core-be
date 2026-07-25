<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\VietnameseSort;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dependent extends TenantModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\DependentFactory::new();
    }

    protected $table = 'beneficiary_dependents';

    protected $fillable = [
        'household_id', 'residential_area_id', 'full_name', 'date_of_birth', 'gender', 'id_number',
        'phone', 'latitude', 'longitude', 'note', 'organization_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected static function booted()
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function residentialArea()
    {
        return $this->belongsTo(ResidentialArea::class);
    }

    public function beneficiaries()
    {
        return $this->belongsToMany(Beneficiary::class, 'beneficiary_dependent_relations')
            ->using(BeneficiaryDependentRelation::class)
            ->withPivot(['id', 'relationship_type', 'note'])
            ->withTimestamps();
    }

    public function dependentRelations()
    {
        return $this->hasMany(BeneficiaryDependentRelation::class);
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('full_name', 'like', '%'.$search.'%')
                ->orWhere('id_number', 'like', '%'.$search.'%')))
            ->when($filters['household_id'] ?? null, fn ($q, $id) => $q->where('household_id', $id))
            ->when($filters['residential_area_id'] ?? null, fn ($q, $id) => $q->where('residential_area_id', $id))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'full_name', 'date_of_birth', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
