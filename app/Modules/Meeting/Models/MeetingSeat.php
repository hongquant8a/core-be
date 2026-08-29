<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingSeat extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'seat_map_id',
        'meeting_participant_id',
        'zone',
        'is_vip',
        'label',
        'row_index',
        'col_index',
        'pos_x',
        'pos_y',
        'rotation',
        'sort_order',
    ];

    protected $casts = [
        'is_vip' => 'boolean',
        'rotation' => 'float',
    ];

    protected static function booted()
    {
        static::creating(fn (MeetingSeat $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingSeat $model) => $model->updated_by = auth()->id());
    }

    public function seatMap()
    {
        return $this->belongsTo(MeetingSeatMap::class, 'seat_map_id');
    }

    public function participant()
    {
        return $this->belongsTo(MeetingParticipant::class, 'meeting_participant_id');
    }
}
