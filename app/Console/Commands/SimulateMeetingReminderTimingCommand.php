<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingReminder;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Simulate meeting reminder timing — clone từ SimulateReminderTimingCommand cho TaskAssignment.
 *
 * Flow:
 *  1. Replace 3 reminder schedules (before/on/after) bằng intervals ngắn (phút).
 *  2. Tạo meeting test với start_time = now + N phút.
 *  3. MeetingObserver auto schedule 3 MeetingReminder pending.
 *  4. Theo dõi cron `notifications:process-reminders` fire dần — log timeline thực vs expected.
 *  5. Cleanup data test.
 *
 * Yêu cầu: scheduler đã đăng ký (bootstrap/console.php) hoặc chạy `schedule:work` song song.
 */
class SimulateMeetingReminderTimingCommand extends Command
{
    protected $signature = 'notification:simulate-meeting-reminder
        {--organizer-email= : Chair user email (default: user id=1)}
        {--participant-email= : Participant user email (default: user id=2)}
        {--organization-id= : ID tổ chức (default: organization đầu tiên)}
        {--channels=mail : Channels (comma-separated)}
        {--start-min=5 : Meeting start_time = now + N phút (default 5)}
        {--before-min=2 : Nhắc trước start N phút (default 2)}
        {--after-min=2 : Nhắc sau start N phút (default 2)}
        {--wait-min=8 : Theo dõi N phút (default 8, 0=không wait)}
        {--no-cleanup : Giữ data}
        {--start-at= : start_time tuyệt đối Y-m-d H:i:s, override --start-min}
        {--check-tz : In ra block timezone debug ở đầu run}
        {--empty-channels : Override schedule channels = [] (test cancel branch)}';

    protected $description = 'Test meeting reminder timing: schedule ngắn (phút), theo dõi cron fire theo thời gian thật';

    public function handle(): int
    {
        $channels = array_filter(array_map('trim', explode(',', $this->option('channels'))));
        $noCleanup = (bool) $this->option('no-cleanup');
        $startMin = (int) $this->option('start-min');
        $beforeMin = (int) $this->option('before-min');
        $afterMin = (int) $this->option('after-min');
        $waitMin = (int) $this->option('wait-min');
        $startAt = $this->parseStartAt($this->option('start-at'));
        $checkTz = (bool) $this->option('check-tz');
        $emptyChannels = (bool) $this->option('empty-channels');

        if ($emptyChannels) {
            $channels = [];
        }

        if ($beforeMin >= $startMin) {
            $this->error('before-min phải < start-min để remind_at không âm từ now');

            return self::FAILURE;
        }

        $this->info('=== Meeting Reminder Timing Simulation ===');
        $this->line('Channels: '.implode(', ', $channels));
        $this->line("Meeting start: now + {$startMin} min");
        $this->line("Reminder before: {$beforeMin} min trước start");
        $this->line('Reminder on: đúng start_time');
        $this->line("Reminder after: {$afterMin} min sau start");
        $this->newLine();

        $organization = $this->resolveOrganization();
        setPermissionsTeamId($organization->id);
        $this->line("Organization: #{$organization->id} {$organization->name}");

        if ($checkTz) {
            $this->printTimezoneCheck();
        }

        $organizer = $this->resolveUser($this->option('organizer-email'), 'organizer');
        $participant = $this->resolveUser($this->option('participant-email'), 'participant');
        auth()->login($organizer);

        $this->info('Step 1: Replace meeting reminder schedules với short intervals...');
        $this->setupShortSchedules($channels, $beforeMin, $afterMin, $organization->id);
        $this->newLine();

        $createdIds = ['meeting' => null, 'attendee' => null, 'participant' => null];

        try {
            $start = $startAt ?? now()->addMinutes($startMin);
            $startNote = $startAt ? 'absolute' : "now+{$startMin}min";
            $this->info("Step 2: Create meeting với start_time {$start->format('Y-m-d H:i:s')} ({$startNote})...");

            $typeId = $this->getOrCreate('meeting_types');
            $locationId = $this->getOrCreate('meeting_locations');

            $meeting = Meeting::create([
                'organization_id' => $organization->id,
                'meeting_type_id' => $typeId,
                'meeting_location_id' => $locationId,
                'title' => 'SIM Meeting Timing: '.now()->format('H:i:s'),
                'is_public' => false,
                'content' => 'Simulation meeting for reminder testing',
                'start_time' => $start,
                'end_time' => $start->copy()->addHour(),
                'status' => 'published',
                'view_count' => 0,
                'published_at' => now(),
                'created_by' => $organizer->id,
                'updated_by' => $organizer->id,
            ]);
            $createdIds['meeting'] = $meeting->id;

            // Tạo MeetingAttendee link tới participant user (cần unique trong org).
            $attendee = MeetingAttendee::firstOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $participant->id],
                [
                    'position_name' => 'SIM Position',
                    'department_name' => 'SIM Department',
                    'status' => 'active',
                ]
            );
            if (! $attendee->wasRecentlyCreated) {
                // Reuse — không cleanup
                $createdIds['attendee'] = null;
            } else {
                $createdIds['attendee'] = $attendee->id;
            }

            $partRow = MeetingParticipant::create([
                'organization_id' => $organization->id,
                'meeting_id' => $meeting->id,
                'meeting_attendee_id' => $attendee->id,
                'display_name' => $participant->name,
                'email' => $participant->email,
                'response_status' => 'accepted',
                'responded_at' => now(),
            ]);
            $createdIds['participant'] = $partRow->id;

            $this->newLine();
            $this->info('Step 3: Reminder timeline (expected):');
            $reminders = MeetingReminder::with('schedule')
                ->where('meeting_id', $meeting->id)
                ->orderBy('scheduled_at')
                ->get();
            $this->table(
                ['ID', 'Moment', 'Label', 'scheduled_at', 'Phút từ now'],
                $reminders->map(fn ($r) => [
                    $r->id,
                    $r->moment,
                    $r->schedule?->label ?? '—',
                    $r->scheduled_at->format('Y-m-d H:i:s'),
                    round(now()->diffInSeconds($r->scheduled_at, false) / 60, 1),
                ])->all(),
            );
            $this->newLine();

            if ($waitMin === 0) {
                $this->line('Wait disabled. Dùng --wait-min=N để theo dõi cron fire.');

                return self::SUCCESS;
            }

            $this->info("Step 4: Theo dõi {$waitMin} phút — poll mỗi 30s...");
            $this->line('(Cron phải chạy: Schedule::command(ProcessRemindersCommand)->everyMinute() đã register)');
            $this->line('Chạy song song: php artisan schedule:work');
            $this->newLine();

            $endAt = now()->addMinutes($waitMin);
            $previousFiredIds = [];

            while (now()->lt($endAt)) {
                $firedReminders = MeetingReminder::where('meeting_id', $meeting->id)
                    ->where('status', 'fired')
                    ->whereNotIn('id', $previousFiredIds)
                    ->get();

                foreach ($firedReminders as $r) {
                    $this->line(now()->format('Y-m-d H:i:s')." → Reminder #{$r->id} ({$r->moment}) FIRED at {$r->fired_at->format('Y-m-d H:i:s')} (expected scheduled_at={$r->scheduled_at->format('Y-m-d H:i:s')})");
                    $previousFiredIds[] = $r->id;
                }

                sleep(30);
            }
            $this->newLine();

            $this->info('=== Final state ===');
            $finalReminders = MeetingReminder::with('schedule')
                ->where('meeting_id', $meeting->id)
                ->orderBy('scheduled_at')
                ->get();
            $this->table(
                ['ID', 'Moment', 'scheduled_at', 'Status', 'fired_at', 'Delay (s)'],
                $finalReminders->map(fn ($r) => [
                    $r->id,
                    $r->moment,
                    $r->scheduled_at->format('Y-m-d H:i:s'),
                    $r->status,
                    $r->fired_at?->format('Y-m-d H:i:s') ?? '—',
                    $r->fired_at ? $r->scheduled_at->diffInSeconds($r->fired_at, false) : '—',
                ])->all(),
            );
            $this->newLine();

            $this->info('=== Notifications created ===');
            $notis = Notification::where('notifiable_id', $meeting->id)
                ->where('notifiable_type', Meeting::class)
                ->with('deliveries')
                ->get();
            foreach ($notis as $n) {
                $delv = $n->deliveries->map(fn ($d) => "{$d->channel}={$d->status}".($d->sent_at ? " ({$d->sent_at->format('H:i:s')})" : ''))->implode(', ');
                $this->line("  #{$n->id} {$n->event_key} user=#{$n->user_id} [{$delv}]");
            }
        } finally {
            if (! $noCleanup) {
                $this->newLine();
                $this->info('Cleanup...');
                if ($createdIds['meeting']) {
                    Notification::where('notifiable_type', Meeting::class)
                        ->where('notifiable_id', $createdIds['meeting'])
                        ->delete();
                    MeetingReminder::where('meeting_id', $createdIds['meeting'])->delete();
                    if ($createdIds['participant']) {
                        MeetingParticipant::find($createdIds['participant'])?->delete();
                    }
                    Meeting::find($createdIds['meeting'])?->delete();
                }
                if ($createdIds['attendee']) {
                    MeetingAttendee::find($createdIds['attendee'])?->delete();
                }
                $this->line('  Done.');
            } else {
                $this->line('Skip cleanup (--no-cleanup).');
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
        $u = User::orderBy('id')->skip($role === 'organizer' ? 0 : 1)->first();
        if (! $u) {
            $this->error("No user for {$role}");
            exit(1);
        }

        return $u;
    }

    private function setupShortSchedules(array $channels, int $beforeMin, int $afterMin, int $organizationId): void
    {
        $moduleKey = NotificationModuleEnum::Meeting->value;

        $events = [
            NotificationEventEnum::MeetingReminderBefore,
            NotificationEventEnum::MeetingReminderOn,
            NotificationEventEnum::MeetingReminderAfter,
        ];

        foreach ($events as $event) {
            $config = NotificationEventConfig::firstOrCreate(
                ['module_key' => $moduleKey, 'event_key' => $event->value, 'organization_id' => $organizationId],
                ['enabled' => true]
            );
            $config->update(['enabled' => true]);
            $config->schedules()->delete();

            $spec = match ($event) {
                NotificationEventEnum::MeetingReminderBefore => ['moment' => 'before', 'offset' => $beforeMin, 'label' => "Nhắc trước {$beforeMin} phút"],
                NotificationEventEnum::MeetingReminderOn => ['moment' => 'on', 'offset' => null, 'label' => 'Đến giờ'],
                NotificationEventEnum::MeetingReminderAfter => ['moment' => 'after', 'offset' => $afterMin, 'label' => "Sau {$afterMin} phút"],
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

        $chanNote = empty($channels) ? '[]' : implode(',', $channels);
        $this->line("  Schedules set: before={$beforeMin}min, on=start, after={$afterMin}min | channels={$chanNote}");
    }

    private function getOrCreate(string $table): int
    {
        $existing = DB::table($table)->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table($table)->insertGetId([
            'organization_id' => 1,
            'name' => 'SIM',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function parseStartAt(?string $input): ?\Carbon\Carbon
    {
        if (! $input) {
            return null;
        }
        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $input);
        } catch (\Throwable $e) {
            $this->error("--start-at phải đúng format Y-m-d H:i:s, nhận: {$input}");
            exit(1);
        }
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
