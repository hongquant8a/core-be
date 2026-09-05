<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingSeatMap extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'seat_layout_template_id',
        'layout_type',
        'config',
        'canvas',
    ];

    protected $casts = [
        'config' => 'array',
        'canvas' => 'array',
    ];

    protected static function booted()
    {
        static::creating(fn (MeetingSeatMap $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingSeatMap $model) => $model->updated_by = auth()->id());
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function seats()
    {
        return $this->hasMany(MeetingSeat::class, 'seat_map_id');
    }
}
