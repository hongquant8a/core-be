<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Support\VietnameseSort;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchedulingEmployeeGroup extends TenantModel
{
    use SoftDeletes;

    protected $table = 'scheduling_employee_groups';

    protected $fillable = [
        'organization_id', 'name', 'description', 'status', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function ($m) {
            if (is_null($m->created_by)) {
                $m->created_by = auth()->id();
            }
            if (is_null($m->updated_by)) {
                $m->updated_by = auth()->id();
            }
        });
        static::updating(function ($m) {
            $m->updated_by = auth()->id();
        });
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Modules\Core\Models\User::class, 'updated_by');
    }

    public function members()
    {
        return $this->belongsToMany(
            SchedulingEmployee::class,
            'scheduling_employee_group_members',
            'scheduling_employee_group_id',
            'scheduling_employee_id'
        );
    }

    public function employees()
    {
        return $this->members();
    }

    public function scopeFilter($query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to_date'] ?? null,   fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['sort_by'] ?? 'sort_order', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'sort_order', 'created_at'];
                $col = in_array($sortBy, $allowed, true) ? $sortBy : 'sort_order';
                VietnameseSort::apply($q, $col, $filters['sort_order'] ?? 'asc');
            });
    }
}
