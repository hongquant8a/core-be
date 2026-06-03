<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Core\Support\ExportFilename;
use App\Modules\Scheduling\Enums\ScheduleStatus;
use App\Modules\Scheduling\Enums\SessionType;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ScheduleAttachment;
use App\Modules\Scheduling\Models\ScheduleNotificationRecipient;
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
    /**
     * Thống kê: total, draft, pending, approved, rejected, cancelled.
     */
    public function stats(array $filters): array
    {
        $base = Schedule::filter($filters);
        return [
            'total'     => (clone $base)->count(),
            'draft'     => (clone $base)->where('status', ScheduleStatus::DRAFT->value)->count(),
            'pending'   => (clone $base)->where('status', ScheduleStatus::PENDING->value)->count(),
            'approved'  => (clone $base)->where('status', ScheduleStatus::PUBLISHED->value)->count(),
            'rejected'  => 0, // In standard spec, reject sets status to CANCELLED. We return 0 for compatibility.
            'cancelled' => (clone $base)->where('status', ScheduleStatus::CANCELLED->value)->count(),
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

        return Schedule::with(['creator', 'editor', 'host', 'driver', 'recipients.user', 'recipients.group', 'attachments', 'reminders'])
            ->filter($filters)
            ->paginate($limit);
    }

    /**
     * Ma trận tuần: group theo date → session → [schedules], kèm thông tin tuần (week_id, week_number, year).
     */
    public function weekMatrix(array $filters): array
    {
        if (Auth::check() && Auth::user()->hasRole('scheduling-lai-xe')) {
            $filters['driver_id'] = Auth::id();
        }

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

        $schedules = Schedule::with(['host', 'driver', 'recipients.user', 'recipients.group', 'attachments', 'reminders'])
            ->filter($filters)
            ->orderBy('date_time')
            ->orderBy('session')
            ->orderBy('sort_order')
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
     * Lấy danh sách các tuần đã có lịch (dropdown chọn tuần).
     */
    public function getWeeks(array $filters): array
    {
        $dates = Schedule::filter($filters)
            ->selectRaw('DATE(date_time) as date_val')
            ->whereNotNull('date_time')
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
            'recipients.user', 'recipients.group', 'attachments', 'reminders',
        ]);
    }

    /**
     * Tạo mới lịch.
     */
    public function store(array $data, array $files = [], array $participants = [], array $reminders = []): Schedule
    {
        $participants = !empty($participants) ? $participants : ($data['participants'] ?? $data['recipients'] ?? []);
        $reminders = !empty($reminders) ? $reminders : ($data['reminders'] ?? []);

        return DB::transaction(function () use ($data, $files, $participants, $reminders) {
            $orgId = getPermissionsTeamId();
            $data['organization_id'] = $orgId;
            
            // Check approval setting
            if (isset($data['status'])) {
                $statusVal = (int)$data['status'];
            }

            if ($statusVal === ScheduleStatus::DRAFT->value) {
                $data['status'] = ScheduleStatus::DRAFT->value;
            } else {
                $orgSettings = OrgSchedulingSettings::firstOrCreate(['organization_id' => $orgId]);
                $requiresApproval = (bool)$orgSettings->requires_approval;

                if ($requiresApproval) {
                    $data['status'] = ScheduleStatus::PENDING->value;
                } else {
                    $data['status'] = ScheduleStatus::PUBLISHED->value;
                }
            }

            $schedule = Schedule::create($data);

            $this->syncRecipients($schedule, $participants);
            $this->syncReminders($schedule, $reminders);

            if ($files) {
                foreach ($files as $index => $file) {
                    $this->uploadAttachment($schedule, $file, $index);
                }
            }

            // Auto-trigger notifications queue if published
            $statusVal = $schedule->status;
            if ($statusVal instanceof ScheduleStatus) {
                $statusVal = $statusVal->value;
            } else {
                $statusVal = (int)$statusVal;
            }

            if ($statusVal === ScheduleStatus::PUBLISHED->value) {
                // Call job to compile notifications and reminders
                app(NotificationService::class)->publish($schedule);
            }

            return $this->show($schedule);
        });
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

        return DB::transaction(function () use ($schedule, $data, $files, $participants, $reminders, $removeMediaIds) {
            $schedule->update($data);

            if ($participants !== null) {
                $this->syncRecipients($schedule, $participants);
            }
            if ($reminders !== null) {
                $this->syncReminders($schedule, $reminders);
            }
            
            // Sync attachments by deleting removed ones
            if (isset($data['attachments']) && is_array($data['attachments'])) {
                $keepIds = [];
                foreach ($data['attachments'] as $att) {
                    if (is_array($att) && isset($att['id']) && is_numeric($att['id'])) {
                        $keepIds[] = (int)$att['id'];
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
                foreach ($files as $index => $file) {
                    $this->uploadAttachment($schedule, $file, $index);
                }
            }

            // If updated status to published, queue notifications
            $statusVal = $schedule->fresh()->status;
            if ($statusVal instanceof ScheduleStatus) {
                $statusVal = $statusVal->value;
            } else {
                $statusVal = (int)$statusVal;
            }
            if ($statusVal === ScheduleStatus::PUBLISHED->value) {
                app(NotificationService::class)->update($schedule);
            }

            return $this->show($schedule->fresh());
        });
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
        Schedule::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string|int $status): void
    {
        $statusInt = (int)$status;
        if (is_string($status) && !is_numeric($status)) {
            $statusStr = strtoupper($status);
            $statusInt = match ($statusStr) {
                'DRAFT' => ScheduleStatus::DRAFT->value,
                'PENDING' => ScheduleStatus::PENDING->value,
                'APPROVED', 'PUBLISHED' => ScheduleStatus::PUBLISHED->value,
                'CANCELLED' => ScheduleStatus::CANCELLED->value,
                default => 0,
            };
        }

        Schedule::whereIn('id', $ids)->update(['status' => $statusInt]);
    }

    /**
     * Đổi trạng thái đơn lẻ.
     */
    public function changeStatus(Schedule $schedule, string|int $status): Schedule
    {
        $statusInt = (int)$status;
        if (is_string($status) && !is_numeric($status)) {
            $statusStr = strtoupper($status);
            $statusInt = match ($statusStr) {
                'DRAFT' => ScheduleStatus::DRAFT->value,
                'PENDING' => ScheduleStatus::PENDING->value,
                'APPROVED', 'PUBLISHED' => ScheduleStatus::PUBLISHED->value,
                'CANCELLED' => ScheduleStatus::CANCELLED->value,
                default => 0,
            };
        }

        $schedule->update(['status' => $statusInt]);
        
        if ($statusInt === ScheduleStatus::PUBLISHED->value) {
            app(NotificationService::class)->publish($schedule);
        } elseif ($statusInt === ScheduleStatus::CANCELLED->value) {
            app(NotificationService::class)->cancel($schedule);
        }

        return $this->show($schedule->fresh());
    }

    /**
     * Duyệt lịch.
     */
    public function approve(Schedule $schedule): Schedule
    {
        $schedule->update([
            'status'      => ScheduleStatus::PUBLISHED->value,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        app(NotificationService::class)->publish($schedule);

        return $this->show($schedule->fresh());
    }

    /**
     * Từ chối lịch: trả về Draft.
     */
    public function reject(Schedule $schedule, string $note): Schedule
    {
        $schedule->update([
            'status' => ScheduleStatus::DRAFT->value,
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
            $new->status     = ScheduleStatus::DRAFT->value;
            $new->save();

            // Clone recipients
            foreach ($schedule->recipients as $recipient) {
                $new->recipients()->create([
                    'user_id'      => $recipient->user_id,
                    'group_id'     => $recipient->group_id,
                    'display_name' => $recipient->display_name,
                ]);
            }

            // Clone reminders
            foreach ($schedule->reminders as $reminder) {
                $new->reminders()->create([
                    'minutes_before' => $reminder->minutes_before,
                    'channels'       => $reminder->channels,
                    'source'         => $reminder->source,
                    'preset_id'      => $reminder->preset_id,
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
    private function uploadAttachment(Schedule $schedule, $file, int $sortOrder): void
    {
        $uuid = Str::uuid()->toString();
        $ext = $file->getClientOriginalExtension();
        $fileName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $fileSize = $file->getSize();
        $yearMonth = now()->format('Y/m');
        $path = $file->storeAs("schedules/{$yearMonth}", "{$uuid}.{$ext}", 'public');

        ScheduleAttachment::create([
            'schedule_id' => $schedule->id,
            'title'       => pathinfo($fileName, PATHINFO_FILENAME),
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
                    'group_id'     => null,
                    'display_name' => null,
                ]);
            } else {
                $schedule->recipients()->create([
                    'user_id'      => $recipient['user_id'] ?? $recipient['id'] ?? null,
                    'group_id'     => $recipient['group_id'] ?? null,
                    'display_name' => $recipient['display_name'] ?? null,
                ]);
            }
        }
    }

    private function syncReminders(Schedule $schedule, array $reminders): void
    {
        $schedule->reminders()->delete();
        foreach ($reminders as $r) {
            $minutes = $r['minutes_before'] ?? $r['offset_minutes'] ?? 0;
            $source = $r['source'] ?? $r['reminder_type'] ?? 'CUSTOM';
            
            $channels = $r['channels'] ?? [];
            if (!is_array($channels)) {
                $channels = [$channels];
            }
            $channels = array_map('strtoupper', $channels);
            
            $schedule->reminders()->create([
                'minutes_before' => (int)$minutes,
                'channels'       => array_values(array_unique($channels)),
                'source'         => $source,
                'preset_id'      => $r['preset_id'] ?? null,
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
}
