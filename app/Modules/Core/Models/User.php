<?php

namespace App\Modules\Core\Models;

use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
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

    /** Transient stash: phone gán qua mass-assign hoặc setter sẽ giữ ở đây giữa saving + saved. */
    protected ?string $pendingPhone = null;

    /** Marker để biết phone có được set hay không (phân biệt null = "unset" vs null = "set null"). */
    protected bool $hasPendingPhone = false;

    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'phone',  // không phải column thật — booted() route sang user_profiles.phone (BC)
        'user_name',
        'password',
        'status',
        'fcm_token',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted()
    {
        static::creating(fn ($user) => $user->created_by = $user->updated_by = auth()->id());
        static::updating(fn ($user) => $user->updated_by = auth()->id());

        // BC routing cho 'phone': mass-assign hoặc $user->phone = '...' không insert vào users
        // mà stash → sau khi save xong, apply vào user_profiles.phone.
        static::saving(function (User $user) {
            if (array_key_exists('phone', $user->attributes)) {
                $user->pendingPhone = $user->attributes['phone'];
                $user->hasPendingPhone = true;
                unset($user->attributes['phone']);
            }
        });
        static::saved(function (User $user) {
            if ($user->hasPendingPhone) {
                UserProfile::firstOrCreate(['user_id' => $user->id])
                    ->update(['phone' => $user->pendingPhone]);
                $user->hasPendingPhone = false;
                $user->pendingPhone = null;
            }
        });
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

    /** Profile cá nhân (phone, gender, birth_date, citizen_id, address...). Auto-create qua UserObserver. */
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    /** BC accessor: code cũ dùng $user->phone vẫn work, đọc qua profile. */
    public function getPhoneAttribute(): ?string
    {
        return $this->profile?->phone;
    }

    /**
     * Lần đăng nhập gần nhất — derive từ personal_access_tokens.created_at max.
     * Mỗi lần Sanctum issue token mới = 1 lần login. Không có token = chưa từng login.
     *
     * Tránh N+1: query nên dùng ->withMax('tokens', 'created_at') để load aggregate
     * trong cùng 1 round-trip (Service có scope `loadLastLogin`).
     */
    public function getLastLoginAtAttribute(): ?\Carbon\Carbon
    {
        // Đã withMax → attribute 'tokens_max_created_at' đã có sẵn
        if (array_key_exists('tokens_max_created_at', $this->attributes)) {
            return $this->attributes['tokens_max_created_at']
                ? \Carbon\Carbon::parse($this->attributes['tokens_max_created_at'])
                : null;
        }

        // Đã eager-load tokens collection
        if ($this->relationLoaded('tokens')) {
            $max = $this->tokens->max('created_at');
            return $max ? \Carbon\Carbon::parse($max) : null;
        }

        // Fallback: 1 query (single user case OK; list không nên rơi vào nhánh này)
        $val = $this->tokens()->max('created_at');
        return $val ? \Carbon\Carbon::parse($val) : null;
    }

    /** Helper scope: query builder eager-load aggregate last_login_at, tránh N+1. */
    public function scopeWithLastLogin($query)
    {
        return $query->withMax('tokens', 'created_at');
    }

    public function socials()
    {
        return $this->hasMany(UserSocial::class);
    }

    public function taskAssignmentUser()
    {
        return $this->hasOne(TaskAssignmentUser::class, 'user_id');
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
            $query->whereHas('taskAssignmentUser', fn ($q) => $q->where('task_assignment_department_id', $deptId));
        })->when(filter_var($filters['in_task_assignment'] ?? false, FILTER_VALIDATE_BOOLEAN), function ($query) {
            $query->whereHas('taskAssignmentUser', fn ($q) => $q->where('status', 'active'));
        })->when($filters['role_id'] ?? null, function ($query, $roleId) {
            $teamId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
            $query->whereExists(function ($sub) use ($roleId, $teamId) {
                $sub->select(\DB::raw(1))
                    ->from('model_has_roles')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', self::class)
                    ->where('model_has_roles.role_id', $roleId);
                if ($teamId) {
                    $sub->where('model_has_roles.organization_id', $teamId);
                }
            });
        })->when($filters['organization_id'] ?? null, function ($query, $orgId) {
            $query->whereExists(function ($sub) use ($orgId) {
                $sub->select(\DB::raw(1))
                    ->from('model_has_roles')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', self::class)
                    ->where('model_has_roles.organization_id', $orgId);
            });
        })->when($filters['sort_by'] ?? 'created_at', function ($query, $sortBy) use ($filters) {
            $allowed = ['id', 'name', 'email', 'user_name', 'created_at'];
            $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
            $query->orderBy($column, $filters['sort_order'] ?? 'desc');
        });
    }
}
