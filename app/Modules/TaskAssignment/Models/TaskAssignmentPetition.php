<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TaskAssignmentPetition extends TenantModel implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $table = 'task_assignment_petitions';

    protected $fillable = [
        'department_id',
        'submission_date',
        'deadline_date',
        'sender_name',
        'sender_address',
        'sender_cccd',
        'sender_phone',
        'sender_email',
        'content',
        'processing_status',
        'completed_at',
        'document_number',
        'document_excerpt',
        'response_content',
        'organization_id',
    ];

    protected $casts = [
        'submission_date' => 'date',
        'deadline_date'   => 'date',
        'completed_at'    => 'datetime',
    ];

    protected $appends = [
    ];

    public function department()
    {
        return $this->belongsTo(TaskAssignmentDepartment::class, 'department_id');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAssignmentPetitionAttachment::class, 'petition_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Modules\Core\Models\User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(\App\Modules\Core\Models\User::class, 'updated_by');
    }

    public function isOverdue(): bool
    {
        return $this->deadline_date
            && $this->deadline_date->isPast()
            && ! in_array($this->processing_status, [
                \App\Modules\TaskAssignment\Enums\PetitionStatusEnum::Completed->value,
                \App\Modules\TaskAssignment\Enums\PetitionStatusEnum::Cancelled->value,
            ], true);
    }

    public function timingStatus(): string
    {
        $done      = \App\Modules\TaskAssignment\Enums\PetitionStatusEnum::Completed->value;
        $cancelled = \App\Modules\TaskAssignment\Enums\PetitionStatusEnum::Cancelled->value;

        if ($this->processing_status === $cancelled) {
            return 'cancelled';
        }

        if ($this->processing_status === $done) {
            if (! $this->deadline_date) {
                return 'on_time';
            }
            $completedDate = $this->completed_at?->toDateString();
            $endDate       = $this->deadline_date->toDateString();

            return match (true) {
                $completedDate < $endDate => 'early',
                $completedDate > $endDate => 'late',
                default                   => 'on_time',
            };
        }

        if ($this->isOverdue()) {
            return 'overdue';
        }

        return 'upcoming';
    }


}
