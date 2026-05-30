<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SchedulingEmployee extends TenantModel
{
    use HasFactory;

    protected $table = 'scheduling_employees';

    protected $fillable = [
        'user_id',
        'status',
        'note',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected static function booted()
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function groups()
    {
        return $this->belongsToMany(
            SchedulingEmployeeGroup::class,
            'scheduling_employee_group_members',
            'scheduling_employee_id',
            'scheduling_employee_group_id'
        )->withPivot('id', 'organization_id')->withTimestamps();
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('user', fn ($u) => $u
            ->where('name', 'like', '%'.$search.'%')
            ->orWhere('email', 'like', '%'.$search.'%')
            ->orWhere('user_name', 'like', '%'.$search.'%')
        ))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'status', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
