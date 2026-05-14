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

    protected $fillable = ['code', 'name', 'description', 'status', 'sort_order', 'organization_id', 'created_by', 'updated_by'];

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

    public function taskAssignmentUsers()
    {
        return $this->hasMany(TaskAssignmentUser::class, 'task_assignment_department_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q2) => $q2->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'code', 'sort_order', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
