<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\VietnameseSort;
use App\Modules\Scheduling\Enums\{ModuleType, SessionType, Nature, ScheduleStatus};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\Notification\Contracts\Remindable;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class Schedule extends TenantModel implements Remindable
{
    use HasFactory, SoftDeletes;

    protected $table = 'schedules';

    protected $fillable = [
        'organization_id', 'module_type', 'date_time', 'session', 'content',
        'host_id', 'host_text', 'location', 'preparation_unit', 'departments_text',
        'participants_text', 'participant_count', 'nature', 'driver_id', 'driver_text',
        'sort_order', 'status', 'approval_status', 'week_number', 'year',
        'approved_by', 'approved_at', 'rejection_note', 'created_by', 'updated_by', 'is_important'
    ];

    protected $casts = [
        'date_time'     => 'datetime',
        'module_type'   => ModuleType::class,
        'session'       => SessionType::class,
        'nature'        => Nature::class,
        'status'        => ScheduleStatus::class,
        'approved_at'   => 'datetime',
        'sort_order'    => 'integer',
        'week_number'   => 'integer',
        'year'          => 'integer',
        'is_important'  => 'boolean'
    ];



    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (Schedule $schedule) {
            // Set auditors
            if (is_null($schedule->created_by)) {
                $schedule->created_by = auth()->id() ?: User::value('id') ?: 1;
            }
            $schedule->updated_by = auth()->id() ?: User::value('id') ?: 1;

            // Auto-calculate year, week_number, and session from date_time
            $dateVal = $schedule->date_time;
            if ($dateVal) {
                $carbon = \Carbon\Carbon::parse($dateVal);
                $schedule->year = $carbon->isoWeekYear;
                $schedule->week_number = $carbon->isoWeek;

                // Auto-calculate session based on the hour,
                // NHƯNG ưu tiên giá trị session do Frontend gửi lên nếu có sự thay đổi rõ ràng.
                if (!$schedule->isDirty('session') || empty($schedule->session)) {
                    $timeString = $carbon->format('H:i');
                    $schedule->session = SessionType::fromTime($timeString);
                }
            }
        });
    }

    // ── Relations ────────────────────────────────────────────────────────────────

    public function creator()    { return $this->belongsTo(User::class, 'created_by'); }
    public function editor()     { return $this->belongsTo(User::class, 'updated_by'); }
    public function host()       { return $this->belongsTo(User::class, 'host_id'); }
    public function driver()     { return $this->belongsTo(User::class, 'driver_id'); }
    public function approver()   { return $this->belongsTo(User::class, 'approved_by'); }

    public function attachments()
    {
        return $this->hasMany(ScheduleAttachment::class, 'schedule_id')->orderBy('sort_order');
    }

    public function recipients()
    {
        return $this->hasMany(ScheduleNotificationRecipient::class, 'schedule_id');
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'schedule_notification_recipients', 'schedule_id', 'user_id')
            ->withPivot('group_id');
    }

    // -- Bắt đầu implement Remindable --

    public function getReminderDeadline(): ?Carbon
    {
        // Dùng date_time trực tiếp — đã là datetime đầy đủ (cast 'datetime').
        // Không override giờ bằng slot buổi: lịch 10:00 sáng, remind before 30' → 09:30, không phải 07:00.
        return $this->date_time ? Carbon::parse($this->date_time) : null;
    }

    public function getReminderOrganizationId(): int
    {
        return (int) $this->organization_id;
    }

    public function getReminderModuleKey(): string
    {
        return NotificationModuleEnum::Scheduling->value; // 'scheduling'
    }

    public function getReminderEventKeys(): array
    {
        return [
            NotificationEventEnum::ScheduleReminderBefore->value,
            NotificationEventEnum::ScheduleReminderOn->value,
            NotificationEventEnum::ScheduleReminderAfter->value,
        ];
    }

    public function getReminderEventKey(?string $moment): string
    {
        return match ($moment) {
            'before' => NotificationEventEnum::ScheduleReminderBefore->value,
            'on'     => NotificationEventEnum::ScheduleReminderOn->value,
            'after'  => NotificationEventEnum::ScheduleReminderAfter->value,
            default  => NotificationEventEnum::ScheduleReminderBefore->value,
        };
    }

    public function resolveReminderRecipients(): Collection
    {
        $this->loadMissing(['recipients.user', 'host', 'driver']);

        $users = $this->recipients->map->user->filter();

        if ($this->host) $users->push($this->host);
        if ($this->driver) $users->push($this->driver);

        return $users->unique('id')->values();
    }

    public function resolveGuestReminderRecipients(): Collection
    {
        return collect();
    }

    public function isValidForReminder(): bool
    {
        $statusVal = $this->status instanceof ScheduleStatus
            ? $this->status->value
            : (int) $this->status;

        return $statusVal === ScheduleStatus::PUBLISHED->value;
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    // -- Kết thúc implement Remindable --

    public function setStatusAttribute($value)
    {
        if (is_string($value) && !is_numeric($value)) {
            $value = strtoupper($value);
            $value = match ($value) {
                'DRAFT' => 0,
                'PUBLISHED' => 1,
                default => 0,
            };
        } elseif ($value instanceof ScheduleStatus) {
            $value = $value->value;
        }
        $this->attributes['status'] = $value;
    }

    public function setSessionAttribute($value)
    {
        if (is_string($value)) {
            $value = strtoupper($value);
            if ($value === 'MORNING') $value = 'S';
            elseif ($value === 'AFTERNOON') $value = 'C';
            elseif ($value === 'EVENING') $value = 'T';
        } elseif ($value instanceof SessionType) {
            $value = $value->value;
        }
        $this->attributes['session'] = $value;
    }

    // ── Scopes ───────────────────────────────────────────────────────────────────

    public function scopeFilter($query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('content', 'like', "%{$s}%")
                        ->orWhere('location', 'like', "%{$s}%")
                        ->orWhere('preparation_unit', 'like', "%{$s}%")
                        ->orWhere('departments_text', 'like', "%{$s}%");
                });
            })
            ->when(isset($filters['module_type']), function ($q) use ($filters) {
                $q->where('module_type', $filters['module_type']);
            })
            ->when(isset($filters['status']), function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            })
            ->when(isset($filters['approval_status']), function ($q) use ($filters) {
                $q->where('approval_status', $filters['approval_status']);
            })
            ->when(isset($filters['session']), function ($q) use ($filters) {
                $q->where('session', $filters['session']);
            })
            ->when($filters['host_id'] ?? null, function ($q, $v) {
                $q->where('host_id', $v);
            })
            ->when($filters['driver_id'] ?? null, function ($q, $v) {
                $q->where('driver_id', $v);
            })
            ->when($filters['date_time'] ?? null, function ($q, $v) {
                $q->whereDate('date_time', $v);
            })
            ->when($filters['from_date'] ?? null, function ($q, $v) {
                $q->whereDate('date_time', '>=', $v);
            })
            ->when($filters['to_date'] ?? null, function ($q, $v) {
                $q->whereDate('date_time', '<=', $v);
            })
            ->when($filters['week'] ?? null, function ($q, $week) {
                if (str_contains($week, '-W')) {
                    [$year, $w] = explode('-W', $week);
                    $q->where('year', (int)$year)->where('week_number', (int)$w);
                }
            })
            ->when(isset($filters['year']), function ($q) use ($filters) {
                $q->where('year', (int)$filters['year']);
            })
            ->when(isset($filters['week_number']), function ($q) use ($filters) {
                $q->where('week_number', (int)$filters['week_number']);
            })
            ->when($filters['view_mode'] ?? null, function ($q, $mode) {
                if ($mode === 'personal') {
                    $userId = auth()->id();
                    $q->where('status', \App\Modules\Scheduling\Enums\ScheduleStatus::PUBLISHED->value)
                      ->where('approval_status', \App\Modules\Scheduling\Enums\ApprovalStatus::APPROVED->value)
                      ->where(function ($sub) use ($userId) {
                          $sub->where('host_id', $userId)
                              ->orWhere('driver_id', $userId)
                              ->orWhereHas('recipients', fn ($p) => $p->where('user_id', $userId));
                      });
                } elseif ($mode === 'managed') {
                    $q->where('host_id', auth()->id());
                }
            })
            ->when($filters['general_visibility'] ?? false, function ($q) use ($filters) {
                // Khi đã có view_mode (personal/managed) thì bỏ qua general_visibility —
                // view_mode tự định nghĩa visibility riêng, không cần lọc thêm status/approval.
                if (! empty($filters['view_mode'])) {
                    return;
                }

                // Với màn hình chung của nhân viên (general view), chỉ xem được lịch PUBLISHED
                // HOẶC lịch DRAFT do chính user hiện tại tạo ra.
                // Lịch chờ duyệt (approval_status=pending) chỉ hiển thị nếu user có quyền duyệt.
                $userId = auth()->id();
                if ($userId) {
                    $q->where(function ($sub) use ($userId) {
                        $sub->where('status', \App\Modules\Scheduling\Enums\ScheduleStatus::PUBLISHED->value)
                            ->orWhere('created_by', $userId);
                    });
                } else {
                    $q->where('status', \App\Modules\Scheduling\Enums\ScheduleStatus::PUBLISHED->value);
                }

                $hasApprovePerm = auth()->user()?->can('scheduling.approve')
                    || auth()->user()?->can('schedules-executive.approve')
                    || auth()->user()?->can('schedules-office.approve');
                if (! $hasApprovePerm) {
                    $q->where(function ($sub) use ($userId) {
                        $sub->where('approval_status', 'approved')
                            ->orWhere('created_by', $userId);
                    });
                }
            })
            ->when(
                ($filters['sort_by'] ?? '') !== '',
                function ($q) use ($filters) {
                    $dateCol = 'date_time';
                    $allowed = ['id', $dateCol, 'session', 'sort_order', 'status', 'created_at', 'updated_at'];
                    $sortBy = $filters['sort_by'] ?? $dateCol;
                    $col = in_array($sortBy, $allowed, true) ? $sortBy : $dateCol;
                    VietnameseSort::apply($q, $col, $filters['sort_order'] ?? 'asc');
                },
                function ($q) use ($filters) {
                    if (! array_key_exists('sort_by', $filters)) {
                        VietnameseSort::apply($q, 'date_time', $filters['sort_order'] ?? 'asc');
                    }
                }
            );
    }
}
