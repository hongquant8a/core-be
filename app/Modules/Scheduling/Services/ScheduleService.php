<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Core\Support\ExportFilename;
use App\Modules\Scheduling\Enums\ModuleType;
use App\Modules\Scheduling\Enums\ScheduleStatus;
use App\Modules\Scheduling\Enums\ApprovalStatus;
use App\Modules\Scheduling\Enums\SessionType;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ScheduleAttachment;
use App\Modules\Core\Services\MediaService;
use App\Modules\Scheduling\Models\ScheduleReminder;
use App\Modules\Scheduling\Models\OrgSchedulingSettings;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScheduleService
{
    public function __construct(private MediaService $mediaService) {}
    /**
     * Thống kê: total, draft, pending_approval, approved, rejected, published.
     * Mỗi schedule nằm trong đúng 1 bucket.
     */
    public function stats(array $filters): array
    {
        $base = Schedule::filter($filters);
        return [
            'total'            => (clone $base)->count(),
            'draft'            => (clone $base)->where('status', ScheduleStatus::DRAFT->value)->count(),
            'pending_approval' => (clone $base)->where('status', ScheduleStatus::PUBLISHED->value)->where('approval_status', ApprovalStatus::PENDING->value)->count(),
            'approved'         => (clone $base)->where('status', ScheduleStatus::PUBLISHED->value)->where('approval_status', ApprovalStatus::APPROVED->value)->count(),
            'rejected'         => (clone $base)->where('status', ScheduleStatus::PUBLISHED->value)->where('approval_status', ApprovalStatus::REJECTED->value)->count(),
            'published'        => (clone $base)->where('status', ScheduleStatus::PUBLISHED->value)->count(),
        ];
    }

    /**
     * Danh sách phân trang.
     */
    public function index(array $filters, int $limit)
    {
        // Lái xe — khi user có role scheduling-lai-xe, tự động áp driver_id = auth()->id() vào filter
        if (Auth::check() && Auth::user()->hasRole('scheduling-lai-xe')) {
            $filters['driver_id'] = Auth::id();
        }

        return Schedule::with(['creator', 'editor', 'host', 'driver', 'recipients.user', 'attachments', 'reminders'])
            ->filter($filters)
            ->paginate($limit);
    }

    /**
     * Ma trận tuần: group theo date → session → [schedules], kèm thông tin tuần (week_id, week_number, year).
     */
    public function weekMatrix(array $filters): array
    {
        // Default: chỉ lịch đã duyệt + bản nháp của chính mình. Có quyền duyệt → thêm lịch chờ duyệt.
        $filters['general_visibility'] = true;

        $year = $filters['year'] ?? null;
        $weekNumber = $filters['week_number'] ?? null;

        if (!$year || !$weekNumber) {
            $anchorDate = $filters['from_date'] ?? $filters['event_date'] ?? $filters['date'] ?? now()->toDateString();
            $carbon = \Carbon\Carbon::parse($anchorDate);
            $year = $carbon->isoWeekYear;
            $weekNumber = $carbon->isoWeek;
        }

        $weekId = "{$year}-W" . str_pad($weekNumber, 2, '0', STR_PAD_LEFT);
        $start = now()->setISODate((int)$year, (int)$weekNumber)->startOfWeek();
        $end = now()->setISODate((int)$year, (int)$weekNumber)->endOfWeek();

        if (empty($filters['week']) && empty($filters['event_date']) && empty($filters['date']) && empty($filters['from_date'])) {
            $filters['week'] = $weekId;
        }

        $dateCol = 'date_time';

        $schedules = Schedule::with(['host', 'driver', 'recipients.user', 'attachments', 'reminders'])
            ->filter($filters)
            // Sửa lỗi FE: Ưu tiên sort_order để FE có thể moveUp/moveDown/chèn dòng.
            // Phải order theo ngày -> buổi -> sort_order -> time
            ->orderByRaw("DATE({$dateCol}) ASC")
            ->orderBy('session', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy($dateCol, 'ASC')
            ->get();

        $matrix = $schedules->groupBy(fn($item) => $item->date_time ? $item->date_time->format('Y-m-d') : '')->map(function ($day) {
            return $day->groupBy(fn($item) => $item->session->value ?? $item->session);
        })->toArray();

        return [
            'week_id'     => $weekId,
            'week_number' => (int)$weekNumber,
            'year'        => (int)$year,
            'date_from'   => $start->format('Y-m-d'),
            'date_to'     => $end->format('Y-m-d'),
            'matrix'      => $matrix,
        ];
    }

    /**
     * Ma trận lịch cho Lái xe — format gọn qua DriverScheduleResource.
     */
    public function driverWeekMatrix(array $filters): array
    {
        $filters['status'] = \App\Modules\Scheduling\Enums\ScheduleStatus::PUBLISHED->value;
        $filters['driver_id'] = Auth::id();

        $year = $filters['year'] ?? null;
        $weekNumber = $filters['week_number'] ?? null;

        if (!$year || !$weekNumber) {
            $anchorDate = $filters['from_date'] ?? $filters['event_date'] ?? $filters['date'] ?? now()->toDateString();
            $carbon = \Carbon\Carbon::parse($anchorDate);
            $year = $carbon->isoWeekYear;
            $weekNumber = $carbon->isoWeek;
        }

        $weekId = "{$year}-W" . str_pad($weekNumber, 2, '0', STR_PAD_LEFT);
        $start = now()->setISODate((int)$year, (int)$weekNumber)->startOfWeek();
        $end = now()->setISODate((int)$year, (int)$weekNumber)->endOfWeek();

        if (empty($filters['week']) && empty($filters['event_date']) && empty($filters['date']) && empty($filters['from_date'])) {
            $filters['week'] = $weekId;
        }

        $dateCol = 'date_time';

        $schedules = Schedule::with(['host'])
            ->filter($filters)
            ->orderByRaw("DATE({$dateCol}) ASC")
            ->orderBy('session', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy($dateCol, 'ASC')
            ->get();

        $matrix = $schedules->groupBy(fn($item) => $item->date_time ? $item->date_time->format('Y-m-d') : '')->map(function ($day) {
            return $day->groupBy(fn($item) => $item->session->value ?? $item->session)->map(function ($items) {
                return \App\Modules\Scheduling\Resources\DriverScheduleResource::collection($items);
            });
        });

        return [
            'week_id'     => $weekId,
            'week_number' => (int)$weekNumber,
            'year'        => (int)$year,
            'date_from'   => $start->format('Y-m-d'),
            'date_to'     => $end->format('Y-m-d'),
            'matrix'      => $matrix,
        ];
    }

    /**
     * Lấy danh sách các tuần đã có lịch (dropdown chọn tuần).
     */
    public function getWeeks(array $filters): array
    {
        $dateCol = 'date_time';

        $filters['sort_by'] = '';

        $dates = Schedule::filter($filters)
            ->selectRaw("DATE({$dateCol}) as date_val")
            ->whereNotNull($dateCol)
            ->distinct()
            ->orderBy('date_val', 'asc')
            ->pluck('date_val');

        $weeks = [];
        foreach ($dates as $date) {
            $carbon = \Carbon\Carbon::parse($date);
            $year = $carbon->isoWeekYear;
            $weekNumber = $carbon->isoWeek;
            $weekId = "{$year}-W" . str_pad($weekNumber, 2, '0', STR_PAD_LEFT);

            if (!isset($weeks[$weekId])) {
                $start = (clone $carbon)->startOfWeek()->format('d/m/Y');
                $end = (clone $carbon)->endOfWeek()->format('d/m/Y');
                $weeks[$weekId] = [
                    'week_id'     => $weekId,
                    'week_number' => $weekNumber,
                    'year'        => $year,
                    'label'       => "Tuần {$weekNumber}, {$year} ({$start} - {$end})",
                    'date_from'   => (clone $carbon)->startOfWeek()->format('Y-m-d'),
                    'date_to'     => (clone $carbon)->endOfWeek()->format('Y-m-d'),
                ];
            }
        }

        return array_values($weeks);
    }

    /**
     * Chi tiết + preload đầy đủ.
     */
    public function show(Schedule $schedule): Schedule
    {
        return $schedule->load([
            'creator', 'editor', 'host', 'driver', 'approver',
            'recipients.user', 'attachments', 'reminders',
        ]);
    }

    /**
     * Tạo mới lịch.
     */
    public function store(array $data, array $files = [], array $participants = [], array $reminders = []): Schedule
    {
        $participants = !empty($participants) ? $participants : ($data['participants'] ?? $data['recipients'] ?? []);
        $reminders = !empty($reminders) ? $reminders : ($data['reminders'] ?? []);
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($data, $files, $participants, $reminders, &$storedFiles) {
                $orgId = getPermissionsTeamId();
                $data['organization_id'] = $orgId;

                // Normalize status: nếu FE gửi PUBLISHED thì áp dụng approval logic luôn
                $statusInput = $data['status'] ?? null;
                $statusInt = $this->normalizeStatus($statusInput) ?? ScheduleStatus::DRAFT->value;
                $data['status'] = $statusInt;
                if ($statusInt === ScheduleStatus::PUBLISHED->value) {
                    $data['approval_status'] = $this->resolveApprovalStatus($data, $orgId);
                }
                if (!empty($data['sort_order']) && !empty($data['date_time']) && !empty($data['session'])) {
                    $carbon = \Carbon\Carbon::parse($data['date_time']);
                    Schedule::where('organization_id', $orgId)
                        ->where('module_type', $data['module_type'])
                        ->whereDate('date_time', $carbon->toDateString())
                        ->where('session', $data['session'])
                        ->where('sort_order', '>=', (int) $data['sort_order'])
                        ->lockForUpdate()
                        ->increment('sort_order');
                }

                $schedule = Schedule::create($data);

                $this->syncRecipients($schedule, $participants);
                $this->syncReminders($schedule, $reminders);

                if ($files) {
                    $attachmentNames = $data['attachment_names'] ?? [];
                    foreach ($files as $index => $file) {
                        $customName = $attachmentNames[$index] ?? null;
                        $this->uploadAttachment($schedule, $file, $index, $customName, $storedFiles);
                    }
                }

                return $this->show($schedule);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    /**
     * Cập nhật lịch.
     */
    public function update(Schedule $schedule, array $data, array $files = [], array $participants = null, array $reminders = null, array $removeMediaIds = []): Schedule
    {
        if ($participants === null && isset($data['participants'])) {
            $participants = $data['participants'];
        }
        if ($participants === null && isset($data['recipients'])) {
            $participants = $data['recipients'];
        }
        if ($reminders === null && isset($data['reminders'])) {
            $reminders = $data['reminders'];
        }
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($schedule, $data, $files, $participants, $reminders, $removeMediaIds, &$storedFiles) {
                // Nếu đang đổi status → PUBLISHED, auto resolve approval_status
                $newStatus = $data['status'] ?? null;
                if ($newStatus !== null) {
                    $statusInt = $this->normalizeStatus($newStatus);
                    if ($statusInt === ScheduleStatus::PUBLISHED->value && $schedule->status !== ScheduleStatus::PUBLISHED) {
                        $data['approval_status'] = $this->resolveApprovalStatus(
                            ['module_type' => $data['module_type'] ?? $schedule->module_type],
                            $schedule->organization_id,
                        );
                    } elseif ($statusInt === ScheduleStatus::DRAFT->value && $schedule->status !== ScheduleStatus::DRAFT) {
                        $data['approval_status'] = null;
                    }
                }

                $schedule->update($data);

                if ($participants !== null) {
                    $this->syncRecipients($schedule, $participants);
                }
                if ($reminders !== null) {
                    $this->syncReminders($schedule, $reminders);
                }

                if (isset($data['attachments']) && is_array($data['attachments'])) {
                    $keepIds = [];
                    foreach ($data['attachments'] as $att) {
                        if (is_array($att) && isset($att['id']) && is_numeric($att['id'])) {
                            $keepIds[] = (int)$att['id'];
                            if (!empty($att['name'])) {
                                ScheduleAttachment::where('id', (int)$att['id'])
                                    ->where('schedule_id', $schedule->id)
                                    ->update(['title' => $att['name']]);
                            }
                        }
                    }
                    $attachmentsToDelete = ScheduleAttachment::where('schedule_id', $schedule->id)
                        ->whereNotIn('id', $keepIds)
                        ->get();
                    foreach ($attachmentsToDelete as $att) {
                        Storage::disk('public')->delete($att->file_path);
                        $att->delete();
                    }
                }

                if (!empty($removeMediaIds)) {
                    $attachments = ScheduleAttachment::whereIn('id', $removeMediaIds)->where('schedule_id', $schedule->id)->get();
                    foreach ($attachments as $att) {
                        Storage::disk('public')->delete($att->file_path);
                        $att->delete();
                    }
                }
                if ($files) {
                    $attachmentNames = $data['attachment_names'] ?? [];
                    foreach ($files as $index => $file) {
                        $customName = $attachmentNames[$index] ?? null;
                        $this->uploadAttachment($schedule, $file, $index, $customName, $storedFiles);
                    }
                }

                $statusVal = $schedule->fresh()->status;
                if ($statusVal instanceof ScheduleStatus) {
                    $statusVal = $statusVal->value;
                } else {
                    $statusVal = (int)$statusVal;
                }
                return $this->show($schedule->fresh());
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    /**
     * Xóa mềm.
     */
    public function destroy(Schedule $schedule): void
    {
        $schedule->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Schedule::where('organization_id', getPermissionsTeamId())->whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string|int $status): void
    {
        $statusInt = (int)$status;
        if (is_string($status) && !is_numeric($status)) {
            $statusStr = strtoupper($status);
            $statusInt = match ($statusStr) {
                'DRAFT' => ScheduleStatus::DRAFT->value,
                'PUBLISHED' => ScheduleStatus::PUBLISHED->value,
                default => 0,
            };
        }

        Schedule::where('organization_id', getPermissionsTeamId())->whereIn('id', $ids)->update(['status' => $statusInt]);
    }

    /**
     * Đổi trạng thái publish (DRAFT ↔ PUBLISHED).
     */
    public function changeStatus(Schedule $schedule, string|int $status): Schedule
    {
        $statusInt = (int)$status;
        if (is_string($status) && !is_numeric($status)) {
            $statusStr = strtoupper($status);
            $statusInt = match ($statusStr) {
                'DRAFT' => ScheduleStatus::DRAFT->value,
                'PUBLISHED' => ScheduleStatus::PUBLISHED->value,
                default => 0,
            };
        }

        $updateData = ['status' => $statusInt];

        if ($statusInt === ScheduleStatus::PUBLISHED->value && $schedule->status !== ScheduleStatus::PUBLISHED) {
            // DRAFT → PUBLISHED: auto resolve approval_status từ setting phân hệ
            $updateData['approval_status'] = $this->resolveApprovalStatus(
                ['module_type' => $schedule->module_type],
                $schedule->organization_id,
            );
        } elseif ($statusInt === ScheduleStatus::DRAFT->value && $schedule->status !== ScheduleStatus::DRAFT) {
            // PUBLISHED → DRAFT: clear approval_status
            $updateData['approval_status'] = null;
        }

        $schedule->update($updateData);

        return $this->show($schedule->fresh());
    }

    /**
     * Duyệt lịch — set approval_status = approved.
     */
    public function approve(Schedule $schedule): Schedule
    {
        $schedule->update([
            'approval_status' => ApprovalStatus::APPROVED->value,
            'approved_by'     => Auth::id(),
            'approved_at'     => now(),
        ]);

        return $this->show($schedule->fresh());
    }

    /**
     * Từ chối duyệt lịch — set approval_status = rejected.
     */
    public function reject(Schedule $schedule, string $note): Schedule
    {
        $schedule->update([
            'approval_status' => ApprovalStatus::REJECTED->value,
            'rejection_note'  => $note,
        ]);
        return $this->show($schedule->fresh());
    }

    /**
     * Sao chép lịch sang ngày khác.
     */
    public function duplicate(Schedule $schedule, string $date): Schedule
    {
        return DB::transaction(function () use ($schedule, $date) {
            $new = $schedule->replicate(['approved_by', 'approved_at', 'created_by', 'updated_by']);
            $timePart = $schedule->date_time ? $schedule->date_time->format('H:i:s') : '08:00:00';
            $new->date_time = "{$date} {$timePart}";
            $new->status          = ScheduleStatus::DRAFT->value;
            $new->approval_status = null;
            $new->save();

            // Clone recipients
            foreach ($schedule->recipients as $recipient) {
                $new->recipients()->create([
                    'user_id'      => $recipient->user_id,
                    'display_name' => $recipient->display_name,
                ]);
            }

            // Clone reminders
            foreach ($schedule->reminders as $reminder) {
                $new->reminders()->create([
                    'moment'                   => $reminder->moment,
                    'offset_minutes'           => $reminder->offset_minutes,
                    'channels'                 => $reminder->channels,
                    'source'                   => $reminder->source,
                    'notification_schedule_id' => $reminder->notification_schedule_id,
                ]);
            }

            // Clone attachments
            foreach ($schedule->attachments as $attachment) {
                $newPath = $attachment->file_path;
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->file_path)) {
                    $ext = pathinfo($attachment->file_name, PATHINFO_EXTENSION);
                    $newPath = 'schedules/' . now()->format('Y/m') . '/' . \Illuminate\Support\Str::uuid()->toString() . '.' . $ext;
                    \Illuminate\Support\Facades\Storage::disk('public')->copy($attachment->file_path, $newPath);
                }

                $new->attachments()->create([
                    'title'       => $attachment->title,
                    'file_name'   => $attachment->file_name,
                    'file_path'   => $newPath,
                    'file_size'   => $attachment->file_size,
                    'mime_type'   => $attachment->mime_type,
                    'sort_order'  => $attachment->sort_order,
                    'uploaded_by' => \Illuminate\Support\Facades\Auth::id() ?? 1,
                ]);
            }

            return $this->show($new);
        });
    }

    /**
     * Sắp xếp lại thứ tự lịch.
     */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $order => $id) {
                Schedule::where('id', $id)->update(['sort_order' => $order + 1]);
            }
        });
    }

    /**
     * Phục vụ upload attachment.
     */
    private function uploadAttachment(Schedule $schedule, $file, int $sortOrder, ?string $customName = null, array &$storedFiles = []): void
    {
        $uuid = Str::uuid()->toString();
        $ext = $file->getClientOriginalExtension();
        $fileName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $fileSize = $file->getSize();
        $yearMonth = now()->format('Y/m');
        $path = $file->storeAs("schedules/{$yearMonth}", "{$uuid}.{$ext}", 'public');

        $storedFiles[] = ['disk' => 'public', 'path' => $path];

        ScheduleAttachment::create([
            'schedule_id' => $schedule->id,
            'title'       => $customName ?: pathinfo($fileName, PATHINFO_FILENAME),
            'file_name'   => $fileName,
            'file_path'   => $path,
            'file_size'   => $fileSize,
            'mime_type'   => $mimeType,
            'sort_order'  => $sortOrder,
            'uploaded_by' => Auth::id() ?? 1,
        ]);
    }

    private function syncRecipients(Schedule $schedule, array $recipients): void
    {
        $schedule->recipients()->delete();
        foreach ($recipients as $recipient) {
            if (is_scalar($recipient)) {
                $schedule->recipients()->create([
                    'user_id'      => $recipient,
                    'display_name' => null,
                ]);
            } else {
                $schedule->recipients()->create([
                    'user_id'      => $recipient['user_id'] ?? $recipient['id'] ?? null,
                    'display_name' => $recipient['display_name'] ?? null,
                ]);
            }
        }
    }

    private function syncReminders(Schedule $schedule, array $reminders): void
    {
        $schedule->reminders()->where('source', 'CUSTOM')->delete();
        foreach ($reminders as $r) {
            $moment = $r['moment'] ?? $r['trigger'] ?? 'before';
            $offset = (int) ($r['offset_minutes'] ?? $r['minutes_before'] ?? 0);

            $source = $r['source'] ?? $r['reminder_type'] ?? 'CUSTOM';

            $channels = $r['channels'] ?? [];
            if (!is_array($channels)) {
                $channels = [$channels];
            }
            $channels = array_map('strtoupper', $channels);

            $schedule->reminders()->create([
                'moment'         => $moment,
                'offset_minutes' => $offset,
                'channels'       => array_values(array_unique($channels)),
                'source'         => $source,
            ]);
        }
    }

    public function export(array $filters)
    {
        $fileName = 'export__lich-cong-tac-tuan_' . now()->format('H-i-s_d-m-Y') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Modules\Scheduling\Exports\WeeklyScheduleExcelExport($filters), $fileName);
    }

    public function exportPdf(array $filters)
    {
        $exporter = new \App\Modules\Scheduling\Exports\WeeklySchedulePdfExporter();
        $path = $exporter->generate($filters);
        $fileName = 'export__lich-cong-tac-tuan_' . now()->format('H-i-s_d-m-Y') . '.pdf';
        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    public function exportWord(array $filters)
    {
        $exporter = new \App\Modules\Scheduling\Exports\WeeklyScheduleWordExporter();
        $path = $exporter->generate($filters);
        $fileName = 'export__lich-cong-tac-tuan_' . now()->format('H-i-s_d-m-Y') . '.docx';
        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    public function weekCounts(string $anchorDate): array
    {
        $carbon = \Carbon\Carbon::parse($anchorDate);
        $start = $carbon->copy()->startOfWeek()->toDateString();
        $end = $carbon->copy()->endOfWeek()->toDateString();

        $baseQuery = Schedule::whereBetween('date_time', [$start, $end]);

        $query = (clone $baseQuery)->where(function ($q) {
            $userId = auth()->id();
            if ($userId) {
                $q->where('status', \App\Modules\Scheduling\Enums\ScheduleStatus::PUBLISHED->value)
                  ->orWhere('created_by', $userId);
            } else {
                $q->where('status', \App\Modules\Scheduling\Enums\ScheduleStatus::PUBLISHED->value);
            }

            if (!auth()->user()?->can('scheduling.approve')) {
                $q->where('approval_status', 'approved');
            }
        });

        $counts = (clone $query)->selectRaw("module_type, COUNT(*) as cnt")
            ->groupBy('module_type')
            ->pluck('cnt', 'module_type');

        $personal = 0;
        $userId = auth()->id();
        if ($userId) {
            $personal = (clone $query)->where(function ($q) use ($userId) {
                $q->where('host_id', $userId)
                  ->orWhere('driver_id', $userId)
                  ->orWhereHas('recipients', fn ($r) => $r->where('user_id', $userId));
            })->count();
        }

        return [
            'personal'  => $personal,
            'executive' => (int) ($counts['EXECUTIVE'] ?? 0),
            'office'    => (int) ($counts['OFFICE'] ?? 0),
        ];
    }

    private function normalizeStatus(mixed $status): ?int
    {
        if ($status === null) {
            return null;
        }
        if (is_numeric($status)) {
            return (int) $status;
        }
        return match (strtoupper((string) $status)) {
            'DRAFT' => ScheduleStatus::DRAFT->value,
            'PUBLISHED' => ScheduleStatus::PUBLISHED->value,
            default => null,
        };
    }

    private function resolveApprovalStatus(array $data, int $orgId): ?string
    {
        $orgSettings = OrgSchedulingSettings::firstOrCreate(['organization_id' => $orgId]);
        $moduleType = $data['module_type'] ?? null;
        $requiresApproval = false;

        if ($moduleType instanceof ModuleType) {
            $requiresApproval = $moduleType === ModuleType::EXECUTIVE
                ? $orgSettings->executive_requires_approval
                : $orgSettings->office_requires_approval;
        } else {
            $requiresApproval = strtoupper((string) $moduleType) === 'EXECUTIVE'
                ? $orgSettings->executive_requires_approval
                : $orgSettings->office_requires_approval;
        }

        return (bool) $requiresApproval
            ? ApprovalStatus::PENDING->value
            : ApprovalStatus::APPROVED->value;
    }
}
