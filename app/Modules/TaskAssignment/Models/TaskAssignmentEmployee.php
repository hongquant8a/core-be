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

    /** Các phòng ban mà nhân viên này thuộc về (bảng nối khoá theo employee_id). */
    public function departmentMemberships()
    {
        return $this->hasMany(TaskAssignmentEmployeeDepartment::class, 'task_assignment_employee_id');
    }

    /** Phòng ban dạng quan hệ n-n, dùng khi chỉ cần danh sách phòng. */
    public function departments()
    {
        return $this->belongsToMany(
            TaskAssignmentDepartment::class,
            'task_assignment_employee_department',
            'task_assignment_employee_id',
            'task_assignment_department_id'
        )->withPivot('is_representative')->withTimestamps();
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
