<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\MediaService;
use App\Modules\Scheduling\Enums\ModuleTypeEnum;
use App\Modules\Scheduling\Enums\NatureEnum;
use App\Modules\Scheduling\Enums\ReminderSourceEnum;
use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use App\Modules\Scheduling\Enums\SessionTypeEnum;
use App\Modules\Scheduling\Models\OrgSchedulingSettings;
use App\Modules\Scheduling\Models\Schedule;
use App\Services\Notification\Events\SchedulePublished;
use App\Services\Notification\Events\ScheduleUpdated;
use App\Services\Notification\Events\ScheduleCancelled;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class ScheduleService
{
    public function __construct(
        protected ScheduleFilterService $filterService,
        protected MediaService $mediaService
    ) {}

    /**
     * Get paginated schedules.
     */
    public function index(array $filters, int $limit)
    {
        $query = Schedule::with(['host', 'driver', 'creator', 'editor', 'attachments.mediaFile']);
        $this->filterService->filter($query, $filters);

        return $query->paginate($limit);
    }

    /**
     * Get schedules for weekly matrix (no pagination).
     */
    public function weeklyMatrix(array $filters)
    {
        $query = Schedule::with(['host', 'driver', 'creator', 'editor', 'attachments.mediaFile']);
        
        // Default to current week if no date context is provided
        if (empty($filters['week_number']) && empty($filters['start_date']) && empty($filters['event_date'])) {
            $filters['week_number'] = (int) date('W');
            $filters['year'] = (int) date('Y');
        }

        $this->filterService->filter($query, $filters);

        return $query->get();
    }

    /**
     * Show schedule details.
     */
    public function show(Schedule $schedule): Schedule
    {
        return $schedule->load([
            'host',
            'driver',
            'creator',
            'editor',
            'attachments.mediaFile',
            'reminders.preset',
            'recipients.user',
            'recipients.group'
        ]);
    }

    /**
     * Store a new schedule.
     */
    public function store(array $validated, array $files = []): Schedule
    {
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($validated, $files, &$storedFiles) {
                $orgId = $this->resolveCurrentOrganizationId();

                // Compute dependent fields
                $carbonDate = Carbon::parse($validated['event_date']);
                $validated['week_number'] = $carbonDate->weekOfYear;
                $validated['year'] = $carbonDate->year;

                if (!isset($validated['session']) && !empty($validated['start_time'])) {
                    $validated['session'] = SessionTypeEnum::fromTime($validated['start_time']);
                }

                // Denormalize host priority weight
                $host = User::findOrFail($validated['host_id']);
                $validated['host_priority_weight'] = $host->priority_weight ?? 0;

                // Handle approval configuration
                $orgSettings = OrgSchedulingSettings::where('organization_id', $orgId)->first();
                $approvalRequired = false;
                
                $moduleType = is_string($validated['module_type']) 
                    ? $validated['module_type'] 
                    : $validated['module_type']->value;

                if ($moduleType === ModuleTypeEnum::Executive->value) {
                    $approvalRequired = $orgSettings?->executive_approval_required ?? false;
                } else {
                    $approvalRequired = $orgSettings?->office_approval_required ?? false;
                }

                $reqStatus = isset($validated['status']) 
                    ? (is_int($validated['status']) ? $validated['status'] : $validated['status']->value)
                    : ScheduleStatusEnum::Draft->value;

                if ($reqStatus === ScheduleStatusEnum::Published->value) {
                    if ($approvalRequired) {
                        $validated['status'] = ScheduleStatusEnum::Pending->value;
                    } else {
                        $validated['status'] = ScheduleStatusEnum::Published->value;
                        $validated['approved_by'] = auth()->id();
                        $validated['approved_at'] = now();
                    }
                } else {
                    $validated['status'] = $reqStatus;
                }

                $validated['organization_id'] = $orgId;

                // Create schedule
                $schedule = Schedule::create($validated);

                // Handle attachments
                if (!empty($files)) {
                    $sortOrder = 0;
                    foreach ($files as $file) {
                        if ($file instanceof UploadedFile && $file->isValid()) {
                            $media = $this->mediaService->uploadOne($schedule, $file, 'schedule-attachments', ['disk' => 'public']);
                            $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];

                            $schedule->attachments()->create([
                                'organization_id' => $orgId,
                                'media_id' => $media->id,
                                'file_name' => $file->getClientOriginalName(),
                                'sort_order' => $sortOrder++,
                            ]);
                        }
                    }
                }

                // Handle recipients
                if (!empty($validated['recipients'])) {
                    foreach ($validated['recipients'] as $rec) {
                        $schedule->recipients()->create([
                            'user_id' => $rec['user_id'] ?? null,
                            'group_id' => $rec['group_id'] ?? null,
                        ]);
                    }
                }

                // Handle reminders
                if (!empty($validated['reminders'])) {
                    foreach ($validated['reminders'] as $rem) {
                        $schedule->reminders()->create([
                            'minutes_before' => $rem['minutes_before'],
                            'channels' => $rem['channels'],
                            'source' => $rem['source'] ?? ReminderSourceEnum::Custom->value,
                            'preset_id' => $rem['preset_id'] ?? null,
                        ]);
                    }
                }

                if ($schedule->status->value === ScheduleStatusEnum::Published->value) {
                    Event::dispatch(new SchedulePublished($schedule));
                }

                return $schedule;
            });
        } catch (\Throwable $e) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $e;
        }
    }

    /**
     * Update an existing schedule.
     */
    public function update(Schedule $schedule, array $validated, array $newFiles = []): Schedule
    {
        $originalStatusVal = $schedule->status->value;
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($schedule, $validated, $newFiles, &$storedFiles, $originalStatusVal) {
                $orgId = $this->resolveCurrentOrganizationId();

                // Compute dependent fields if event_date changed
                if (!empty($validated['event_date'])) {
                    $carbonDate = Carbon::parse($validated['event_date']);
                    $validated['week_number'] = $carbonDate->weekOfYear;
                    $validated['year'] = $carbonDate->year;
                }

                if (!empty($validated['start_time'])) {
                    $validated['session'] = SessionTypeEnum::fromTime($validated['start_time']);
                }

                // Update denormalized priority if host_id changed
                if (!empty($validated['host_id'])) {
                    $host = User::findOrFail($validated['host_id']);
                    $validated['host_priority_weight'] = $host->priority_weight ?? 0;
                }

                // Handle approval configuration if status is updated to Published
                if (isset($validated['status'])) {
                    $orgSettings = OrgSchedulingSettings::where('organization_id', $orgId)->first();
                    $approvalRequired = false;

                    $moduleType = !empty($validated['module_type']) 
                        ? (is_string($validated['module_type']) ? $validated['module_type'] : $validated['module_type']->value)
                        : $schedule->module_type->value;

                    if ($moduleType === ModuleTypeEnum::Executive->value) {
                        $approvalRequired = $orgSettings?->executive_approval_required ?? false;
                    } else {
                        $approvalRequired = $orgSettings?->office_approval_required ?? false;
                    }

                    $reqStatus = is_int($validated['status']) ? $validated['status'] : $validated['status']->value;

                    if ($reqStatus === ScheduleStatusEnum::Published->value && $schedule->status->value !== ScheduleStatusEnum::Published->value) {
                        if ($approvalRequired) {
                            $validated['status'] = ScheduleStatusEnum::Pending->value;
                        } else {
                            $validated['status'] = ScheduleStatusEnum::Published->value;
                            $validated['approved_by'] = auth()->id();
                            $validated['approved_at'] = now();
                        }
                    } else {
                        $validated['status'] = $reqStatus;
                    }
                }

                // Update schedule details
                $schedule->update($validated);

                // Handle deleted attachments
                if (!empty($validated['delete_attachments'])) {
                    $attachmentsToDelete = $schedule->attachments()->whereIn('id', $validated['delete_attachments'])->get();
                    foreach ($attachmentsToDelete as $att) {
                        $this->mediaService->removeByIds($schedule, [$att->media_id], 'schedule-attachments');
                        $att->delete();
                    }
                }

                // Handle new attachments
                if (!empty($newFiles)) {
                    $nextSort = ((int) $schedule->attachments()->max('sort_order')) + 1;
                    foreach ($newFiles as $file) {
                        if ($file instanceof UploadedFile && $file->isValid()) {
                            $media = $this->mediaService->uploadOne($schedule, $file, 'schedule-attachments', ['disk' => 'public']);
                            $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];

                            $schedule->attachments()->create([
                                'organization_id' => $orgId,
                                'media_id' => $media->id,
                                'file_name' => $file->getClientOriginalName(),
                                'sort_order' => $nextSort++,
                            ]);
                        }
                    }
                }

                // Update recipients if provided
                if (isset($validated['recipients'])) {
                    $schedule->recipients()->delete();
                    foreach ($validated['recipients'] as $rec) {
                        $schedule->recipients()->create([
                            'user_id' => $rec['user_id'] ?? null,
                            'group_id' => $rec['group_id'] ?? null,
                        ]);
                    }
                }

                // Update reminders if provided
                if (isset($validated['reminders'])) {
                    $schedule->reminders()->delete();
                    foreach ($validated['reminders'] as $rem) {
                        $schedule->reminders()->create([
                            'minutes_before' => $rem['minutes_before'],
                            'channels' => $rem['channels'],
                            'source' => $rem['source'] ?? ReminderSourceEnum::Custom->value,
                            'preset_id' => $rem['preset_id'] ?? null,
                        ]);
                    }
                }

                $statusVal = $schedule->status->value;

                $isPublishedNow = $statusVal === ScheduleStatusEnum::Published->value;
                $wasPublishedBefore = $originalStatusVal === ScheduleStatusEnum::Published->value;

                if ($isPublishedNow && !$wasPublishedBefore) {
                    Event::dispatch(new SchedulePublished($schedule));
                } elseif ($isPublishedNow && $wasPublishedBefore) {
                    $changedFields = array_filter(
                        ['content', 'event_date', 'start_time', 'location'],
                        fn ($f) => $schedule->wasChanged($f)
                    );
                    if (!empty($changedFields)) {
                        Event::dispatch(new ScheduleUpdated($schedule, array_values($changedFields)));
                    }
                } elseif (!$isPublishedNow && $wasPublishedBefore) {
                    Event::dispatch(new ScheduleCancelled($schedule));
                }

                return $schedule->fresh(['host', 'driver', 'creator', 'editor', 'attachments.mediaFile']);
            });
        } catch (\Throwable $e) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $e;
        }
    }

    /**
     * Soft delete a schedule.
     */
    public function destroy(Schedule $schedule): bool
    {
        $wasPublished = $schedule->status->value === ScheduleStatusEnum::Published->value;
        $deleted = $schedule->delete();
        if ($deleted && $wasPublished) {
            Event::dispatch(new ScheduleCancelled($schedule));
        }
        return $deleted;
    }

    /**
     * Restore a soft-deleted schedule.
     */
    public function restore(int $id): Schedule
    {
        $orgId = $this->resolveCurrentOrganizationId();
        $schedule = Schedule::onlyTrashed()
            ->where('organization_id', $orgId)
            ->findOrFail($id);

        $schedule->restore();

        return $schedule;
    }

    /**
     * Approve a pending schedule.
     */
    public function approve(Schedule $schedule): Schedule
    {
        $wasPublished = $schedule->status->value === ScheduleStatusEnum::Published->value;
        $schedule->status = ScheduleStatusEnum::Published;
        $schedule->approved_by = auth()->id();
        $schedule->approved_at = now();
        $schedule->save();

        if (!$wasPublished) {
            Event::dispatch(new SchedulePublished($schedule));
        }

        return $schedule;
    }

    /**
     * Reject a pending schedule back to draft.
     */
    public function reject(Schedule $schedule): Schedule
    {
        $wasPublished = $schedule->status->value === ScheduleStatusEnum::Published->value;
        $schedule->status = ScheduleStatusEnum::Draft;
        $schedule->save();

        if ($wasPublished) {
            Event::dispatch(new ScheduleCancelled($schedule));
        }

        return $schedule;
    }

    /**
     * Reorder schedules sort order.
     */
    public function reorder(array $orders): void
    {
        DB::transaction(function () use ($orders) {
            foreach ($orders as $order) {
                Schedule::where('organization_id', $this->resolveCurrentOrganizationId())
                    ->where('id', $order['id'])
                    ->update(['sort_order' => $order['sort_order']]);
            }
        });
    }

    /**
     * Duplicate schedule to multiple target dates.
     */
    public function duplicate(Schedule $schedule, array $dates): array
    {
        $duplicatedSchedules = [];

        DB::transaction(function () use ($schedule, $dates, &$duplicatedSchedules) {
            $orgId = $this->resolveCurrentOrganizationId();
            
            // Eager load relations for cloning
            $schedule->load(['recipients', 'reminders', 'attachments.mediaFile']);

            foreach ($dates as $date) {
                $carbonDate = Carbon::parse($date);
                
                // Clone attributes
                $newAttributes = $schedule->toArray();
                unset($newAttributes['id'], $newAttributes['created_at'], $newAttributes['updated_at'], $newAttributes['deleted_at']);
                
                $newAttributes['event_date'] = $date;
                $newAttributes['week_number'] = $carbonDate->weekOfYear;
                $newAttributes['year'] = $carbonDate->year;
                $newAttributes['status'] = ScheduleStatusEnum::Draft->value; // Duplicates always default to Draft

                // Create new schedule
                $newSchedule = Schedule::create($newAttributes);

                // Clone recipients
                foreach ($schedule->recipients as $rec) {
                    $newSchedule->recipients()->create([
                        'user_id' => $rec->user_id,
                        'group_id' => $rec->group_id,
                    ]);
                }

                // Clone reminders
                foreach ($schedule->reminders as $rem) {
                    $newSchedule->reminders()->create([
                        'minutes_before' => $rem->minutes_before,
                        'channels' => $rem->channels,
                        'source' => $rem->source->value,
                        'preset_id' => $rem->preset_id,
                    ]);
                }

                // Clone attachments
                foreach ($schedule->attachments as $att) {
                    $mediaFile = $att->mediaFile;
                    if ($mediaFile) {
                        $newMedia = $mediaFile->copy($newSchedule, 'schedule-attachments', 'public');
                        $newSchedule->attachments()->create([
                            'organization_id' => $orgId,
                            'media_id' => $newMedia->id,
                            'file_name' => $att->file_name,
                            'sort_order' => $att->sort_order,
                        ]);
                    }
                }

                $duplicatedSchedules[] = $newSchedule;
            }
        });

        return $duplicatedSchedules;
    }

    /**
     * Get statistics counts per status.
     */
    public function stats(array $filters): array
    {
        $query = Schedule::query();
        $this->filterService->filter($query, $filters);

        return [
            'total' => (clone $query)->count(),
            'draft' => (clone $query)->where('status', ScheduleStatusEnum::Draft->value)->count(),
            'pending' => (clone $query)->where('status', ScheduleStatusEnum::Pending->value)->count(),
            'published' => (clone $query)->where('status', ScheduleStatusEnum::Published->value)->count(),
            'cancelled' => (clone $query)->where('status', ScheduleStatusEnum::Cancelled->value)->count(),
        ];
    }

    /**
     * Bulk delete schedules.
     */
    public function bulkDestroy(array $ids): void
    {
        $orgId = $this->resolveCurrentOrganizationId();
        
        DB::transaction(function () use ($ids, $orgId) {
            $schedules = Schedule::whereIn('id', $ids)
                ->where('organization_id', $orgId)
                ->get();

            foreach ($schedules as $schedule) {
                if ($schedule->status === ScheduleStatusEnum::Published->value) {
                    Event::dispatch(new ScheduleCancelled($schedule));
                }
                $schedule->delete();
            }
        });
    }

    /**
     * Bulk update status of schedules.
     */
    public function bulkUpdateStatus(array $ids, int $status): void
    {
        $orgId = $this->resolveCurrentOrganizationId();

        DB::transaction(function () use ($ids, $status, $orgId) {
            $schedules = Schedule::whereIn('id', $ids)
                ->where('organization_id', $orgId)
                ->get();

            foreach ($schedules as $schedule) {
                $oldStatus = $schedule->status;
                
                $schedule->status = $status;
                if ($status === ScheduleStatusEnum::Published->value) {
                    $schedule->approved_by = auth()->id();
                    $schedule->approved_at = now();
                }
                $schedule->save();

                if ($oldStatus !== $schedule->status) {
                    if ($schedule->status === ScheduleStatusEnum::Published->value) {
                        Event::dispatch(new SchedulePublished($schedule));
                    } elseif ($schedule->status === ScheduleStatusEnum::Cancelled->value) {
                        Event::dispatch(new ScheduleCancelled($schedule));
                    } elseif ($oldStatus === ScheduleStatusEnum::Published->value) {
                        Event::dispatch(new ScheduleCancelled($schedule));
                    }
                }
            }
        });
    }

    /**
     * Change status of a single schedule.
     */
    public function changeStatus(Schedule $schedule, int $status): Schedule
    {
        $oldStatus = $schedule->status;
        
        $schedule->status = $status;
        if ($status === ScheduleStatusEnum::Published->value) {
            $schedule->approved_by = auth()->id();
            $schedule->approved_at = now();
        }
        $schedule->save();

        if ($oldStatus !== $schedule->status) {
            if ($schedule->status === ScheduleStatusEnum::Published->value) {
                Event::dispatch(new SchedulePublished($schedule));
            } elseif ($schedule->status === ScheduleStatusEnum::Cancelled->value) {
                Event::dispatch(new ScheduleCancelled($schedule));
            } elseif ($oldStatus === ScheduleStatusEnum::Published->value) {
                Event::dispatch(new ScheduleCancelled($schedule));
            }
        }

        return $this->show($schedule);
    }

    /**
     * Import schedules from Excel.
     */
    public function import($file): void
    {
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Modules\Scheduling\Imports\ScheduleImport, $file);
    }

    /**
     * Resolve the current organization ID.
     */
    protected function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (!is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
