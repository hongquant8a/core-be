<?php

namespace App\Modules\Core\Models;

use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, Notifiable;

    /** Spatie luôn dùng guard 'web' cho quyền (dùng chung cho cả web và API Sanctum). */
    protected $guard_name = 'web';

    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'user_name',
        'password',
        'status',
        'created_by',
        'updated_by',
        'task_assignment_department_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted()
    {
        static::creating(fn ($user) => $user->created_by = $user->updated_by = auth()->id());
        static::updating(fn ($user) => $user->updated_by = auth()->id());
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Tuỳ chọn 1–1: tổ chức làm việc gần nhất (lưu trong user_preferences). */
    public function preference()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function taskAssignmentDepartment()
    {
        return $this->belongsTo(TaskAssignmentDepartment::class, 'task_assignment_department_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('user_name', 'like', '%'.$search.'%');
            });
        })->when($filters['status'] ?? null, function ($query, $status) {
            $query->where('status', $status);
        })->when($filters['task_assignment_department_id'] ?? null, function ($query, $deptId) {
            $query->where('task_assignment_department_id', $deptId);
        })->when($filters['sort_by'] ?? 'created_at', function ($query, $sortBy) use ($filters) {
            $allowed = ['id', 'name', 'email', 'user_name', 'created_at'];
            $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
            $query->orderBy($column, $filters['sort_order'] ?? 'desc');
        });
    }
}
