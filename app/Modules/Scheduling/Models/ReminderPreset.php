<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReminderPreset extends TenantModel
{
    use HasFactory;

    protected $table = 'reminder_presets';

    protected $fillable = [
        'organization_id',
        'moment',
        'offset_minutes',
        'label',
        'channels',
        'created_by',
    ];

    protected $casts = [
        'offset_minutes' => 'integer',
        'channels' => 'array',
        'organization_id' => 'integer',
    ];

    protected static function booted()
    {
        parent::booted();

        static::creating(function (ReminderPreset $model) {
            $model->created_by = auth()->id();
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where('label', 'like', '%' . $search . '%');
        });
    }
}
