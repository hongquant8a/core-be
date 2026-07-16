<?php

namespace App\Modules\Beneficiary\Models;

use App\Models\Reminder;
use App\Modules\Beneficiary\Enums\ScheduleStatusEnum;
use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Services\Notification\Contracts\Remindable;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class VisitSchedule extends TenantModel implements HasMedia, Remindable
{
    use HasFactory, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('visit_evidence');
    }

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\VisitScheduleFactory::new();
    }

    protected $table = 'beneficiary_visit_schedules';

    protected $fillable = [
        'subject_type', 'subject_id', 'occasion', 'scheduled_date', 'status',
        'assigned_to', 'note', 'organization_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['assigned_to'] ?? null, fn ($q, $id) => $q->where('assigned_to', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('scheduled_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('scheduled_date', '<=', $date))
            ->when($filters['sort_by'] ?? 'scheduled_date', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'scheduled_date', 'status', 'created_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'scheduled_date';
                $q->orderBy($column, $filters['sort_order'] ?? 'asc');
            });
    }

    // -- Bắt đầu implement Remindable --

    public function getReminderDeadline(): ?Carbon
    {
        return $this->scheduled_date ? Carbon::parse($this->scheduled_date)->startOfDay() : null;
    }

    public function getReminderOrganizationId(): int
    {
        return (int) $this->organization_id;
    }

    public function getReminderModuleKey(): string
    {
        return NotificationModuleEnum::Beneficiary->value;
    }

    public function getReminderEventKeys(): array
    {
        return [
            NotificationEventEnum::BeneficiaryVisitReminderBefore->value,
            NotificationEventEnum::BeneficiaryVisitReminderOn->value,
            NotificationEventEnum::BeneficiaryVisitReminderAfter->value,
        ];
    }

    public function getReminderEventKey(?string $moment): string
    {
        return match ($moment) {
            'before' => NotificationEventEnum::BeneficiaryVisitReminderBefore->value,
            'on'     => NotificationEventEnum::BeneficiaryVisitReminderOn->value,
            'after'  => NotificationEventEnum::BeneficiaryVisitReminderAfter->value,
            default  => NotificationEventEnum::BeneficiaryVisitReminderBefore->value,
        };
    }

    public function resolveReminderRecipients(): Collection
    {
        $this->loadMissing('assignedTo');

        return collect([$this->assignedTo])->filter()->unique('id')->values();
    }

    public function resolveGuestReminderRecipients(): Collection
    {
        return collect();
    }

    public function isValidForReminder(): bool
    {
        return $this->status === ScheduleStatusEnum::Pending->value;
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    // -- Kết thúc implement Remindable --
}
