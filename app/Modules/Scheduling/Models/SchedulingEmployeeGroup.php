<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SchedulingEmployeeGroup extends TenantModel
{
    use HasFactory;

    protected $table = 'scheduling_employee_groups';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected static function booted()
    {
        parent::booted();

        static::creating(function (SchedulingEmployeeGroup $model) {
            if (is_null($model->created_by)) {
                $model->created_by = auth()->id();
            }
            if (is_null($model->updated_by)) {
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function (SchedulingEmployeeGroup $model) {
            if (is_null($model->updated_by)) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Many-to-many relationship with SchedulingEmployee.
     */
    public function employees()
    {
        return $this->belongsToMany(
            SchedulingEmployee::class,
            'scheduling_employee_group_members',
            'scheduling_employee_group_id',
            'scheduling_employee_id'
        )->withPivot('id', 'organization_id')->withTimestamps();
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        })
        ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
        ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
        ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
        ->when($filters['sort_by'] ?? 'name', function ($q, $sortBy) use ($filters) {
            $allowed = ['id', 'name', 'status', 'created_at', 'updated_at'];
            $column = in_array($sortBy, $allowed, true) ? $sortBy : 'name';
            \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'asc');
        });
    }
}
