<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Models\VisitSchedule;
use App\Modules\Core\Services\MediaService;

class VisitScheduleService
{
    public function __construct(private MediaService $mediaService) {}

    public function index(array $filters, int $limit)
    {
        return VisitSchedule::with(['subject', 'assignedTo'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(VisitSchedule $schedule): VisitSchedule
    {
        return $schedule->load(['subject', 'assignedTo']);
    }

    public function changeStatus(VisitSchedule $schedule, string $status, ?string $note, array $evidenceFiles = []): VisitSchedule
    {
        $schedule->update([
            'status' => $status,
            'note' => $note ?? $schedule->note,
        ]);

        if ($status === 'done' && ! empty($evidenceFiles)) {
            $this->mediaService->uploadMany($schedule, $evidenceFiles, 'visit_evidence');
        }

        return $schedule->load(['subject', 'assignedTo']);
    }
}
