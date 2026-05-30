<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Enums\ModuleTypeEnum;
use App\Modules\Scheduling\Enums\NatureEnum;
use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use App\Modules\Scheduling\Enums\SessionTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Schedule extends TenantModel implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $table = 'schedules';

    protected $fillable = [
        'organization_id',
        'module_type',
        'event_date',
        'start_time',
        'end_time',
        'session',
        'content',
        'host_id',
        'host_priority_weight',
        'location',
        'preparation_unit',
        'participant_count',
        'nature',
        'driver_id',
        'color_code',
        'participants_text',
        'departments_text',
        'sort_order',
        'status',
        'week_number',
        'year',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'module_type' => ModuleTypeEnum::class,
        'session' => SessionTypeEnum::class,
        'nature' => NatureEnum::class,
        'status' => ScheduleStatusEnum::class,
        'event_date' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'host_priority_weight' => 'integer',
        'sort_order' => 'integer',
        'week_number' => 'integer',
        'year' => 'integer',
    ];

    protected static function booted()
    {
        parent::booted(); // Call TenantModel boot which runs bootHasOrganizationScope
 
        static::creating(function (Schedule $model) {
            if (is_null($model->created_by)) {
                $model->created_by = auth()->id();
            }
            if (is_null($model->updated_by)) {
                $model->updated_by = auth()->id();
            }
        });
 
        static::updating(function (Schedule $model) {
            if (is_null($model->updated_by)) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments()
    {
        return $this->hasMany(ScheduleAttachment::class, 'schedule_id')->orderBy('sort_order');
    }

    public function reminders()
    {
        return $this->hasMany(ScheduleReminder::class, 'schedule_id');
    }

    public function recipients()
    {
        return $this->hasMany(ScheduleNotificationRecipient::class, 'schedule_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('schedule-attachments');
    }
}
