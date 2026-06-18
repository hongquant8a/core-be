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
    public function __construct(private MediaService $mediaService) {}

    public function stats(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);
        $base = TaskAssignmentPetition::query();
        $this->applyFilters($base, $filters);

        return [
            'total'      => (clone $base)->count(),
            'new'        => (clone $base)->where('processing_status', PetitionStatusEnum::New->value)->count(),
            'processing' => (clone $base)->where('processing_status', PetitionStatusEnum::Processing->value)->count(),
            'completed'  => (clone $base)->where('processing_status', PetitionStatusEnum::Completed->value)->count(),
            'paused'     => (clone $base)->where('processing_status', PetitionStatusEnum::Paused->value)->count(),
            'cancelled'  => (clone $base)->where('processing_status', PetitionStatusEnum::Cancelled->value)->count(),
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

                if (isset($data['processing_status']) && $data['processing_status'] === PetitionStatusEnum::Completed->value && empty($data['completed_at'])) {
                    $data['completed_at'] = now();
                }

                $petition = TaskAssignmentPetition::create($data);
                $this->uploadAttachments($petition, $files, $storedFiles, 'petition');

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

                if (isset($data['processing_status']) && $data['processing_status'] === PetitionStatusEnum::Completed->value && empty($petition->completed_at)) {
                    $data['completed_at'] = now();
                }

                $petition->update($data);

                if (! empty($removeAttachmentIds)) {
                    $this->removeAttachments($petition, $removeAttachmentIds, 'petition');
                }

                $this->uploadAttachments($petition, $files, $storedFiles, 'petition');

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

    public function updateProgress(TaskAssignmentPetition $petition, array $validated, array $files = [], array $removeAttachmentIds = []): TaskAssignmentPetition
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($petition, $validated, $files, $removeAttachmentIds, &$storedFiles) {
                $data = collect($validated)->except(['attachments', 'remove_attachment_ids'])->all();

                // Tự động set completed_at khi chuyển processing_status
                if (! empty($data['processing_status'])) {
                    if ($data['processing_status'] === PetitionStatusEnum::Completed->value) {
                        $data['completed_at'] = $data['completed_at'] ?? now();
                    } elseif ($data['processing_status'] !== PetitionStatusEnum::Completed->value
                        && $petition->processing_status === PetitionStatusEnum::Completed->value) {
                        $data['completed_at'] = null;
                    }
                }

                $petition->update($data);

                if (! empty($removeAttachmentIds)) {
                    $this->removeAttachments($petition, $removeAttachmentIds);
                }

                $this->uploadAttachments($petition, $files, $storedFiles, 'progress');

                return $petition->load(['department', 'attachments.media', 'creator', 'editor']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function export(array $filters): BinaryFileResponse
    {
        $filters = $this->applyDepartmentRestriction($filters);

        return Excel::download(new PetitionsExport($filters), ExportFilename::make('don-thu'));
    }

    public function changeStatus(TaskAssignmentPetition $petition, string $status): TaskAssignmentPetition
    {
        $data = ['processing_status' => $status];

        if ($status === PetitionStatusEnum::Completed->value) {
            $data['completed_at'] = now();
        } elseif ($petition->processing_status === PetitionStatusEnum::Completed->value) {
            $data['completed_at'] = null;
        }

        $petition->update($data);

        return $petition->load(['department', 'attachments.media', 'creator', 'editor']);
    }

    public function unlock(TaskAssignmentPetition $petition): TaskAssignmentPetition
    {
        // Sử dụng lại logic changeStatus, chuyển về Đang xử lý
        return $this->changeStatus($petition, PetitionStatusEnum::Processing->value);
    }

    private function getUserDepartmentIds(): array
    {
        return auth()->user()->taskAssignmentUsers()
            ->where('status', 'active')
            ->pluck('task_assignment_department_id')
            ->toArray();
    }

    public function getAvailableDepartments()
    {
        $deptIds = $this->getUserDepartmentIds();

        return \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::whereIn('id', $deptIds)
            ->where('status', 'active')
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    private function applyDepartmentRestriction(array $filters): array
    {
        $userDeptIds = $this->getUserDepartmentIds();

        if (empty($userDeptIds)) {
            $filters['department_id'] = 0;
            return $filters;
        }

        if (isset($filters['department_id'])) {
            $requestedDeptId = (int) $filters['department_id'];
            if (in_array($requestedDeptId, $userDeptIds, true)) {
                return $filters;
            }
            $filters['department_id'] = 0;
            return $filters;
        }

        $hasOverview = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::whereIn('id', $userDeptIds)
            ->where('is_petition_overview', true)
            ->exists();

        if ($hasOverview) {
            return $filters;
        }

        if (count($userDeptIds) === 1) {
            $filters['department_id'] = $userDeptIds[0];
        } else {
            $filters['department_ids'] = $userDeptIds;
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
            ->when($filters['department_ids'] ?? null, fn ($q, $v) => $q->whereIn('department_id', $v))
            ->when($filters['submission_date_from'] ?? null, fn ($q, $v) => $q->whereDate('submission_date', '>=', $v))
            ->when($filters['submission_date_to'] ?? null, fn ($q, $v) => $q->whereDate('submission_date', '<=', $v))
            ->when($filters['deadline_date_from'] ?? null, fn ($q, $v) => $q->whereDate('deadline_date', '>=', $v))
            ->when($filters['deadline_date_to'] ?? null, fn ($q, $v) => $q->whereDate('deadline_date', '<=', $v))
            ->when($filters['timing_status'] ?? null, function ($q, $timing) {
                $done = PetitionStatusEnum::Completed->value;
                $cancelled = PetitionStatusEnum::Cancelled->value;

                if ($timing === 'upcoming') {
                    $q->whereNotIn('processing_status', [$done, $cancelled])
                        ->where(fn ($sub) => $sub->whereNull('deadline_date')
                            ->orWhereDate('deadline_date', '>=', today())
                        );
                } elseif ($timing === 'overdue') {
                    $q->whereNotIn('processing_status', [$done, $cancelled])
                        ->whereNotNull('deadline_date')
                        ->whereDate('deadline_date', '<', today());
                } elseif ($timing === 'late') {
                    $q->where('processing_status', $done)
                        ->whereNotNull('deadline_date')
                        ->whereRaw('DATE(completed_at) > DATE(deadline_date)');
                } elseif ($timing === 'early') {
                    $q->where('processing_status', $done)
                        ->whereNotNull('deadline_date')
                        ->whereRaw('DATE(completed_at) < DATE(deadline_date)');
                } elseif ($timing === 'on_time') {
                    $q->where('processing_status', $done)
                        ->where(fn ($sub) => $sub->whereNull('deadline_date')
                            ->orWhereRaw('DATE(completed_at) = DATE(deadline_date)')
                        );
                } elseif ($timing === 'cancelled') {
                    $q->where('processing_status', $cancelled);
                }
            })
            ->when($filters['sort_by'] ?? null, function ($q, $v) use ($filters) {
                $allowed = ['id', 'submission_date', 'deadline_date', 'created_at', 'updated_at'];
                if (in_array($v, $allowed, true)) {
                    $q->orderBy($v, $filters['sort_order'] ?? 'desc');
                }
            });
    }

    private function uploadAttachments(TaskAssignmentPetition $petition, array $files, array &$storedFiles, string $type = 'petition'): void
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
                'type' => $type,
                'sort_order' => 0,
            ]);
        }
    }

    private function removeAttachments(TaskAssignmentPetition $petition, array $attachmentIds, ?string $type = null): void
    {
        $query = TaskAssignmentPetitionAttachment::where('petition_id', $petition->id)
            ->whereIn('id', $attachmentIds);

        if ($type !== null) {
            $query->where('type', $type);
        }

        $attachments = $query->get();

        foreach ($attachments as $attachment) {
            if ($attachment->media) {
                $attachment->media->delete();
            }
            $attachment->delete();
        }
    }
}
