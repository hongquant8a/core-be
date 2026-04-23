<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Modules\TaskAssignment\Services\TaskAssignmentDocumentService;
use App\Modules\TaskAssignment\Services\TaskAssignmentItemService;
use App\Modules\TaskAssignment\Services\TaskAssignmentReportService;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateNotificationFlowCommand extends Command
{
    protected $signature = 'notification:simulate
        {--manager-email= : Email của người giao việc (default: user id=1)}
        {--assignee-email= : Email của người được giao việc (default: user id=2)}
        {--organization-id= : ID tổ chức (default: organization đầu tiên)}
        {--channels=mail : Channels gửi (comma-separated: sms,mail,zalo,fcm)}
        {--no-cleanup : Giữ lại data sau khi chạy (mặc định xóa)}';

    protected $description = 'Giả lập full lifecycle notification: ban hành văn bản → báo cáo → xác nhận';

    public function handle(
        TaskAssignmentDocumentService $docSvc,
        TaskAssignmentItemService $itemSvc,
        TaskAssignmentReportService $reportSvc,
    ): int {
        $channels = array_filter(array_map('trim', explode(',', $this->option('channels'))));
        $noCleanup = (bool) $this->option('no-cleanup');

        $this->info('=== Notification Flow Simulation ===');
        $this->line('Channels: '.implode(', ', $channels));
        $this->newLine();

        // 1. Resolve organization + actors + set Spatie team context
        $organization = $this->resolveOrganization();
        setPermissionsTeamId($organization->id);
        $this->line("Organization: #{$organization->id} {$organization->name}");

        $manager = $this->resolveUser($this->option('manager-email'), 'manager');
        $assignee = $this->resolveUser($this->option('assignee-email'), 'assignee');
        $this->line("Manager:  #{$manager->id} {$manager->name} ({$manager->email})");
        $this->line("Assignee: #{$assignee->id} {$assignee->name} ({$assignee->email})");
        $this->newLine();

        // 2. Snapshot initial state + enable configs
        $initialNotiCount = Notification::count();
        $initialReminderCount = TaskAssignmentReminder::count();

        $this->info('Step 1: Enabling event configs for simulation...');
        $this->enableAllEvents($channels, $organization->id);
        $this->newLine();

        $createdIds = ['document' => null, 'item' => null, 'configs_snapshot' => null];

        try {
            // 3. Create document (draft)
            $this->info('Step 2: Creating document (draft)...');
            $deptId = $this->getOrCreateDepartment();
            $typeId = $this->getOrCreateType();
            $itemTypeId = $this->getOrCreateItemType();

            $document = TaskAssignmentDocument::create([
                'task_assignment_type_id' => $typeId,
                'name' => 'SIM: Văn bản giả lập '.now()->format('H:i:s'),
                'status' => 'draft',
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]);
            $createdIds['document'] = $document->id;
            $this->line("  Document created: #{$document->id} '{$document->name}' (status=draft)");
            $this->newLine();

            // 4. Create item with assignee
            $this->info('Step 3: Creating công việc với deadline (2 ngày sau) + gán assignee...');
            $item = TaskAssignmentItem::create([
                'task_assignment_document_id' => $document->id,
                'task_assignment_item_type_id' => $itemTypeId,
                'name' => 'SIM: Rà soát báo cáo',
                'description' => 'Công việc giả lập để test notification flow',
                'priority' => 'normal',
                'deadline_type' => 'has_deadline',
                'end_at' => now()->addDays(2)->setTime(17, 0),
                'processing_status' => 'todo',
                'completion_percent' => 0,
                'assigned_by' => $manager->id,
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]);
            $createdIds['item'] = $item->id;

            DB::table('task_assignment_item_user')->insert([
                'task_assignment_item_id' => $item->id,
                'user_id' => $assignee->id,
                'department_id' => $deptId,
                'department_role' => 'member',
                'assignment_status' => 'assigned',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $reminders = TaskAssignmentReminder::with('schedule')
                ->where('task_assignment_item_id', $item->id)
                ->orderBy('remind_at')
                ->get();
            $this->line("  Item created: #{$item->id} '{$item->name}' (deadline={$item->end_at->format('d/m/Y H:i')})");
            $this->line('  Reminders auto-created by Observer:');
            $this->table(
                ['Label', 'Moment', 'Offset (min)', 'remind_at', 'Expected offset from deadline'],
                $reminders->map(fn ($r) => [
                    $r->schedule?->label ?? '—',
                    $r->moment,
                    $r->schedule?->offset_minutes ?? 'null',
                    $r->remind_at->format('d/m/Y H:i:s'),
                    $this->formatDeadlineDiff($item->end_at, $r->remind_at),
                ])->all(),
            );
            $this->newLine();

            // 4.5. Fire 1 reminder để demo timing chạy đúng
            $this->info('Step 3.5: Demo fire reminder — force remind_at về past + run cron...');
            $firstPending = TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
                ->where('status', 'pending')
                ->orderBy('remind_at')
                ->first();
            if ($firstPending) {
                $firstPending->update(['remind_at' => now()->subMinutes(1)]);
                $this->line("  Forced reminder #{$firstPending->id} ({$firstPending->moment}/{$firstPending->schedule?->label}) remind_at → ".$firstPending->remind_at->format('H:i:s'));
                $this->call('notifications:process-reminders');
                $this->waitForQueue(2);
                $fresh = $firstPending->fresh();
                $this->line("  → Reminder status: {$fresh->status} (fired_at={$fresh->fired_at?->format('H:i:s')})");
                $this->showNotificationsForItem($item->id, 'reminder_'.$firstPending->moment);
            }
            $this->newLine();

            // 5. Issue document → fires DocumentIssued
            $this->info('Step 4: Ban hành văn bản (draft → issued) → bắn event DocumentIssued...');
            $docSvc->changeStatus($document, 'issued');
            $this->line('  → Event DocumentIssued fired. Chờ queue worker process...');
            $this->waitForQueue();
            $this->showNotificationsForItem($item->id, 'document_issued');
            $this->newLine();

            // 6. Assignee submits report → fires TaskCompleted
            $this->info('Step 5: Assignee nộp báo cáo → bắn event TaskCompleted...');
            auth()->login($assignee);
            $report = $reportSvc->store($item, [
                'report_document_content' => 'Báo cáo giả lập từ simulation',
                'completion_percent' => 100,
            ]);
            auth()->logout();
            $this->line('  → Report created. Event TaskCompleted fired. Chờ queue worker process...');
            $this->waitForQueue();
            $this->showNotificationsForItem($item->id, 'task_completed');
            $this->newLine();

            // 7. Manager confirms report → fires TaskConfirmed
            $this->info('Step 6: Manager xác nhận báo cáo → bắn event TaskConfirmed...');
            auth()->login($manager);
            $reportSvc->confirm($report, 'Xác nhận từ simulation');
            auth()->logout();
            $this->line('  → Event TaskConfirmed fired. Chờ queue worker process...');
            $this->waitForQueue();
            $this->showNotificationsForItem($item->id, 'task_confirmed');
            $this->newLine();

            // Wait extra cho SendDeliveryJob process xong các delivery
            $this->line('Chờ worker process nốt các SendDeliveryJob...');
            $this->waitForQueue(5);
            $this->newLine();

            // 8. Summary
            $this->info('=== Summary ===');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Notifications created', Notification::count() - $initialNotiCount],
                    ['Deliveries created', NotificationDelivery::whereHas('notification', fn ($q) => $q->where('notifiable_id', $item->id))->count()],
                    ['Deliveries sent', NotificationDelivery::whereHas('notification', fn ($q) => $q->where('notifiable_id', $item->id))->where('status', 'sent')->count()],
                    ['Deliveries failed', NotificationDelivery::whereHas('notification', fn ($q) => $q->where('notifiable_id', $item->id))->where('status', 'failed')->count()],
                    ['Deliveries skipped', NotificationDelivery::whereHas('notification', fn ($q) => $q->where('notifiable_id', $item->id))->where('status', 'skipped')->count()],
                    ['Deliveries pending (still in queue)', NotificationDelivery::whereHas('notification', fn ($q) => $q->where('notifiable_id', $item->id))->where('status', 'pending')->count()],
                    ['Reminders pending', TaskAssignmentReminder::where('task_assignment_item_id', $item->id)->where('status', 'pending')->count()],
                    ['Reminders cancelled (item done)', TaskAssignmentReminder::where('task_assignment_item_id', $item->id)->where('status', 'cancelled')->count()],
                ],
            );
            $this->newLine();

            if (NotificationDelivery::whereHas('notification', fn ($q) => $q->where('notifiable_id', $item->id))->where('status', 'pending')->exists()) {
                $this->warn('Some deliveries still pending — queue worker chưa chạy hoặc chưa pick up jobs.');
                $this->warn('Chạy: php artisan queue:work --queue=notifications,default');
            }

            $this->info('Xem chi tiết deliveries qua API: GET /api/notifications/logs');
        } finally {
            if (! $noCleanup) {
                $this->newLine();
                $this->info('Cleanup: deleting simulation data...');
                // Xóa notifications polymorphic (không có FK cascade → phải xóa tay).
                // Deliveries cascade theo notification FK nên không cần xóa riêng.
                if ($createdIds['item']) {
                    Notification::where('notifiable_type', TaskAssignmentItem::class)
                        ->where('notifiable_id', $createdIds['item'])
                        ->delete();
                    TaskAssignmentItem::find($createdIds['item'])?->delete();
                }
                if ($createdIds['document']) {
                    TaskAssignmentDocument::find($createdIds['document'])?->delete();
                }
                $this->line('  Done. (Pass --no-cleanup to keep data)');
            }
        }

        return self::SUCCESS;
    }

    private function waitForQueue(int $seconds = 3): void
    {
        sleep($seconds);
    }

    private function formatDeadlineDiff(\Carbon\Carbon $deadline, \Carbon\Carbon $remindAt): string
    {
        $diffMinutes = $deadline->diffInMinutes($remindAt, false);
        if ($diffMinutes > 0) {
            return 'sau hạn '.$diffMinutes.' phút';
        }
        if ($diffMinutes < 0) {
            return 'trước hạn '.abs($diffMinutes).' phút';
        }

        return 'đúng hạn';
    }

    private function resolveOrganization(): Organization
    {
        $id = $this->option('organization-id');
        if ($id) {
            $org = Organization::find($id);
            if (! $org) {
                $this->error("Organization #{$id} not found");
                exit(1);
            }

            return $org;
        }

        $org = Organization::orderBy('id')->first();
        if (! $org) {
            $this->error('Chưa có organization nào trong DB. Seed trước.');
            exit(1);
        }

        return $org;
    }

    private function resolveUser(?string $email, string $role): User
    {
        if ($email) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->error("User {$role} with email '{$email}' not found");
                exit(1);
            }

            return $user;
        }

        $user = User::orderBy('id')->skip($role === 'manager' ? 0 : 1)->first();
        if (! $user) {
            $this->error("No user available for {$role}. Seed users first.");
            exit(1);
        }

        return $user;
    }

    private function enableAllEvents(array $channels, int $organizationId): void
    {
        $moduleKey = NotificationModuleEnum::TaskAssignment->value;

        foreach (NotificationEventEnum::cases() as $event) {
            $config = NotificationEventConfig::firstOrCreate(
                ['module_key' => $moduleKey, 'event_key' => $event->value, 'organization_id' => $organizationId],
                ['enabled' => true]
            );
            $config->update(['enabled' => true]);

            // Non-reminder: ensure instant schedule exists with channels
            if (! str_starts_with($event->value, 'reminder_')) {
                $schedule = NotificationSchedule::firstOrCreate(
                    ['notification_event_config_id' => $config->id, 'moment' => null, 'offset_minutes' => null],
                    ['channels' => $channels, 'label' => 'Instant (simulation)', 'sort_order' => 0]
                );
                $schedule->update(['channels' => $channels]);
            } else {
                // Reminder: update channels cho tất cả schedule đã seed
                $config->schedules()->update(['channels' => json_encode($channels)]);
            }
        }

        $this->line('  Event configs enabled for: '.implode(', ', NotificationEventEnum::values()));
    }

    private function showNotificationsForItem(int $itemId, string $eventKey): void
    {
        $notis = Notification::where('notifiable_id', $itemId)
            ->where('event_key', $eventKey)
            ->with('deliveries')
            ->get();

        if ($notis->isEmpty()) {
            $this->line("  (No notifications for event {$eventKey} yet — listener may still be in queue)");

            return;
        }

        foreach ($notis as $n) {
            $deliverySummary = $n->deliveries->map(fn ($d) => "{$d->channel}={$d->status}")->implode(', ');
            $this->line("  → Notification #{$n->id} user=#{$n->user_id} '{$n->title}' [{$deliverySummary}]");
        }
    }

    private function getOrCreateDepartment(): int
    {
        $existing = DB::table('task_assignment_departments')->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table('task_assignment_departments')->insertGetId([
            'name' => 'SIM Department',
            'code' => 'SIM-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getOrCreateType(): int
    {
        $existing = DB::table('task_assignment_types')->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table('task_assignment_types')->insertGetId([
            'name' => 'SIM Type',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getOrCreateItemType(): int
    {
        $existing = DB::table('task_assignment_item_types')->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'SIM ItemType',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
