<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateReminderTimingCommand extends Command
{
    protected $signature = 'notification:simulate-reminder
        {--manager-email= : Manager email (default: user id=1)}
        {--assignee-email= : Assignee email (default: user id=2)}
        {--organization-id= : ID tổ chức (default: organization đầu tiên)}
        {--channels=mail : Channels (comma-separated)}
        {--deadline-min=5 : Deadline trong N phút (default 5)}
        {--before-min=2 : Nhắc trước deadline N phút (default 2)}
        {--after-min=2 : Nhắc sau deadline N phút (default 2)}
        {--wait-min=8 : Theo dõi trong N phút (default 8, 0=không wait)}
        {--no-cleanup : Giữ data}
        {--deadline-at= : End_at tuyệt đối Y-m-d H:i:s, override --deadline-min}
        {--users=1 : Số user assigned (1-10), override --assignee-email}
        {--force-status-after= : Format Nm:done|cancelled — sau N phút wait, update item status}
        {--check-tz : In ra block timezone debug ở đầu run}
        {--no-organization : Tạo document với organization_id=0 (test cancel branch)}
        {--empty-channels : Override schedule channels = []}
        {--cross-org-schedule : Tạo schedule ở org khác item (test cancel branch)}';

    protected $description = 'Test reminder timing: schedule ngắn (phút), theo dõi cron fire theo thời gian thật';

    public function handle(): int
    {
        $channels = array_filter(array_map('trim', explode(',', $this->option('channels'))));
        $noCleanup = (bool) $this->option('no-cleanup');
        $deadlineMin = (int) $this->option('deadline-min');
        $beforeMin = (int) $this->option('before-min');
        $afterMin = (int) $this->option('after-min');
        $waitMin = (int) $this->option('wait-min');
        $deadlineAt = $this->parseDeadlineAt($this->option('deadline-at'));
        $usersCount = max(1, min(10, (int) $this->option('users')));
        $forceStatus = $this->option('force-status-after');
        $checkTz = (bool) $this->option('check-tz');
        $noOrg = (bool) $this->option('no-organization');
        $emptyChannels = (bool) $this->option('empty-channels');
        $crossOrg = (bool) $this->option('cross-org-schedule');

        if ($emptyChannels) {
            $channels = [];
        }

        if ($beforeMin >= $deadlineMin) {
            $this->error('before-min phải < deadline-min để remind_at không âm từ now');

            return self::FAILURE;
        }

        $this->info('=== Reminder Timing Simulation ===');
        $this->line('Channels: '.implode(', ', $channels));
        $this->line("Deadline: now + {$deadlineMin} min");
        $this->line("Reminder before: {$beforeMin} min trước deadline");
        $this->line('Reminder on: đúng deadline');
        $this->line("Reminder after: {$afterMin} min sau deadline");
        $this->newLine();

        $organization = $this->resolveOrganization();
        setPermissionsTeamId($organization->id);
        $this->line("Organization: #{$organization->id} {$organization->name}");

        if ($checkTz) {
            $this->printTimezoneCheck();
        }

        $manager = $this->resolveUser($this->option('manager-email'), 'manager');
        $assignee = $this->resolveUser($this->option('assignee-email'), 'assignee');

        $this->info('Step 1: Replace reminder schedules với short intervals...');
        $this->setupShortSchedules($channels, $beforeMin, $afterMin, $organization->id, $crossOrg);
        $this->newLine();

        $createdIds = ['document' => null, 'item' => null];

        try {
            $deadline = $deadlineAt ?? now()->addMinutes($deadlineMin);

            $deadlineNote = $deadlineAt ? 'absolute' : "now+{$deadlineMin}min";
            $this->info("Step 2: Create item với deadline {$deadline->format('Y-m-d H:i:s')} ({$deadlineNote})...");
            $typeId = $this->getOrCreate('task_assignment_types');
            $itemTypeId = $this->getOrCreate('task_assignment_item_types');
            $deptId = DB::table('task_assignment_departments')->insertGetId([
                'name' => 'SIM', 'code' => 'SIM-'.uniqid(), 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $document = TaskAssignmentDocument::create([
                'task_assignment_type_id' => $typeId,
                'name' => 'SIM Timing: '.now()->format('H:i:s'),
                'status' => 'issued',
                'issued_at' => now(),
                'organization_id' => $noOrg ? 0 : $organization->id,
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]);
            $createdIds['document'] = $document->id;

            $item = TaskAssignmentItem::create([
                'task_assignment_document_id' => $document->id,
                'task_assignment_item_type_id' => $itemTypeId,
                'name' => 'SIM: Test timing',
                'priority' => 'normal',
                'deadline_type' => 'has_deadline',
                'end_at' => $deadline,
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

            if ($usersCount > 1) {
                $this->assignAdditionalUsers($item->id, $usersCount - 1, $deptId, $assignee->id);
            }

            $this->newLine();
            $this->info('Step 3: Reminder timeline (expected):');
            $reminders = TaskAssignmentReminder::with('schedule')
                ->where('task_assignment_item_id', $item->id)
                ->orderBy('remind_at')
                ->get();
            $this->table(
                ['ID', 'Moment', 'Label', 'remind_at', 'Phút từ now'],
                $reminders->map(fn ($r) => [
                    $r->id,
                    $r->moment,
                    $r->schedule?->label ?? '—',
                    $r->remind_at->format('Y-m-d H:i:s'),
                    round(now()->diffInSeconds($r->remind_at, false) / 60, 1),
                ])->all(),
            );
            $this->newLine();

            if ($waitMin === 0) {
                $this->line('Wait disabled. Dùng --wait-min=N để theo dõi cron fire.');
                $this->line('Hoặc manual: php artisan tinker → check Notification + NotificationDelivery.');

                return self::SUCCESS;
            }

            $this->info("Step 4: Theo dõi {$waitMin} phút — poll mỗi 30s...");
            $this->line('(Cron phải chạy: Schedule::command(ProcessRemindersCommand)->everyMinute() đã register)');
            $this->line('Chạy song song: php artisan schedule:work');
            $this->newLine();

            $endAt = now()->addMinutes($waitMin);
            $previousFiredIds = [];

            $loopStartedAt = now();
            $forceApplied = false;
            while (now()->lt($endAt)) {
                $firedReminders = TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
                    ->where('status', 'fired')
                    ->whereNotIn('id', $previousFiredIds)
                    ->get();

                foreach ($firedReminders as $r) {
                    $this->line(now()->format('Y-m-d H:i:s')." → Reminder #{$r->id} ({$r->moment}) FIRED at {$r->fired_at->format('Y-m-d H:i:s')} (expected remind_at={$r->remind_at->format('Y-m-d H:i:s')})");
                    $previousFiredIds[] = $r->id;
                }

                if ($forceStatus && ! $forceApplied) {
                    $forceApplied = $this->applyForceStatus($item->id, $loopStartedAt, $forceStatus);
                }

                sleep(30);
            }
            $this->newLine();

            $this->info('=== Final state ===');
            $finalReminders = TaskAssignmentReminder::with('schedule')
                ->where('task_assignment_item_id', $item->id)
                ->orderBy('remind_at')
                ->get();
            $this->table(
                ['ID', 'Moment', 'remind_at', 'Status', 'fired_at', 'Delay (s)'],
                $finalReminders->map(fn ($r) => [
                    $r->id,
                    $r->moment,
                    $r->remind_at->format('Y-m-d H:i:s'),
                    $r->status,
                    $r->fired_at?->format('Y-m-d H:i:s') ?? '—',
                    $r->fired_at ? $r->remind_at->diffInSeconds($r->fired_at, false) : '—',
                ])->all(),
            );
            $this->newLine();

            $this->info('=== Notifications created ===');
            $notis = Notification::where('notifiable_id', $item->id)->with('deliveries')->get();
            foreach ($notis as $n) {
                $delv = $n->deliveries->map(fn ($d) => "{$d->channel}={$d->status}".($d->sent_at ? " ({$d->sent_at->format('H:i:s')})" : ''))->implode(', ');
                $this->line("  #{$n->id} {$n->event_key} user=#{$n->user_id} [{$delv}]");
            }
        } finally {
            if (! $noCleanup) {
                $this->newLine();
                $this->info('Cleanup...');
                if ($createdIds['item']) {
                    Notification::where('notifiable_type', (new TaskAssignmentItem())->getMorphClass())
                        ->where('notifiable_id', $createdIds['item'])
                        ->delete();
                    TaskAssignmentItem::find($createdIds['item'])?->delete();
                }
                if ($createdIds['document']) {
                    TaskAssignmentDocument::find($createdIds['document'])?->delete();
                }
                $this->line('  Done.');
            }
        }

        return self::SUCCESS;
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
            $u = User::where('email', $email)->first();
            if (! $u) {
                $this->error("User {$role} '{$email}' not found");
                exit(1);
            }

            return $u;
        }
        $u = User::orderBy('id')->skip($role === 'manager' ? 0 : 1)->first();
        if (! $u) {
            $this->error("No user for {$role}");
            exit(1);
        }

        return $u;
    }

    private function setupShortSchedules(array $channels, int $beforeMin, int $afterMin, int $organizationId, bool $crossOrg = false): void
    {
        $moduleKey = NotificationModuleEnum::TaskAssignment->value;
        $targetOrgId = $crossOrg ? $this->resolveCrossOrgId($organizationId) : $organizationId;

        foreach ([NotificationEventEnum::ReminderBefore, NotificationEventEnum::ReminderOn, NotificationEventEnum::ReminderAfter] as $event) {
            $config = NotificationEventConfig::firstOrCreate(
                ['module_key' => $moduleKey, 'event_key' => $event->value, 'organization_id' => $targetOrgId],
                ['enabled' => true]
            );
            $config->update(['enabled' => true]);
            $config->schedules()->delete();

            $spec = match ($event) {
                NotificationEventEnum::ReminderBefore => ['moment' => 'before', 'offset' => $beforeMin, 'label' => "Nhắc trước {$beforeMin} phút"],
                NotificationEventEnum::ReminderOn => ['moment' => 'on', 'offset' => null, 'label' => 'Đến hạn'],
                NotificationEventEnum::ReminderAfter => ['moment' => 'after', 'offset' => $afterMin, 'label' => "Trễ {$afterMin} phút"],
            };

            NotificationSchedule::create([
                'notification_event_config_id' => $config->id,
                'moment' => $spec['moment'],
                'offset_minutes' => $spec['offset'],
                'channels' => $channels,
                'label' => $spec['label'],
                'sort_order' => 0,
            ]);
        }

        $orgNote = $crossOrg ? " (cross-org={$targetOrgId})" : '';
        $chanNote = empty($channels) ? '[]' : implode(',', $channels);
        $this->line("  Schedules set: before={$beforeMin}min, on=deadline, after={$afterMin}min | channels={$chanNote}{$orgNote}");
    }

    private function getOrCreate(string $table): int
    {
        $existing = DB::table($table)->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table($table)->insertGetId([
            'name' => 'SIM', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function parseDeadlineAt(?string $input): ?\Carbon\Carbon
    {
        if (! $input) {
            return null;
        }
        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $input);
        } catch (\Throwable $e) {
            $this->error("--deadline-at phải đúng format Y-m-d H:i:s, nhận: {$input}");
            exit(1);
        }
    }

    private function applyForceStatus(int $itemId, \Carbon\Carbon $loopStartedAt, string $spec): bool
    {
        if (! preg_match('/^(\d+)m:(done|cancelled)$/', $spec, $m)) {
            $this->error("--force-status-after format sai (cần Nm:done|cancelled): {$spec}");
            return true;
        }
        $delayMin = (int) $m[1];
        $newStatus = $m[2];
        if (now()->lt($loopStartedAt->copy()->addMinutes($delayMin))) {
            return false;
        }
        TaskAssignmentItem::where('id', $itemId)->update(['processing_status' => $newStatus]);
        $this->line('[force] '.now()->format('Y-m-d H:i:s')." → Item #{$itemId} processing_status = {$newStatus}");
        return true;
    }

    private function assignAdditionalUsers(int $itemId, int $count, int $deptId, int $excludeUserId): void
    {
        $users = User::whereKeyNot($excludeUserId)->orderBy('id')->limit($count)->get();
        foreach ($users as $u) {
            DB::table('task_assignment_item_user')->insert([
                'task_assignment_item_id' => $itemId,
                'user_id' => $u->id,
                'department_id' => $deptId,
                'department_role' => 'member',
                'assignment_status' => 'assigned',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->line("  Assigned {$users->count()} additional users (total ".($users->count() + 1).')');
    }

    private function resolveCrossOrgId(int $primaryOrgId): int
    {
        $other = Organization::where('id', '!=', $primaryOrgId)->orderBy('id')->first();
        if (! $other) {
            $other = Organization::create(['slug' => 'cross-org-test', 'name' => 'Cross-Org Test', 'status' => 'active']);
        }
        return $other->id;
    }

    private function printTimezoneCheck(): void
    {
        $appTz = config('app.timezone');
        $nowTz = now()->getTimezone()->getName();
        $nowRaw = now()->format('Y-m-d H:i:s');
        $dbNow = DB::selectOne('SELECT NOW() AS n')->n ?? '?';
        $diffSec = abs(now()->diffInSeconds(\Carbon\Carbon::parse($dbNow)));
        $match = $diffSec < 5 ? 'OK' : "DRIFT (diff={$diffSec}s)";

        $this->info('=== Timezone check ===');
        $this->line("  app.timezone: {$appTz}");
        $this->line("  now() TZ:    {$nowTz}");
        $this->line("  now() raw:   {$nowRaw}");
        $this->line("  DB NOW():    {$dbNow}");
        $this->line("  Match:       {$match}");
        $this->newLine();
    }
}
