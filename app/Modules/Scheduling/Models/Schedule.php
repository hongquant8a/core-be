<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\VietnameseSort;
use App\Modules\Scheduling\Enums\{ScheduleStatusEnum, ScheduleSessionEnum, ScheduleModuleTypeEnum};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Schedule extends TenantModel implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    public const COLLECTION_ATTACHMENTS = 'schedule-attachments';

    protected $table = 'schedules';

    protected $fillable = [
        'organization_id', 'module_type', 'title', 'content', 'location',
        'session', 'date', 'start_time', 'end_time',
        'host_user_id', 'driver_user_id', 'preparation_location',
        'status', 'approved_by', 'approved_at', 'rejection_note',
        'sort_order', 'is_recurring', 'recurrence_rule', 'parent_schedule_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'date'            => 'date',
        'approved_at'     => 'datetime',
        'is_recurring'    => 'boolean',
        'recurrence_rule' => 'array',
        'sort_order'      => 'integer',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Schedule $schedule) {
            if (is_null($schedule->created_by)) {
                $schedule->created_by = auth()->id();
            }
            if (is_null($schedule->updated_by)) {
                $schedule->updated_by = auth()->id();
            }
        });
        static::updating(function (Schedule $schedule) {
            $schedule->updated_by = auth()->id();
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION_ATTACHMENTS);
    }

    // ── Relations ────────────────────────────────────────────────────────────────

    public function creator()    { return $this->belongsTo(User::class, 'created_by'); }
    public function editor()     { return $this->belongsTo(User::class, 'updated_by'); }
    public function host()       { return $this->belongsTo(User::class, 'host_user_id'); }
    public function driver()     { return $this->belongsTo(User::class, 'driver_user_id'); }
    public function approver()   { return $this->belongsTo(User::class, 'approved_by'); }
    public function parent()     { return $this->belongsTo(Schedule::class, 'parent_schedule_id'); }

    public function participants()
    {
        return $this->hasMany(ScheduleParticipant::class, 'schedule_id')->orderBy('sort_order');
    }

    public function reminders()
    {
        return $this->hasMany(ScheduleReminder::class, 'schedule_id');
    }

    public function recipients()
    {
        return $this->participants();
    }

    public function getEventDateAttribute()
    {
        return $this->date;
    }

    // ── Scopes ───────────────────────────────────────────────────────────────────

    public function scopeFilter($query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null,
                fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($filters['module_type'] ?? null,
                fn ($q, $v) => $q->where('module_type', $v))
            ->when($filters['status'] ?? null,
                fn ($q, $v) => $q->where('status', $v))
            ->when($filters['session'] ?? null,
                fn ($q, $v) => $q->where('session', $v))
            ->when($filters['host_user_id'] ?? null,
                fn ($q, $v) => $q->where('host_user_id', $v))
            ->when($filters['driver_user_id'] ?? null,
                fn ($q, $v) => $q->where('driver_user_id', $v))
            ->when($filters['date'] ?? null,
                fn ($q, $v) => $q->whereDate('date', $v))
            ->when($filters['from_date'] ?? null,
                fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to_date'] ?? null,
                fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['week'] ?? null, function ($q, $week) {
                // format: "2026-W22" → lọc theo tuần ISO
                [$year, $w] = explode('-W', $week);
                $start = now()->setISODate((int)$year, (int)$w)->startOfWeek()->toDateString();
                $end   = now()->setISODate((int)$year, (int)$w)->endOfWeek()->toDateString();
                $q->whereBetween('date', [$start, $end]);
            })
            ->when($filters['view_mode'] ?? null, function ($q, $mode) {
                // personal = chỉ lịch user tạo hoặc tham dự
                // org      = toàn bộ org (mặc định)
                // managed  = lịch user là host
                if ($mode === 'personal') {
                    $userId = auth()->id();
                    $q->where(function ($sub) use ($userId) {
                        $sub->where('created_by', $userId)
                            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $userId));
                    });
                } elseif ($mode === 'managed') {
                    $q->where('host_user_id', auth()->id());
                }
            })
            ->when(
                $filters['sort_by'] ?? 'date',
                function ($q, $sortBy) use ($filters) {
                    $allowed = ['id', 'date', 'session', 'sort_order', 'status', 'created_at', 'updated_at'];
                    $col = in_array($sortBy, $allowed, true) ? $sortBy : 'date';
                    VietnameseSort::apply($q, $col, $filters['sort_order'] ?? 'asc');
                }
            );
    }
}
