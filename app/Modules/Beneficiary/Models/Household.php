<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\VietnameseSort;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Household extends TenantModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\HouseholdFactory::new();
    }

    protected $table = 'beneficiary_households';

    protected $fillable = [
        'residential_area_id', 'household_code', 'head_name', 'head_id_number',
        'address', 'latitude', 'longitude', 'phone', 'member_count', 'note',
        'organization_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'member_count' => 'integer',
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

    public function residentialArea()
    {
        return $this->belongsTo(ResidentialArea::class);
    }

    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function dependents()
    {
        return $this->hasMany(Dependent::class);
    }

    public function visitSchedules()
    {
        return $this->morphMany(VisitSchedule::class, 'subject');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('head_name', 'like', '%'.$search.'%')
                ->orWhere('household_code', 'like', '%'.$search.'%')))
            ->when($filters['residential_area_id'] ?? null, fn ($q, $id) => $q->where('residential_area_id', $id))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'head_name', 'household_code', 'member_count', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
