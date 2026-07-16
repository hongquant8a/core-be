<?php

namespace App\Modules\Beneficiary\Resources;

use App\Modules\Beneficiary\Enums\ScheduleStatusEnum;
use App\Modules\Beneficiary\Enums\VisitOccasionEnum;
use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitScheduleResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? [
                'id' => $this->subject->id,
                'name' => $this->subject->full_name ?? $this->subject->head_name ?? null,
            ] : null),
            'occasion' => $this->occasion,
            'occasion_label' => VisitOccasionEnum::tryFrom($this->occasion)?->label() ?? $this->occasion,
            'scheduled_date' => $this->scheduled_date?->format('d/m/Y'),
            'status' => $this->status,
            'status_label' => ScheduleStatusEnum::tryFrom($this->status)?->label() ?? $this->status,
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? $this->formatUserSummary($this->assignedTo) : null),
            'note' => $this->note,
            'evidence' => $this->getMedia('visit_evidence')->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->getUrl(),
            ]),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
