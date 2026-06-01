<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use App\Modules\Scheduling\Exports\WeeklyScheduleExcelExport;
use App\Modules\Scheduling\Exports\WeeklySchedulePdfExporter;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScheduleService
{
    public function __construct(private MediaService $mediaService) {}

    /**
     * Thống kê: total, draft, pending, approved, rejected, cancelled.
     */
    public function stats(array $filters): array
    {
        $base = Schedule::filter($filters);
        return [
            'total'     => (clone $base)->count(),
            'draft'     => (clone $base)->where('status', ScheduleStatusEnum::Draft)->count(),
            'pending'   => (clone $base)->where('status', ScheduleStatusEnum::Pending)->count(),
            'approved'  => (clone $base)->where('status', ScheduleStatusEnum::Approved)->count(),
            'rejected'  => (clone $base)->where('status', ScheduleStatusEnum::Rejected)->count(),
            'cancelled' => (clone $base)->where('status', ScheduleStatusEnum::Cancelled)->count(),
        ];
    }

    /**
     * Danh sách phân trang.
     */
    public function index(array $filters, int $limit)
    {
        // Lái xe — khi user có role scheduling-lai-xe, tự động áp driver_user_id = auth()->id() vào filter
        if (Auth::check() && Auth::user()->hasRole('scheduling-lai-xe')) {
            $filters['driver_user_id'] = Auth::id();
        }

        return Schedule::with(['creator', 'editor', 'host', 'driver', 'participants.user'])
            ->filter($filters)
            ->paginate($limit);
    }

    /**
     * Ma trận tuần: group theo date → session → [schedules], kèm thông tin tuần (week_id, week_number, year).
     */
    public function weekMatrix(array $filters): array
    {
        // Lái xe — khi user có role scheduling-lai-xe, tự động áp driver_user_id = auth()->id() vào filter
        if (Auth::check() && Auth::user()->hasRole('scheduling-lai-xe')) {
            $filters['driver_user_id'] = Auth::id();
        }

        // Tự động suy luận week_number và year từ bộ lọc nếu chưa truyền
        $year = $filters['year'] ?? null;
        $weekNumber = $filters['week_number'] ?? null;

        if (!$year || !$weekNumber) {
            $anchorDate = $filters['from_date'] ?? $filters['date'] ?? now()->toDateString();
            $carbon = \Carbon\Carbon::parse($anchorDate);
            $year = $carbon->isoWeekYear;
            $weekNumber = $carbon->isoWeek;
        }

        $weekId = "{$year}-W" . str_pad($weekNumber, 2, '0', STR_PAD_LEFT);
        $start = now()->setISODate((int)$year, (int)$weekNumber)->startOfWeek();
        $end = now()->setISODate((int)$year, (int)$weekNumber)->endOfWeek();

        // Ép lọc theo tuần nếu chưa được lọc trong scope
        if (empty($filters['week']) && empty($filters['date']) && empty($filters['from_date'])) {
            $filters['week'] = $weekId;
        }

        $schedules = Schedule::with(['host', 'driver', 'participants.user'])
            ->filter($filters)
            ->orderBy('date')
            ->orderBy('session')
            ->orderBy('sort_order')
            ->get();

        $matrix = $schedules->groupBy(fn($item) => $item->date->format('Y-m-d'))->map(function ($day) {
            return $day->groupBy('session');
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
            ->select('date')
            ->distinct()
            ->orderBy('date', 'asc')
            ->pluck('date');

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
            'participants.user', 'reminders.notificationSchedule',
            'media',
        ]);
    }

    /**
     * Tạo mới lịch.
     */
    public function store(array $data, array $files = [], array $participants = [], array $reminders = []): Schedule
    {
        return DB::transaction(function () use ($data, $files, $participants, $reminders) {
            $data['organization_id'] = getPermissionsTeamId();
            
            // Check duyệt lịch: chỉ kích hoạt khi scheduling_settings.approval_enabled = true và module_type nằm trong approval_module_types
            if (($data['status'] ?? null) === ScheduleStatusEnum::Draft->value) {
                $data['status'] = ScheduleStatusEnum::Draft->value;
            } else {
                $settingService = app(SchedulingSettingService::class);
                $settings = $settingService->get($data['organization_id']);
                if ($settings->approval_enabled && in_array($data['module_type'], $settings->approval_module_types ?? [])) {
                    $data['status'] = ScheduleStatusEnum::Pending->value;
                } else {
                    $data['status'] = ScheduleStatusEnum::Approved->value;
                }
            }

            $schedule = Schedule::create($data);

            $this->syncParticipants($schedule, $participants);
            $this->syncReminders($schedule, $reminders);

            if ($files) {
                foreach ($files as $file) {
                    $this->mediaService->uploadOne($schedule, $file, Schedule::COLLECTION_ATTACHMENTS, [
                        'allowed_mimes' => ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg'],
                        'max_size_kb'   => 20480,
                    ]);
                }
            }

            // Manually dispatch SchedulePublished since participants are now fully synced
            $statusVal = $schedule->status instanceof ScheduleStatusEnum ? $schedule->status->value : $schedule->status;
            if ($statusVal === ScheduleStatusEnum::Approved->value) {
                \Illuminate\Support\Facades\Event::dispatch(new \App\Services\Notification\Events\SchedulePublished($schedule));
            }

            return $this->show($schedule);
        });
    }

    /**
     * Cập nhật lịch.
     */
    public function update(Schedule $schedule, array $data, array $files = [], array $participants = null, array $reminders = null, array $removeMediaIds = []): Schedule
    {
        return DB::transaction(function () use ($schedule, $data, $files, $participants, $reminders, $removeMediaIds) {
            $schedule->update($data);

            if ($participants !== null) {
                $this->syncParticipants($schedule, $participants);
            }
            if ($reminders !== null) {
                $this->syncReminders($schedule, $reminders);
            }
            if ($removeMediaIds) {
                $this->mediaService->removeByIds($schedule, $removeMediaIds, Schedule::COLLECTION_ATTACHMENTS);
            }
            foreach ($files as $file) {
                $this->mediaService->uploadOne($schedule, $file, Schedule::COLLECTION_ATTACHMENTS);
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

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        Schedule::whereIn('id', $ids)->update(['status' => $status]);
    }

    /**
     * Đổi trạng thái đơn lẻ.
     */
    public function changeStatus(Schedule $schedule, string $status): Schedule
    {
        $schedule->update(['status' => $status]);
        return $this->show($schedule->fresh());
    }

    /**
     * Duyệt lịch — set approved_by, approved_at.
     */
    public function approve(Schedule $schedule): Schedule
    {
        $schedule->update([
            'status'      => ScheduleStatusEnum::Approved,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        return $this->show($schedule->fresh());
    }

    /**
     * Từ chối lịch — set rejection_note.
     */
    public function reject(Schedule $schedule, string $note): Schedule
    {
        $schedule->update([
            'status'         => ScheduleStatusEnum::Rejected,
            'rejection_note' => $note,
        ]);
        return $this->show($schedule->fresh());
    }

    /**
     * Sao chép lịch sang ngày khác.
     */
    public function duplicate(Schedule $schedule, string $date): Schedule
    {
        return DB::transaction(function () use ($schedule, $date) {
            $new = $schedule->replicate(['approved_by', 'approved_at', 'rejection_note', 'created_by', 'updated_by']);
            $new->date              = $date;
            $new->status            = ScheduleStatusEnum::Draft->value;
            $new->parent_schedule_id = $schedule->id;
            $new->save();

            // Clone participants
            foreach ($schedule->participants as $p) {
                $new->participants()->create(
                    $p->only(['user_id', 'display_name', 'position_name', 'is_external', 'sort_order']) +
                    ['organization_id' => $new->organization_id]
                );
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
     * Xuất Excel.
     */
    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(
            new WeeklyScheduleExcelExport($filters),
            ExportFilename::make('lich-cong-tac')
        );
    }

    /**
     * Xuất PDF.
     */
    public function exportPdf(array $filters)
    {
        $exporter = new WeeklySchedulePdfExporter();
        $path = $exporter->generate($filters);
        return response()->download($path, ExportFilename::make('lich-cong-tac', 'pdf'))->deleteFileAfterSend(true);
    }

    /**
     * Xuất Word.
     */
    public function exportWord(array $filters)
    {
        $schedules = Schedule::with(['host', 'driver', 'participants.user'])
            ->filter($filters)
            ->orderBy('date')->orderBy('session')->orderBy('sort_order')
            ->get();

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText("LỊCH CÔNG TÁC", ['bold' => true, 'size' => 16]);

        foreach ($schedules as $s) {
            $section->addText("- {$s->title} ({$s->date->format('d/m/Y')})");
        }

        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $filename  = ExportFilename::make('lich-cong-tac', 'docx');
        
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $path      = "{$tempDir}/{$filename}";
        $objWriter->save($path);
        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function syncParticipants(Schedule $schedule, array $participants): void
    {
        $schedule->participants()->delete();
        foreach ($participants as $i => $p) {
            $schedule->participants()->create(array_merge($p, [
                'organization_id' => $schedule->organization_id,
                'sort_order'      => $i,
            ]));
        }
    }

    private function syncReminders(Schedule $schedule, array $reminders): void
    {
        $schedule->reminders()->delete();
        foreach ($reminders as $r) {
            $schedule->reminders()->create(array_merge($r, [
                'organization_id' => $schedule->organization_id,
                'created_by'      => Auth::id(),
            ]));
        }
    }
}
