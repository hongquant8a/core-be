<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaskAssignmentDepartment extends TenantModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\TaskAssignment\Models\TaskAssignmentDepartmentFactory::new();
    }

    protected $table = 'task_assignment_departments';

    protected $fillable = ['name', 'description', 'status', 'sort_order', 'organization_id', 'created_by', 'updated_by'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(fn (TaskAssignmentDepartment $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (TaskAssignmentDepartment $model) => $model->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Các bản ghi thành viên (bảng nối) của phòng ban. */
    public function employeeMemberships()
    {
        return $this->hasMany(TaskAssignmentEmployeeDepartment::class, 'task_assignment_department_id');
    }

    /** Nhân viên thuộc phòng ban, dạng n-n. */
    public function employees()
    {
        return $this->belongsToMany(
            TaskAssignmentEmployee::class,
            'task_assignment_employee_department',
            'task_assignment_department_id',
            'task_assignment_employee_id'
        )->withPivot('is_representative')->withTimestamps();
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q2) => $q2->where('name', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'sort_order', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
