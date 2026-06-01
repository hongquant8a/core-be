<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\VietnameseSort;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchedulingEmployee extends TenantModel
{
    use SoftDeletes;

    protected $table = 'scheduling_employees';

    protected $fillable = [
        'organization_id', 'user_id', 'name', 'position_name', 'department',
        'phone', 'email', 'priority_weight', 'status', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'priority_weight' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function ($m) {
            if ($m->user_id && !$m->name) {
                $user = User::find($m->user_id);
                if ($user) {
                    $m->name = $user->name;
                    if (!$m->email) {
                        $m->email = $user->email;
                    }
                    if (!$m->phone) {
                        $m->phone = $user->phone;
                    }
                }
            }
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function groups()
    {
        return $this->belongsToMany(
            SchedulingEmployeeGroup::class,
            'scheduling_employee_group_members',
            'scheduling_employee_id',
            'scheduling_employee_group_id'
        );
    }

    public function scopeFilter($query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', (bool)$filters['status']))
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to_date'] ?? null,   fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['sort_by'] ?? 'sort_order', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'sort_order', 'priority_weight', 'created_at'];
                $col = in_array($sortBy, $allowed, true) ? $sortBy : 'sort_order';
                VietnameseSort::apply($q, $col, $filters['sort_order'] ?? 'asc');
            });
    }
}
