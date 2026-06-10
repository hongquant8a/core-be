<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;
use App\Modules\TaskAssignment\Exports\PetitionsExport;
use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use App\Modules\TaskAssignment\Models\TaskAssignmentPetitionAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaskAssignmentPetitionService
{
    private const ADMIN_ROLES = ['Quản trị', 'Super Admin', 'Admin'];

    public function __construct(private MediaService $mediaService) {}

    public function stats(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);
        $base = TaskAssignmentPetition::query();

        return [
            'total' => (clone $base)->count(),
            'new'        => (clone $base)->where('processing_status', PetitionStatusEnum::MoiTiepNhan->value)->count(),
            'processing' => (clone $base)->where('processing_status', PetitionStatusEnum::DangXuLy->value)->count(),
            'completed'  => (clone $base)->where('processing_status', PetitionStatusEnum::DaHoanThanh->value)->count(),
            'paused'     => (clone $base)->where('processing_status', PetitionStatusEnum::TamDung->value)->count(),
            'cancelled'  => (clone $base)->where('processing_status', PetitionStatusEnum::DaHuy->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        $filters = $this->applyDepartmentRestriction($filters);
        $query = TaskAssignmentPetition::with(['department', 'attachments.media', 'creator', 'editor'])
            ->orderByDesc('id');

        $this->applyFilters($query, $filters);

        return $query->paginate($limit);
    }

    public function show(TaskAssignmentPetition $petition): TaskAssignmentPetition
    {
        return $petition->load(['department', 'attachments.media', 'creator', 'editor']);
    }

    public function store(array $validated, array $files = []): TaskAssignmentPetition
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($validated, $files, &$storedFiles) {
                $data = collect($validated)->except(['attachments'])->all();

                if (isset($data['processing_status']) && $data['processing_status'] === PetitionStatusEnum::DaHoanThanh->value && empty($data['completed_at'])) {
                    $data['completed_at'] = now();
                }

                $petition = TaskAssignmentPetition::create($data);
                $this->uploadAttachments($petition, $files, $storedFiles);

                return $petition->load(['department', 'attachments.media', 'creator', 'editor']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(TaskAssignmentPetition $petition, array $validated, array $files = [], array $removeAttachmentIds = []): TaskAssignmentPetition
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($petition, $validated, $files, $removeAttachmentIds, &$storedFiles) {
                $data = collect($validated)->except(['attachments', 'remove_attachment_ids'])->all();

                if (isset($data['processing_status']) && $data['processing_status'] === PetitionStatusEnum::DaHoanThanh->value && empty($petition->completed_at)) {
                    $data['completed_at'] = now();
                }

                $petition->update($data);

                if (! empty($removeAttachmentIds)) {
                    $this->removeAttachments($petition, $removeAttachmentIds);
                }

                $this->uploadAttachments($petition, $files, $storedFiles);

                return $petition->load(['department', 'attachments.media', 'creator', 'editor']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(TaskAssignmentPetition $petition): void
    {
        $petition->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        TaskAssignmentPetition::where('organization_id', getPermissionsTeamId())->whereIn('id', $ids)->delete();
    }

    public function export(array $filters): BinaryFileResponse
    {
        $filters = $this->applyDepartmentRestriction($filters);

        return Excel::download(new PetitionsExport($filters), ExportFilename::make('don-thu'));
    }

    public function changeStatus(TaskAssignmentPetition $petition, string $status): TaskAssignmentPetition
    {
        $data = ['processing_status' => $status];

        if ($status === PetitionStatusEnum::DaHoanThanh->value) {
            $data['completed_at'] = now();
        } elseif ($petition->processing_status === PetitionStatusEnum::DaHoanThanh->value) {
            $data['completed_at'] = null;
        }

        $petition->update($data);

        return $petition->load(['department', 'attachments.media', 'creator', 'editor']);
    }

    private function applyDepartmentRestriction(array $filters): array
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $taskAssignmentUser = $user->taskAssignmentUser;
            $filters['department_id'] = $taskAssignmentUser?->task_assignment_department_id;
        }

        return $filters;
    }

    private function applyFilters($query, array $filters): void
    {
        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q2) use ($v) {
            $q2->where('sender_name', 'like', "%{$v}%")
                ->orWhere('sender_cccd', 'like', "%{$v}%")
                ->orWhere('sender_phone', 'like', "%{$v}%")
                ->orWhere('sender_email', 'like', "%{$v}%")
                ->orWhere('content', 'like', "%{$v}%");
        }))
            ->when($filters['processing_status'] ?? null, fn ($q, $v) => $q->where('processing_status', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['submission_date_from'] ?? null, fn ($q, $v) => $q->whereDate('submission_date', '>=', $v))
            ->when($filters['submission_date_to'] ?? null, fn ($q, $v) => $q->whereDate('submission_date', '<=', $v))
            ->when($filters['deadline_date_from'] ?? null, fn ($q, $v) => $q->whereDate('deadline_date', '>=', $v))
            ->when($filters['deadline_date_to'] ?? null, fn ($q, $v) => $q->whereDate('deadline_date', '<=', $v))
            ->when($filters['sort_by'] ?? null, function ($q, $v) use ($filters) {
                $allowed = ['id', 'submission_date', 'deadline_date', 'created_at', 'updated_at'];
                if (in_array($v, $allowed, true)) {
                    $q->orderBy($v, $filters['sort_order'] ?? 'desc');
                }
            });
    }

    private function uploadAttachments(TaskAssignmentPetition $petition, array $files, array &$storedFiles): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $media = $this->mediaService->uploadOne($petition, $file, 'petition-attachments', ['disk' => 'public']);

            $storedFiles[] = [
                'disk' => $media->disk,
                'path' => $media->getPathRelativeToRoot(),
            ];

            TaskAssignmentPetitionAttachment::create([
                'petition_id' => $petition->id,
                'media_id' => $media->id,
                'file_name' => $file->getClientOriginalName(),
                'sort_order' => 0,
            ]);
        }
    }

    private function removeAttachments(TaskAssignmentPetition $petition, array $attachmentIds): void
    {
        $attachments = TaskAssignmentPetitionAttachment::where('petition_id', $petition->id)
            ->whereIn('id', $attachmentIds)
            ->get();

        foreach ($attachments as $attachment) {
            if ($attachment->media) {
                $attachment->media->delete();
            }
            $attachment->delete();
        }
    }
}
