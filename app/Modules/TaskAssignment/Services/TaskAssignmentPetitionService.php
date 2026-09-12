<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;
use App\Modules\TaskAssignment\Exports\PetitionsExport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;
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
            'total' => (clone $base)->count(),
            'new' => (clone $base)->where('processing_status', PetitionStatusEnum::New->value)->count(),
            'processing' => (clone $base)->where('processing_status', PetitionStatusEnum::Processing->value)->count(),
            'completed' => (clone $base)->where('processing_status', PetitionStatusEnum::Completed->value)->count(),
            'paused' => (clone $base)->where('processing_status', PetitionStatusEnum::Paused->value)->count(),
            'cancelled' => (clone $base)->where('processing_status', PetitionStatusEnum::Cancelled->value)->count(),
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

                // Phân hệ đơn thư trước đây không có sự kiện thông báo nào: đơn
                // mới về mà phòng ban tiếp nhận không ai biết.
                event(new \App\Services\Notification\Events\PetitionCreated($petition));

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

    /**
     * Lọc danh sách id qua ĐÚNG policy của thao tác đơn lẻ.
     *
     * Policy hàng loạt nhận tên lớp chứ không nhận bản ghi nên không soi được
     * từng dòng. Trước đây service tự chép lại luật (phạm vi phòng ban + khoá đơn
     * đã hoàn thành), tức hai bản luật sống hai nơi và sẽ trôi khỏi nhau. Nay gọi
     * lại chính policy đơn lẻ — `inScope()` và `isCompleted()` chỉ còn một bản.
     *
     * Hoặc làm hết hoặc không làm gì, giống bên công việc: bỏ qua âm thầm rồi báo
     * "đã xoá N đơn" khiến người dùng tưởng xong việc.
     *
     * @return \Illuminate\Support\Collection<int, TaskAssignmentPetition>
     */
    private function pullAuthorized(array $ids, string $ability, string $refusal)
    {
        $petitions = TaskAssignmentPetition::whereIn('id', $ids)->get();

        $user = auth()->user();
        [$allowed, $denied] = $petitions->partition(fn (TaskAssignmentPetition $p) => (bool) $user?->can($ability, $p));

        if ($denied->isNotEmpty()) {
            throw new \RuntimeException(sprintf(
                '%s — %d/%d đơn được chọn không đạt điều kiện. Bỏ chọn những dòng đó rồi thử lại.',
                $refusal, $denied->count(), count($ids)
            ));
        }

        return $allowed;
    }

    public function bulkDestroy(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $allowed = $this->pullAuthorized(
            $ids,
            'delete',
            'Đơn đã hoàn thành bị khóa, và chỉ thao tác được trên đơn thuộc phòng ban của bạn'
        );

        $allowed->each->delete();

        return $allowed->count();
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (empty($ids)) {
            return 0;
        }

        $allowed = $this->pullAuthorized(
            $ids,
            'changeStatus',
            'Đơn đã hoàn thành bị khóa, và chỉ thao tác được trên đơn thuộc phòng ban của bạn'
        );

        $data = ['processing_status' => $status];
        if ($status === PetitionStatusEnum::Completed->value) {
            $data['completed_at'] = now();
        }

        $allowed->each(fn (TaskAssignmentPetition $p) => $p->update($data));

        return $allowed->count();
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
        $previousStatus = $petition->processing_status;
        $data = ['processing_status' => $status];

        if ($status === PetitionStatusEnum::Completed->value) {
            $data['completed_at'] = now();
        } elseif ($petition->processing_status === PetitionStatusEnum::Completed->value) {
            $data['completed_at'] = null;
        }

        $petition->update($data);

        if ($previousStatus !== $status) {
            event(new \App\Services\Notification\Events\PetitionStatusChanged($petition->fresh(), $previousStatus));
        }

        return $petition->load(['department', 'attachments.media', 'creator', 'editor']);
    }

    public function unlock(TaskAssignmentPetition $petition): TaskAssignmentPetition
    {
        // Sử dụng lại logic changeStatus, chuyển về Đang xử lý
        return $this->changeStatus($petition, PetitionStatusEnum::Processing->value);
    }

    private function getUserDepartmentIds(): array
    {
        return TaskAssignmentEmployeeDepartment::forUser(auth()->id())
            ->activeEmployee()
            ->pluck('task_assignment_department_id')
            ->toArray();
    }

    // Đã bỏ `scopedQuery()`: nó là bản chép lại của `inScope()` trong policy, dựng
    // riêng cho thao tác hàng loạt. Nay hàng loạt gọi thẳng policy từng dòng nên
    // không còn ai gọi tới, và phạm vi phòng ban chỉ còn một bản luật duy nhất.

    /** Có được xem/thao tác đơn thư của mọi phòng ban không. */
    private function canViewAll(): bool
    {
        return auth()->user()?->can('task-assignment-petitions.viewAll') ?? false;
    }

    /** Phòng ban được phép chọn khi lập đơn: tất cả nếu có `viewAll`, không thì phòng của mình. */
    public function getAvailableDepartments()
    {
        $query = TaskAssignmentDepartment::where('status', 'active')
            ->select(['id', 'name'])
            ->orderBy('name');

        if (! $this->canViewAll()) {
            $query->whereIn('id', $this->getUserDepartmentIds());
        }

        return $query->get();
    }

    /**
     * Giới hạn dữ liệu theo phòng ban.
     *
     * Có `task-assignment-petitions.viewAll` → không giới hạn. Không có → chỉ đơn
     * thuộc phòng ban mình là thành viên; không thuộc phòng nào thì không thấy gì
     * (department_id = 0 để không khớp bản ghi nào).
     */
    private function applyDepartmentRestriction(array $filters): array
    {
        if ($this->canViewAll()) {
            return $filters;
        }

        $userDeptIds = $this->getUserDepartmentIds();

        if (empty($userDeptIds)) {
            $filters['department_id'] = 0;

            return $filters;
        }

        // Người dùng tự lọc theo một phòng cụ thể — chỉ cho nếu phòng đó thuộc của họ.
        if (isset($filters['department_id'])) {
            $filters['department_id'] = in_array((int) $filters['department_id'], $userDeptIds, true)
                ? (int) $filters['department_id']
                : 0;

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
