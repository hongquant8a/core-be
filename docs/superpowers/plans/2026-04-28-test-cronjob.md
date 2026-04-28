# Test Cronjob `notifications:process-reminders` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verify the every-minute cronjob fires correctly with full datetime inputs (HH:MM:SS), under `Asia/Ho_Chi_Minh` timezone with no UTC conversion, and cover untested branches in `ProcessRemindersCommand::fireReminder()`.

**Architecture:** Two parallel tracks — (A) 18 PHPUnit Feature tests added to existing/new test files, (B) extend `SimulateReminderTimingCommand` with 7 new options + write a 10-scenario manual checklist. No production code changes.

**Tech Stack:** Laravel 11, PHPUnit, Carbon (Asia/Ho_Chi_Minh), MySQL, Spatie permissions.

**User preference:** Single commit at the end (per memory feedback `feedback_fewer_commits.md`).

---

## File Structure

| Path | Action | Responsibility |
|------|--------|----------------|
| [app/Console/Commands/SimulateReminderTimingCommand.php](../../../app/Console/Commands/SimulateReminderTimingCommand.php) | Modify | Add 7 options + 4 helpers + datetime/timezone output |
| [tests/Feature/Notification/ProcessRemindersCommandTest.php](../../../tests/Feature/Notification/ProcessRemindersCommandTest.php) | Modify | Append 14 tests (4 A1 + 10 A2) + 2 helpers |
| `tests/Feature/Notification/ProcessRemindersTimezoneTest.php` | Create | 4 timezone tests (A3) |
| `docs/superpowers/specs/2026-04-28-test-cronjob-checklist.md` | Create | 10-scenario manual checklist |

Files left untouched: `app/Services/Notification/Console/ProcessRemindersCommand.php`, `ReminderScheduler.php`, `NotificationDispatcher.php`, `SendDeliveryJob.php`.

---

## Task 1: Extend `SimulateReminderTimingCommand` with new options

**Files:**
- Modify: `app/Console/Commands/SimulateReminderTimingCommand.php`

- [ ] **Step 1: Add new option signatures**

Edit the `$signature` block. Insert these lines before the closing `}` of the heredoc:

```php
        {--deadline-at= : End_at tuyệt đối Y-m-d H:i:s, override --deadline-min}
        {--users=1 : Số user assigned (1-10), override --assignee-email}
        {--force-status-after= : Format Nm:done|cancelled — sau N phút wait, update item status}
        {--check-tz : In ra block timezone debug ở đầu run}
        {--no-organization : Tạo document với organization_id=0 (test cancel branch)}
        {--empty-channels : Override schedule channels = []}
        {--cross-org-schedule : Tạo schedule ở org khác item (test cancel branch)}
```

- [ ] **Step 2: Read new options at start of `handle()`**

After the existing `$waitMin = (int) $this->option('wait-min');` line, append:

```php
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
```

- [ ] **Step 3: Print timezone block when `--check-tz`**

Right after the `setPermissionsTeamId(...)` line (just after the org log), add:

```php
        if ($checkTz) {
            $this->printTimezoneCheck();
        }
```

- [ ] **Step 4: Use absolute deadline if provided**

Replace the line `$deadline = now()->addMinutes($deadlineMin);` with:

```php
        $deadline = $deadlineAt ?? now()->addMinutes($deadlineMin);
```

- [ ] **Step 5: Switch document creation to honor `--no-organization`**

Find the `$document = TaskAssignmentDocument::create([...])` call. Replace its `organization_id`-bearing block. The model currently doesn't pass org explicitly — the cronjob reads from `document.organization_id`. Add an explicit `organization_id` field:

```php
            $document = TaskAssignmentDocument::create([
                'task_assignment_type_id' => $typeId,
                'name' => 'SIM Timing: '.now()->format('H:i:s'),
                'status' => 'issued',
                'issued_at' => now(),
                'organization_id' => $noOrg ? 0 : $organization->id,
                'created_by' => $manager->id,
                'updated_by' => $manager->id,
            ]);
```

- [ ] **Step 6: Switch reminder_at output format to full datetime**

In the `$this->table([...], $reminders->map(...))` block (Step 3 of original handle), change:

```php
                    $r->remind_at->format('H:i:s'),
```

to:

```php
                    $r->remind_at->format('Y-m-d H:i:s'),
```

And in the final state table block, change `$r->remind_at->format('H:i:s')` → `$r->remind_at->format('Y-m-d H:i:s')` and `$r->fired_at?->format('H:i:s')` → `$r->fired_at?->format('Y-m-d H:i:s')`.

Also in the wait loop, change:

```php
                    $this->line(now()->format('H:i:s')." → Reminder #{$r->id} ...");
```

to:

```php
                    $this->line(now()->format('Y-m-d H:i:s')." → Reminder #{$r->id} ...");
```

- [ ] **Step 7: Apply `--users=N` (assign extra users)**

After the existing `DB::table('task_assignment_item_user')->insert([...])` (the single-user insert), add:

```php
            if ($usersCount > 1) {
                $this->assignAdditionalUsers($item->id, $usersCount - 1, $deptId, $assignee->id);
            }
```

- [ ] **Step 8: Apply `--empty-channels` and `--cross-org-schedule` in `setupShortSchedules`**

Modify the call site:

```php
        $this->setupShortSchedules($channels, $beforeMin, $afterMin, $organization->id, $crossOrg);
```

Update the method signature and inside the foreach add `$emptyChannels` handling. Replace the entire `setupShortSchedules` method with:

```php
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
```

- [ ] **Step 9: Add `applyForceStatus` inside the wait loop**

The current wait loop body is:

```php
            while (now()->lt($endAt)) {
                $firedReminders = TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
                    ->where('status', 'fired')
                    ->whereNotIn('id', $previousFiredIds)
                    ->get();

                foreach ($firedReminders as $r) {
                    $this->line(now()->format('Y-m-d H:i:s')." → Reminder #{$r->id} ({$r->moment}) FIRED at {$r->fired_at->format('Y-m-d H:i:s')} (expected remind_at={$r->remind_at->format('Y-m-d H:i:s')})");
                    $previousFiredIds[] = $r->id;
                }

                sleep(30);
            }
```

Replace with:

```php
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
```

- [ ] **Step 10: Add 4 new private helpers at end of class**

Append the following methods inside the class (before the closing `}`):

```php
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
```

- [ ] **Step 11: Smoke-run the command (manual verify)**

Run from project root:

```bash
php artisan notification:simulate-reminder --check-tz --wait-min=0 --no-cleanup
```

Expected output:
- A `=== Timezone check ===` block at top showing `app.timezone: Asia/Ho_Chi_Minh`, both raw timestamps within 5s.
- The reminder timeline table shows `remind_at` in `Y-m-d H:i:s` format.
- Exits cleanly with 0.

If `--check-tz` block is missing, re-check Step 3. Cleanup leftover rows manually if needed (`--no-cleanup` was set).

---

## Task 2: Write the manual E2E checklist

**Files:**
- Create: `docs/superpowers/specs/2026-04-28-test-cronjob-checklist.md`

- [ ] **Step 1: Create the checklist file with 10 scenarios**

```markdown
# Cronjob `notifications:process-reminders` — Manual Test Checklist

**Date:** 2026-04-28
**Companion command:** `notification:simulate-reminder` (extended in plan 2026-04-28)
**Spec:** [2026-04-28-test-cronjob-design.md](2026-04-28-test-cronjob-design.md)

## Pre-flight (run once)

```
Terminal 1: php artisan schedule:work
Terminal 2: php artisan notification:simulate-reminder <options>
```

Confirm `Asia/Ho_Chi_Minh` is the active timezone:
```bash
php -r "echo date_default_timezone_get();"
```

Expected: `Asia/Ho_Chi_Minh` (or whatever `APP_TIMEZONE` you set; never assume UTC).

## Scenarios

### 1. Happy path basic

Command:
```
php artisan notification:simulate-reminder --wait-min=8
```

Verify (during/after run):
- 3 reminders printed in timeline (before / on / after).
- During wait, console logs `→ Reminder #N (before|on|after) FIRED at ...` for each.
- DB: `SELECT id, moment, status, fired_at FROM task_assignment_reminders ORDER BY id DESC LIMIT 3;` — all `fired`, `fired_at` not null.
- DB: `SELECT COUNT(*) FROM notifications WHERE notifiable_id = <item_id>;` — equals 3.
- DB: `SELECT COUNT(*) FROM notification_deliveries WHERE notification_id IN (...);` — equals 3 (1 per notification, channel=mail).

### 2. Datetime with non-zero seconds

Command:
```
php artisan notification:simulate-reminder --deadline-at='2026-04-28 14:37:42' --before-min=2 --after-min=2 --wait-min=10
```

(Adjust the date to a future moment > now+2min when running.)

Verify:
- Timeline `remind_at` column shows `:42` seconds preserved (e.g. `2026-04-28 14:35:42` for the before reminder).
- Reminder fires on the first cron tick where `now() >= remind_at` (i.e. the minute `:36` for `remind_at = :35:42`).
- Final state table `Delay (s)` column is between 0 and 60.

### 3. Timezone check

Command:
```
php artisan notification:simulate-reminder --check-tz --wait-min=0
```

Verify:
- `=== Timezone check ===` block prints at top.
- `app.timezone: Asia/Ho_Chi_Minh`.
- `now() TZ: Asia/Ho_Chi_Minh`.
- `now() raw` and `DB NOW()` differ by < 5 seconds (no 7h drift).
- `Match: OK`.

### 4. Multi-channel

Command:
```
php artisan notification:simulate-reminder --channels=mail,sms,zalo --wait-min=8
```

Verify:
- During run: each fired reminder produces 3 deliveries (one per channel).
- DB: `SELECT channel, COUNT(*) FROM notification_deliveries WHERE notification_id IN (SELECT id FROM notifications WHERE notifiable_id = <item_id>) GROUP BY channel;` — 3 rows, each `mail/sms/zalo` with the same count.

### 5. Multi-user

Pre-condition: at least 4 users in DB (manager + 3 assignees).

Command:
```
php artisan notification:simulate-reminder --users=3 --wait-min=8
```

Verify:
- Console logs `Assigned 2 additional users (total 3)` after item create.
- Each fired reminder produces 3 notifications (one per user).
- DB: `SELECT user_id, COUNT(*) FROM notifications WHERE notifiable_id = <item_id> GROUP BY user_id;` — 3 rows of 3 each (3 reminders × 3 users = 9 notifications total).

### 6. Item done mid-wait

Command:
```
php artisan notification:simulate-reminder --force-status-after=3m:done --wait-min=10 --before-min=1 --deadline-min=8
```

Timeline:
- `before` reminder due at `now+7min` (deadline=now+8, before=1min).
- `on` reminder due at `now+8min`.
- `after` reminder due at `now+10min` (after=2min default).
- At `now+3min`, command flips item to `done`.

Verify:
- Console: `[force] ... → Item #N processing_status = done` printed at ~3min mark.
- Reminders that fire BEFORE the 3min flip stay `fired` (none in this timeline — all due >3min).
- Reminders due AFTER the flip become `cancelled`. Final state table: all 3 reminders show `cancelled`.

### 7. Item cancelled mid-wait

Same shape as #6, with `cancelled`:
```
php artisan notification:simulate-reminder --force-status-after=3m:cancelled --wait-min=10 --before-min=1 --deadline-min=8
```

Verify: same as #6, all 3 reminders → `cancelled`.

### 8. No organization

Command:
```
php artisan notification:simulate-reminder --no-organization --wait-min=5 --deadline-min=3 --before-min=1
```

Verify:
- Document created with `organization_id=0` (check `SELECT organization_id FROM task_assignment_documents WHERE id=<doc_id>;`).
- After enough wait (~3-4 min), all reminders → `cancelled` (cron hits the `organizationId === 0` branch).
- Zero notifications: `SELECT COUNT(*) FROM notifications WHERE notifiable_id = <item_id>;` → 0.

### 9. Empty channels

Command:
```
php artisan notification:simulate-reminder --empty-channels --wait-min=5 --deadline-min=3 --before-min=1
```

Verify:
- Schedule line shows `channels=[]`.
- After wait, all reminders → `cancelled` (cron hits `empty($schedule->channels)` branch).
- Zero notifications.

### 10. Cross-org schedule

Command:
```
php artisan notification:simulate-reminder --cross-org-schedule --wait-min=5 --deadline-min=3 --before-min=1
```

Verify:
- Console schedule line shows `(cross-org=N)` where N != item's org id.
- After wait, all reminders → `cancelled` (cron hits `config->organization_id !== organizationId` branch).
- Zero notifications.

## Sign-off

- [ ] All 10 scenarios pass.
- [ ] No leftover rows after cleanup (run `SELECT COUNT(*) FROM notifications WHERE title LIKE 'SIM%';` — should be 0).
- [ ] No errors in `storage/logs/laravel.log` during runs.
```

- [ ] **Step 2: Verify file is readable**

Run:
```bash
head -20 docs/superpowers/specs/2026-04-28-test-cronjob-checklist.md
```

Expected: shows the `# Cronjob ... Manual Test Checklist` heading and Pre-flight section.

---

## Task 3: Add helpers to `ProcessRemindersCommandTest.php`

**Files:**
- Modify: `tests/Feature/Notification/ProcessRemindersCommandTest.php`

- [ ] **Step 1: Add `Carbon` and `Config` imports if missing**

At the top, after the `use Tests\TestCase;` line, add (only those not yet imported):

```php
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
```

- [ ] **Step 2: Add helper `makeItemWithMultipleAssignees`**

After the existing `makeItemWithAssignee` method, add:

```php
    private function makeItemWithMultipleAssignees(int $count, ?string $endAt = null): TaskAssignmentItem
    {
        $item = $this->makeItemWithAssignee(endAt: $endAt);
        $deptId = DB::table('task_assignment_item_user')
            ->where('task_assignment_item_id', $item->id)
            ->value('department_id');

        for ($i = 1; $i < $count; $i++) {
            $u = User::factory()->create(['email' => "r{$i}@test.com"]);
            DB::table('task_assignment_item_user')->insert([
                'task_assignment_item_id' => $item->id,
                'department_id' => $deptId,
                'department_role' => 'member',
                'user_id' => $u->id,
                'assignment_role' => 'member',
                'assignment_status' => 'assigned',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $item->fresh(['users']);
    }
```

- [ ] **Step 3: Add helper `seedDuePendingReminders`**

After `makeItemWithMultipleAssignees`, add a helper to bulk-insert N due reminders quickly (used by chunk test):

```php
    private function seedDuePendingReminders(int $count): void
    {
        $this->enableEvent('reminder_before', []);
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);

        // Bulk insert items + reminders bypassing observer for speed
        $orgId = $this->resolveTestOrganization()->id;
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'code' => 'DBULK', 'name' => 'D', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'D', 'status' => 'issued',
            'organization_id' => $orgId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $itemId = DB::table('task_assignment_items')->insertGetId([
                'task_assignment_document_id' => $docId,
                'task_assignment_item_type_id' => $itemTypeId,
                'name' => "Bulk {$i}",
                'priority' => 'normal',
                'deadline_type' => 'has_deadline',
                'end_at' => now()->addMinutes(30),
                'processing_status' => 'todo',
                'completion_percent' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('task_assignment_item_user')->insert([
                'task_assignment_item_id' => $itemId,
                'user_id' => $user->id,
                'department_id' => $deptId,
                'department_role' => 'member',
                'assignment_status' => 'assigned',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('task_assignment_reminders')->insert([
                'task_assignment_item_id' => $itemId,
                'notification_schedule_id' => $schedule->id,
                'moment' => 'before',
                'remind_at' => now()->subMinute(),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
```

- [ ] **Step 4: Run existing tests to confirm no regression**

```bash
php artisan test --filter=ProcessRemindersCommandTest
```

Expected: all 5 existing tests pass.

---

## Task 4: A1 — Time-precision tests (4 tests)

**Files:**
- Modify: `tests/Feature/Notification/ProcessRemindersCommandTest.php`

- [ ] **Step 1: Append the 4 A1 tests at the end of the class**

```php
    public function test_fires_when_remind_at_equals_now_to_the_minute(): void
    {
        Carbon::setTestNow('2026-04-28 14:37:00');
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:00');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame('fired', TaskAssignmentReminder::first()->status);
        $this->assertSame(1, Notification::count());
    }

    public function test_does_not_fire_one_second_before_remind_at(): void
    {
        Carbon::setTestNow('2026-04-28 14:36:00');
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:00');
        Carbon::setTestNow('2026-04-28 14:36:59');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame('pending', TaskAssignmentReminder::first()->status);
        $this->assertSame(0, Notification::count());
    }

    public function test_handles_end_at_with_non_zero_seconds(): void
    {
        Carbon::setTestNow('2026-04-28 14:38:00');
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:42');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame('fired', TaskAssignmentReminder::first()->status);
        $this->assertSame(1, Notification::count());
    }

    public function test_remind_at_stored_with_full_datetime_precision(): void
    {
        Carbon::setTestNow('2026-04-28 14:00:00');
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 30, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:42');

        $reminder = TaskAssignmentReminder::first();
        $this->assertSame(
            '2026-04-28 15:07:42',
            $reminder->remind_at->format('Y-m-d H:i:s'),
            'remind_at must preserve seconds (end_at - 30min)'
        );
    }
```

- [ ] **Step 2: Run only A1 tests**

```bash
php artisan test --filter='test_fires_when_remind_at_equals_now|test_does_not_fire_one_second|test_handles_end_at_with_non_zero|test_remind_at_stored'
```

Expected: 4 tests pass.

If `test_remind_at_stored_with_full_datetime_precision` fails: check that `ReminderScheduler::computeRemindAt` uses `Carbon::copy()->subMinutes()` (it does — line 80) — failure here would mean a regression in scheduler.

---

## Task 5: A2 — Multi-X tests (3 tests)

**Files:**
- Modify: `tests/Feature/Notification/ProcessRemindersCommandTest.php`

- [ ] **Step 1: Append 3 multi-X tests**

```php
    public function test_fires_before_on_after_in_single_run(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->enableEvent('reminder_on', []);
        $this->enableEvent('reminder_after', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->addReminderSchedule('reminder_on', 'on', null, ['mail']);
        $this->addReminderSchedule('reminder_after', 'after', 60, ['mail']);
        // end_at 30 min ago → before(end-60)=90min ago, on=30min ago, after(end+60)=30min in future
        // Make end_at = now - 90min so before(end-60)=150min ago, on=90min ago, after=30min ago — all 3 due
        $this->makeItemWithAssignee(endAt: now()->subMinutes(90)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(3, TaskAssignmentReminder::where('status', 'fired')->count());
        $this->assertSame(3, Notification::count());

        $eventKeys = Notification::pluck('event_key')->sort()->values()->all();
        $this->assertSame(['reminder_after', 'reminder_before', 'reminder_on'], $eventKeys);
    }

    public function test_fires_for_each_assigned_user(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithMultipleAssignees(3, endAt: now()->addMinutes(30)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(3, Notification::count());
        $this->assertSame(3, NotificationDelivery::count());
        Queue::assertPushed(SendDeliveryJob::class, 3);
    }

    public function test_fires_for_each_channel_in_schedule(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail', 'sms', 'zalo']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(1, Notification::count());
        $this->assertSame(3, NotificationDelivery::count());
        $channels = NotificationDelivery::pluck('channel')->sort()->values()->all();
        $this->assertSame(['mail', 'sms', 'zalo'], $channels);
        Queue::assertPushed(SendDeliveryJob::class, 3);
    }
```

- [ ] **Step 2: Run only the new tests**

```bash
php artisan test --filter='test_fires_before_on_after|test_fires_for_each_assigned_user|test_fires_for_each_channel'
```

Expected: 3 tests pass.

---

## Task 6: A2 — Chunking & no-refire tests (2 tests)

**Files:**
- Modify: `tests/Feature/Notification/ProcessRemindersCommandTest.php`

- [ ] **Step 1: Append 2 tests**

```php
    public function test_processes_more_than_100_reminders_in_chunks(): void
    {
        Queue::fake();
        $this->seedDuePendingReminders(150);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(150, TaskAssignmentReminder::where('status', 'fired')->count());
        $this->assertSame(0, TaskAssignmentReminder::where('status', 'pending')->count());
    }

    public function test_does_not_refire_already_fired_reminder(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        // Mark existing reminder as already fired
        TaskAssignmentReminder::query()->update([
            'status' => 'fired',
            'fired_at' => now()->subMinutes(5),
        ]);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        Queue::assertNotPushed(SendDeliveryJob::class);
    }
```

- [ ] **Step 2: Run them**

```bash
php artisan test --filter='test_processes_more_than_100|test_does_not_refire'
```

Expected: 2 tests pass.

If chunked test is slow (> 30s): check `seedDuePendingReminders` uses `DB::table()->insert()` (it does), not Eloquent `create()`. Acceptable up to ~10s.

---

## Task 7: A2 — Cancel-branch tests (5 tests)

**Files:**
- Modify: `tests/Feature/Notification/ProcessRemindersCommandTest.php`

- [ ] **Step 1: Append 5 cancel-branch tests**

```php
    public function test_cancels_when_item_status_is_cancelled(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());
        $item->update(['processing_status' => 'cancelled']);
        TaskAssignmentReminder::query()->update(['status' => 'pending']); // Override observer cancel

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }

    public function test_cancels_when_document_has_no_organization(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        DB::table('task_assignment_documents')
            ->where('id', $item->task_assignment_document_id)
            ->update(['organization_id' => 0]);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }

    public function test_cancels_when_item_hard_deleted(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        // Hard-delete bypassing observer
        DB::table('task_assignment_items')->where('id', $item->id)->delete();

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }

    public function test_cancels_when_schedule_channels_empty(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        // Empty channels after observer creates reminder
        $schedule->update(['channels' => []]);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }

    public function test_cancels_when_schedule_belongs_to_other_organization(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        // Move schedule's parent event_config to a different org
        $otherOrg = \App\Modules\Core\Models\Organization::create([
            'slug' => 'other', 'name' => 'Other', 'status' => 'active',
        ]);
        \App\Modules\Core\Models\NotificationEventConfig::where('id', $schedule->notification_event_config_id)
            ->update(['organization_id' => $otherOrg->id]);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }
```

- [ ] **Step 2: Run only the new tests**

```bash
php artisan test --filter='test_cancels_when_item_status|test_cancels_when_document|test_cancels_when_item_hard|test_cancels_when_schedule_channels|test_cancels_when_schedule_belongs'
```

Expected: 5 tests pass.

---

## Task 8: A3 — Timezone tests (new file)

**Files:**
- Create: `tests/Feature/Notification/ProcessRemindersTimezoneTest.php`

- [ ] **Step 1: Create the new test file**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class ProcessRemindersTimezoneTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
    }

    private function makeItemWithAssignee(?string $endAt = null): TaskAssignmentItem
    {
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'organization_id' => $this->resolveTestOrganization()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'code' => 'TZ-'.uniqid(), 'name' => 'Dept', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'TZTask',
            'priority' => 'normal',
            'deadline_type' => $endAt ? 'has_deadline' : 'no_deadline',
            'end_at' => $endAt,
            'processing_status' => 'todo',
            'completion_percent' => 0,
        ]);
        $u = User::factory()->create();
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $u->id,
            'department_id' => $deptId,
            'department_role' => 'member',
            'assignment_status' => 'assigned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $item->fresh(['users']);
    }

    public function test_now_uses_app_timezone_not_utc(): void
    {
        $this->assertSame(
            'Asia/Ho_Chi_Minh',
            now()->getTimezone()->getName(),
            'now() must follow app.timezone — failure means CI is not running APP_TIMEZONE=Asia/Ho_Chi_Minh'
        );
    }

    public function test_remind_at_comparison_consistent_in_app_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-28 14:37:00', 'Asia/Ho_Chi_Minh'));
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:00');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame('fired', TaskAssignmentReminder::first()->status);
    }

    public function test_no_implicit_utc_shift_when_storing_end_at(): void
    {
        $item = $this->makeItemWithAssignee(endAt: '2026-04-28 15:00:00');

        $raw = DB::table('task_assignment_items')->where('id', $item->id)->value('end_at');

        $this->assertSame(
            '2026-04-28 15:00:00',
            (string) $raw,
            'DB raw end_at must equal input — any -7h or +7h shift means UTC conversion leaked in'
        );
    }

    public function test_command_fires_correctly_under_app_timezone_change(): void
    {
        Config::set('app.timezone', 'UTC');
        date_default_timezone_set('UTC');
        Carbon::setTestNow(Carbon::parse('2026-04-28 07:37:00', 'UTC'));
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 08:37:00');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(
            'fired',
            TaskAssignmentReminder::first()->status,
            'Cron must follow current app.timezone config — failure means hard-coded TZ leaked in'
        );

        // Reset for subsequent tests
        Config::set('app.timezone', 'Asia/Ho_Chi_Minh');
        date_default_timezone_set('Asia/Ho_Chi_Minh');
    }
}
```

- [ ] **Step 2: Run the new test class**

```bash
php artisan test --filter=ProcessRemindersTimezoneTest
```

Expected: 4 tests pass.

If `test_now_uses_app_timezone_not_utc` fails: confirm `APP_TIMEZONE=Asia/Ho_Chi_Minh` in `.env.testing` (or `.env`). Document this in the test docblock if not already.

---

## Task 9: Run full ProcessReminders test suite

- [ ] **Step 1: Run all ProcessReminders-related tests**

```bash
php artisan test --filter='ProcessReminders|ReminderFire'
```

Expected output: all tests in `ProcessRemindersCommandTest` (5 existing + 14 new = 19), `ProcessRemindersTimezoneTest` (4), and `ReminderFireFlowTest` (4 existing) pass — 27 tests total, no failures, no warnings.

If any test fails, fix in place before proceeding (do not commit a broken state).

- [ ] **Step 2: Run the FULL test suite to catch unintended regressions**

```bash
php artisan test
```

Expected: green across the board. If something unrelated breaks, investigate — do not just disable the test.

---

## Task 10: Run the manual checklist

- [ ] **Step 1: Open two terminals; start the scheduler**

Terminal 1:
```bash
php artisan schedule:work
```

Leave it running. It executes the every-minute scheduler.

- [ ] **Step 2: Walk through the 10 scenarios in `2026-04-28-test-cronjob-checklist.md`**

For each scenario in Terminal 2, run the command exactly as documented, verify output + DB state per the "Verify" rows. Tick the box in the checklist file as you go.

If a scenario fails:
- Capture the failing output and DB state.
- Stop and investigate. Most likely the command extension has a bug — go back to Task 1 to diagnose.

- [ ] **Step 3: Confirm the sign-off section**

After all 10 scenarios pass, run the sign-off queries from the checklist (orphan rows + log scan).

---

## Task 11: Final commit

- [ ] **Step 1: Review changed files**

```bash
git status
git diff --stat
```

Expected:
- Modified: `app/Console/Commands/SimulateReminderTimingCommand.php`
- Modified: `tests/Feature/Notification/ProcessRemindersCommandTest.php`
- New: `tests/Feature/Notification/ProcessRemindersTimezoneTest.php`
- New: `docs/superpowers/specs/2026-04-28-test-cronjob-design.md`
- New: `docs/superpowers/specs/2026-04-28-test-cronjob-checklist.md`
- New: `docs/superpowers/plans/2026-04-28-test-cronjob.md`

- [ ] **Step 2: Stage files explicitly (not `git add .`)**

```bash
git add app/Console/Commands/SimulateReminderTimingCommand.php
git add tests/Feature/Notification/ProcessRemindersCommandTest.php
git add tests/Feature/Notification/ProcessRemindersTimezoneTest.php
git add docs/superpowers/specs/2026-04-28-test-cronjob-design.md
git add docs/superpowers/specs/2026-04-28-test-cronjob-checklist.md
git add docs/superpowers/plans/2026-04-28-test-cronjob.md
```

- [ ] **Step 3: Commit (no Co-Authored-By per user preference)**

```bash
git commit -m "test(notification): cronjob coverage for datetime + timezone

- Add 14 PHPUnit tests for ProcessRemindersCommand (time-precision, multi-X, cancel branches, chunking)
- Add 4 timezone tests (no UTC drift, app.timezone follow)
- Extend SimulateReminderTimingCommand with 7 options for manual edge-case testing
- Add manual E2E checklist (10 scenarios)"
```

- [ ] **Step 4: Verify clean tree**

```bash
git status
```

Expected: `nothing to commit, working tree clean`.

---

## Self-Review Notes

- All 18 spec tests are mapped: A1 (Task 4), A2 multi-X (Task 5), A2 chunk/no-refire (Task 6), A2 cancel branches (Task 7), A3 timezone (Task 8). ✓
- All 7 new command options implemented in Task 1. ✓
- All 10 manual scenarios written in Task 2. ✓
- Helper `makeItemWithMultipleAssignees` defined in Task 3 before use in Task 5. ✓
- Helper `seedDuePendingReminders` defined in Task 3 before use in Task 6. ✓
- Single commit at end (Task 11) per user's `feedback_fewer_commits.md`. ✓
- No `Co-Authored-By` per user's `feedback_no_coauthor.md`. ✓
- Type/method names consistent across tasks (e.g. `makeItemWithMultipleAssignees`, `applyForceStatus`, `parseDeadlineAt`). ✓
