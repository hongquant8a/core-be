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
        'household_id', 'full_name', 'date_of_birth', 'gender', 'id_number', 'is_alive',
        'death_date', 'eligibility_status', 'note', 'organization_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'death_date' => 'date',
        'is_alive' => 'boolean',
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

    public function beneficiaries()
    {
        return $this->belongsToMany(Beneficiary::class, 'beneficiary_dependent_relations')
            ->using(BeneficiaryDependentRelation::class)
            ->withPivot(['id', 'relationship_type', 'eligible_from', 'eligible_until', 'status', 'note'])
            ->withTimestamps();
    }

    public function dependentRelations()
    {
        return $this->hasMany(BeneficiaryDependentRelation::class);
    }

    public function subsidyGrants()
    {
        return $this->morphMany(SubsidyGrant::class, 'subject');
    }

    public function activeSubsidyGrants()
    {
        return $this->subsidyGrants()->where('status', 'active');
    }

    public function statusHistories()
    {
        return $this->morphMany(StatusHistory::class, 'subject');
    }

    public function visitSchedules()
    {
        return $this->morphMany(VisitSchedule::class, 'subject');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('full_name', 'like', '%'.$search.'%')
                ->orWhere('id_number', 'like', '%'.$search.'%')))
            ->when($filters['household_id'] ?? null, fn ($q, $id) => $q->where('household_id', $id))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'full_name', 'date_of_birth', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
