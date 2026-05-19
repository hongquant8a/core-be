<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaskAssignmentEmployee extends TenantModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\TaskAssignment\Models\TaskAssignmentEmployeeFactory::new();
    }

    protected $table = 'task_assignment_employees';

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

    /**
     * Các bản ghi dept của nhân viên này trong module Task — join qua user_id (không qua employee_id để giữ BC).
     *
     * `TaskAssignmentUser` extends `TenantModel` nên global scope tự lọc `organization_id = team_id` khi
     * có ngữ cảnh tổ chức. Không cần whereColumn cross-table (gây lỗi SQL khi eager load).
     */
    public function departmentMemberships()
    {
        return $this->hasMany(TaskAssignmentUser::class, 'user_id', 'user_id');
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
            ->when($filters['department_id'] ?? null, fn ($q, $deptId) => $q->whereHas('departmentMemberships', fn ($m) => $m
                ->where('task_assignment_department_id', $deptId)
            ))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'status', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
