<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Services\Notification\Contracts\Remindable;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * @property bool $is_public
 * @property string $status
 * @property ?int $qr_manager_user_id
 * @property ?int $chairperson_meeting_attendee_id
 * @property ?int $operator_meeting_attendee_id
 * @property-read ?\App\Modules\Meeting\Models\MeetingAttendee $chairperson
 * @property-read ?\App\Modules\Meeting\Models\MeetingAttendee $operator
 */
class Meeting extends TenantModel implements HasMedia, Remindable
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    public const COLLECTION_PROJECTOR = 'meeting-projector';
    public const COLLECTION_WAITING = 'meeting-waiting';

    protected $fillable = [
        'organization_id',
        'meeting_type_id',
        'meeting_location_id',
        'chairperson_meeting_attendee_id',
        'operator_meeting_attendee_id',
        'title',
        'is_public',
        'has_online_room',
        'content',
        'start_time',
        'end_time',
        'attendance_open_at',
        'attendance_close_at',
        'status',
        'view_count',
        'published_at',
        'attendance_locked',
        'current_meeting_agenda_id',
        'current_meeting_discussion_registration_id',
        'qr_manager_user_id',
        'projector_image_media_id',
        'waiting_image_media_id',
        'allow_host_management',
        'created_by',
        'updated_by',
        'checkin_token',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'meeting_type_id' => 'integer',
        'meeting_location_id' => 'integer',
        'is_public' => 'boolean',
        'has_online_room' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'attendance_open_at' => 'datetime',
        'attendance_close_at' => 'datetime',
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'attendance_locked' => 'boolean',
        'current_meeting_agenda_id' => 'integer',
        'current_meeting_discussion_registration_id' => 'integer',
        'allow_host_management' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function (Meeting $meeting) {
            $meeting->created_by = auth()->id();
            $meeting->updated_by = auth()->id();
            // Auto-generate checkin_token UUID nếu chưa set — dùng cho QR điểm danh.
            if (empty($meeting->checkin_token)) {
                $meeting->checkin_token = (string) \Illuminate\Support\Str::uuid();
            }
        });
        static::updating(fn (Meeting $meeting) => $meeting->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function meetingType()
    {
        return $this->belongsTo(MeetingType::class, 'meeting_type_id');
    }

    public function meetingLocation()
    {
        return $this->belongsTo(MeetingLocation::class, 'meeting_location_id');
    }

    public function chairperson()
    {
        return $this->belongsTo(MeetingAttendee::class, 'chairperson_meeting_attendee_id');
    }

    public function operator()
    {
        return $this->belongsTo(MeetingAttendee::class, 'operator_meeting_attendee_id');
    }

    /**
     * User được giao quyền bật QR điểm danh — set qua field qr_manager_user_id.
     * Khi set → user này có quyền showQrCode (theo MeetingPolicy), không qua Spatie.
     */
    public function qrManager()
    {
        return $this->belongsTo(User::class, 'qr_manager_user_id');
    }

    /**
     * Background riêng cho cuộc họp (Tab 8 màn chiếu). Nếu null → FE fallback sang
     * MeetingSetting.projector_image_media_id của tổ chức.
     */
    public function projectorImage()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'projector_image_media_id');
    }

    /**
     * Ảnh chờ chương trình (Tab 8 màn chiếu). Nếu null → FE fallback sang
     * MeetingSetting.waiting_image_media_id của tổ chức.
     */
    public function waitingImage()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'waiting_image_media_id');
    }

    /**
     * Khách mời của cuộc họp (nhập trực tiếp khi admin tạo/sửa meeting).
     * Không có user account, chỉ dùng để gửi thư mời.
     */
    public function guests()
    {
        return $this->hasMany(MeetingGuest::class, 'meeting_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION_PROJECTOR)->singleFile();
        $this->addMediaCollection(self::COLLECTION_WAITING)->singleFile();
    }

    public function participants()
    {
        return $this->hasMany(MeetingParticipant::class, 'meeting_id');
    }

    /**
     * User là chủ trì của meeting? Match qua chairperson_meeting_attendee_id → attendee.user_id.
     */
    public function isChairperson(\App\Modules\Core\Models\User $user): bool
    {
        if ($this->chairperson_meeting_attendee_id === null) {
            return false;
        }

        return $this->chairperson?->user_id === $user->id;
    }

    /**
     * User là thư ký/operator của meeting?
     */
    public function isOperator(\App\Modules\Core\Models\User $user): bool
    {
        if ($this->operator_meeting_attendee_id === null) {
            return false;
        }

        return $this->operator?->user_id === $user->id;
    }

    /**
     * User là đại biểu (participant đã được mời) của meeting? Check qua participant.attendee.user_id.
     */
    public function isParticipant(\App\Modules\Core\Models\User $user): bool
    {
        return $this->participants()
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    /**
     * Trả về vai trò CHÍNH của user trong meeting (single string).
     * Ưu tiên KHÓA NGOẠI chair > FK operator > participant entry.
     * Chair có thể đồng thời có participant entry (đại biểu) — vẫn trả 'chairperson'
     * vì FK cao hơn. FE check `current_user_meeting_role` để show nút điều hành.
     */
    public function userMeetingRole(\App\Modules\Core\Models\User $user): ?string
    {
        if ($this->isChairperson($user)) {
            return 'chairperson';
        }
        if ($this->isOperator($user)) {
            return 'operator';
        }
        if ($this->isParticipant($user)) {
            return 'participant';
        }

        return null;
    }

    /**
     * User hiện tại có quyền bỏ phiếu không.
     * Source of truth là list đại biểu + đã được xác nhận điểm danh (attendance present).
     * Chủ trì chỉ được vote nếu cũng là đại biểu. Thư ký không được vote.
     */
    public function canVote(\App\Modules\Core\Models\User $user): bool
    {
        $participant = $this->participants()
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $user->id))
            ->with('attendance')
            ->first();

        return $participant && $participant->attendance?->status === 'present';
    }

    // -- Bắt đầu implement Remindable --

    public function getReminderDeadline(): ?Carbon
    {
        return $this->start_time;
    }

    public function getReminderOrganizationId(): int
    {
        return (int) $this->organization_id;
    }

    public function getReminderModuleKey(): string
    {
        return NotificationModuleEnum::Meeting->value; // 'meeting'
    }

    public function getReminderEventKeys(): array
    {
        return [
            NotificationEventEnum::MeetingReminderBefore->value,
            NotificationEventEnum::MeetingReminderOn->value,
            NotificationEventEnum::MeetingReminderAfter->value,
        ];
    }

    public function getReminderEventKey(?string $moment): string
    {
        // null = instant (do event publish handle, không qua cron)
        return match ($moment) {
            null    => 'meeting_published',
            'before'=> NotificationEventEnum::MeetingReminderBefore->value,
            'on'    => NotificationEventEnum::MeetingReminderOn->value,
            'after' => NotificationEventEnum::MeetingReminderAfter->value,
            default => "meeting_reminder_{$moment}",
        };
    }

    public function resolveReminderRecipients(): Collection
    {
        $this->loadMissing(['participants.attendee', 'chairperson', 'operator']);

        $userIds = $this->participants
            ->pluck('attendee.user_id')
            ->filter();

        foreach ([$this->chairperson?->user_id, $this->operator?->user_id] as $id) {
            if ($id) $userIds->push($id);
        }

        return User::whereIn('id', $userIds->unique())->get();
    }

    public function resolveGuestReminderRecipients(): Collection
    {
        $this->loadMissing('guests');
        return $this->guests ?? collect();
    }

    public function isValidForReminder(): bool
    {
        return $this->status !== 'cancelled';
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    // -- Kết thúc implement Remindable --

    public function agendas()
    {
        return $this->hasMany(MeetingAgenda::class, 'meeting_id')->orderBy('sort_order');
    }

    public function documents()
    {
        return $this->hasMany(MeetingDocument::class, 'meeting_id')->orderBy('sort_order');
    }

    public function voteTopics()
    {
        return $this->hasMany(MeetingVoteTopic::class, 'meeting_id')->orderBy('sort_order');
    }

    public function currentAgenda()
    {
        return $this->belongsTo(MeetingAgenda::class, 'current_meeting_agenda_id');
    }

    public function currentDiscussionRegistration()
    {
        return $this->belongsTo(MeetingDiscussionRegistration::class, 'current_meeting_discussion_registration_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when(isset($filters['is_public']), fn ($q) => $q->where('is_public', (bool) $filters['is_public']))
            ->when($filters['meeting_type_id'] ?? null, fn ($q, $meetingTypeId) => $q->where('meeting_type_id', $meetingTypeId))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'title', 'start_time', 'status', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
