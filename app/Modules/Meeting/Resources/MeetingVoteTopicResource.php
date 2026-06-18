<?php

namespace App\Modules\Meeting\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingVoteTopicResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'meeting_agenda_id' => $this->meeting_agenda_id,
            'title' => $this->title,
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
            'vote_type' => $this->vote_type,
            'ballot_mode' => $this->ballot_mode,
            'show_result_on_projector' => $this->show_result_on_projector,
            'show_result_on_personal_device' => $this->show_result_on_personal_device,
            'is_voting_result_hidden_until_end' => (bool) $this->is_voting_result_hidden_until_end,
            'is_vote_change_allowed' => (bool) $this->is_vote_change_allowed,
            'calculate_by_total_delegates' => (bool) $this->calculate_by_total_delegates,
            'calculate_by_present_delegates' => (bool) $this->calculate_by_present_delegates,
            'sort_order' => $this->sort_order,
            // status đã bỏ — BE compute `phase` derived (kèm timeout) thay cho FE.
            //   draft  = opened_at NULL
            //   opened = opened_at NOT NULL + closed_at NULL + chưa hết duration_minutes
            //   closed = closed_at NOT NULL HOẶC opened_at + duration_minutes <= now (auto-expired)
            'phase' => $this->derivePhase(),
            'opened_at' => $this->opened_at?->format('H:i:s d/m/Y'),
            'closed_at' => $this->closed_at?->format('H:i:s d/m/Y'),
            // ISO 8601 cho FE countdown (anchored to absolute time, không drift theo clock skew).
            'expires_at_iso' => $this->expiresAt()?->toIso8601String(),
            // Pre-fill lựa chọn cũ của auth user (nếu đã vote trước đó hoặc topic re-open).
            // Service phải eager-load userResponses filtered by auth user_id để tránh N+1.
            // Null nếu user chưa vote / không auth.
            'my_response' => $this->whenLoaded('userResponses', function () {
                $mine = $this->userResponses->first();

                return $mine ? [
                    'option' => $mine->option,
                    'voted_at' => $mine->voted_at?->utc()->toIso8601String(),
                ] : null;
            }, null),
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
