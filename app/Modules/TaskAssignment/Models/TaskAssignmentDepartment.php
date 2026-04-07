<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\User;
use App\Modules\Core\Traits\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskAssignmentDepartment extends Model
{
    use HasFactory, HasOrganizationScope;

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

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q2) => $q2->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'code', 'sort_order', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                $q->orderBy($column, $filters['sort_order'] ?? 'desc');
            });
    }
}
