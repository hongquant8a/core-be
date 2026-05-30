<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationGroup extends TenantModel
{
    use HasFactory;

    protected $table = 'notification_groups';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'created_by',
    ];

    protected static function booted()
    {
        parent::booted();

        static::creating(function (NotificationGroup $model) {
            $model->created_by = auth()->id();
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(NotificationGroupMember::class, 'group_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'notification_group_members', 'group_id', 'user_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        });
    }
}
