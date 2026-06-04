<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingVoteTopic extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_agenda_id',
        'title',
        'description',
        'duration_minutes',
        'vote_type',
        'ballot_mode',
        'show_result_on_projector',
        'show_result_on_personal_device',
        'sort_order',
        'opened_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'show_result_on_projector' => 'boolean',
        'show_result_on_personal_device' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(fn (MeetingVoteTopic $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingVoteTopic $model) => $model->updated_by = auth()->id());
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    /**
     * Tất cả phiếu thuộc topic này. Dùng cho eager load filter theo auth user_id để
     * trả `my_response` trong resource (đại biểu re-open vote → pre-fill lựa chọn cũ).
     */
    public function userResponses()
    {
        return $this->hasMany(MeetingVoteResponse::class, 'meeting_vote_topic_id');
    }

    /**
     * Phase derive: opened_at NULL → draft; closed_at NOT NULL → closed;
     * opened_at + duration_minutes < now → closed (timeout); else opened.
     */
    public function derivePhase(): string
    {
        if ($this->closed_at !== null) {
            return 'closed';
        }
        if ($this->opened_at === null) {
            return 'draft';
        }
        if ($this->duration_minutes !== null) {
            $expiresAt = $this->opened_at->copy()->addMinutes((int) $this->duration_minutes);
            if ($expiresAt->lte(now())) {
                return 'closed';
            }
        }

        return 'opened';
    }

    /**
     * Thời điểm hết giờ phiên biểu quyết — opened_at + duration_minutes. Null nếu chưa
     * mở hoặc không set duration.
     */
    public function expiresAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->opened_at === null || $this->duration_minutes === null) {
            return null;
        }

        return $this->opened_at->copy()->addMinutes((int) $this->duration_minutes);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['meeting_agenda_id'] ?? null, fn ($q, $agendaId) => $q->where('meeting_agenda_id', $agendaId))
            // Status đã bỏ field — derive từ opened_at + closed_at + duration_minutes (timeout):
            //  draft  = opened_at IS NULL
            //  opened = opened_at IS NOT NULL AND closed_at IS NULL AND chưa hết duration
            //  closed = closed_at IS NOT NULL HOẶC đã hết duration (auto-expired)
            ->when($filters['status'] ?? null, function ($q, $status) {
                $now = now();
                match ($status) {
                    'draft' => $q->whereNull('opened_at'),
                    'opened' => $q->whereNotNull('opened_at')
                        ->whereNull('closed_at')
                        ->where(function ($sub) use ($now) {
                            $sub->whereNull('duration_minutes')
                                ->orWhereRaw('DATE_ADD(opened_at, INTERVAL duration_minutes MINUTE) > ?', [$now]);
                        }),
                    'closed' => $q->where(function ($sub) use ($now) {
                        $sub->whereNotNull('closed_at')
                            ->orWhere(function ($exp) use ($now) {
                                $exp->whereNotNull('opened_at')
                                    ->whereNotNull('duration_minutes')
                                    ->whereRaw('DATE_ADD(opened_at, INTERVAL duration_minutes MINUTE) <= ?', [$now]);
                            });
                    }),
                    default => null,
                };
            })
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($filters['sort_by'] ?? 'sort_order', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'sort_order', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'sort_order';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'asc');
            });
    }
}
