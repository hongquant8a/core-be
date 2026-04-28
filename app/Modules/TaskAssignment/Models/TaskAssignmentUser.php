<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\User;
use App\Modules\Core\Traits\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;

class TaskAssignmentUser extends Model
{
    use HasOrganizationScope;

    protected $table = 'task_assignment_users';

    protected $fillable = [
        'user_id',
        'task_assignment_department_id',
        'is_primary',
        'is_representative',
        'status',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_representative' => 'boolean',
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

    public function department()
    {
        return $this->belongsTo(TaskAssignmentDepartment::class, 'task_assignment_department_id');
    }
}
