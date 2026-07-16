<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\VietnameseSort;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Beneficiary extends TenantModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryFactory::new();
    }

    protected $table = 'beneficiaries';

    protected $fillable = [
        'household_id', 'full_name', 'date_of_birth', 'birth_year', 'gender', 'id_number', 'injury_rate',
        'recognition_decision_no', 'recognition_date', 'status', 'death_date', 'address', 'latitude',
        'longitude', 'phone', 'note', 'organization_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'recognition_date' => 'date',
        'death_date' => 'date',
        'injury_rate' => 'decimal:2',
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

    public function classifications()
    {
        return $this->hasMany(BeneficiaryClassification::class);
    }

    public function primaryClassification()
    {
        return $this->hasOne(BeneficiaryClassification::class)->where('is_primary', true);
    }

    public function dependents()
    {
        return $this->belongsToMany(Dependent::class, 'beneficiary_dependent_relations')
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
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['household_id'] ?? null, fn ($q, $id) => $q->where('household_id', $id))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'full_name', 'date_of_birth', 'status', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
