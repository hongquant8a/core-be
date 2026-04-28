# Test Cronjob `notifications:process-reminders` — Design

**Date:** 2026-04-28
**Scope:** Verify cronjob behavior with full datetime inputs (time component) under `Asia/Ho_Chi_Minh` timezone, no UTC conversion.

## Context

- Cronjob: `notifications:process-reminders`, registered every minute with `withoutOverlapping()` ([routes/console.php:13-15](../../../routes/console.php#L13-L15)).
- Implementation: [app/Services/Notification/Console/ProcessRemindersCommand.php](../../../app/Services/Notification/Console/ProcessRemindersCommand.php).
- Triggered logic: query `TaskAssignmentReminder` where `status='pending'` AND `remind_at <= now()`, dispatch `Notification` + `NotificationDelivery` per channel, queue `SendDeliveryJob`.
- Timezone: `Asia/Ho_Chi_Minh` (`config/app.php:68`); all datetime columns stored without TZ offset.
- Recent change context: all date inputs in the system now carry full time component (HH:MM:SS), not date-only.

## Goals

1. Verify cronjob fires correctly when `remind_at` has minute/second precision (not just date).
2. Verify no implicit UTC conversion: `now()`, `remind_at`, and DB `NOW()` all agree under app timezone.
3. Cover untested branches in `ProcessRemindersCommand::fireReminder()`.

## Non-goals

- Not modifying `ProcessRemindersCommand` logic.
- Not modifying `NotificationDispatcher`, `SendDeliveryJob` (covered by existing tests).
- Not changing app timezone default.
- Not load/perf testing beyond chunk-100 boundary.

## Approach

Two parallel tracks:
- **(A) Automated PHPUnit Feature tests** — 18 new tests across 2 files.
- **(B) Manual E2E retest** — extend existing `SimulateReminderTimingCommand` with options for edge cases, plus a written checklist of 10 scenarios.

## (A) Automated tests

### A1. Time-precision (4 tests, append to [tests/Feature/Notification/ProcessRemindersCommandTest.php](../../../tests/Feature/Notification/ProcessRemindersCommandTest.php))

| Test | Setup | Assertion |
|------|-------|-----------|
| `test_fires_when_remind_at_equals_now_to_the_minute` | `Carbon::setTestNow('2026-04-28 14:37:00')`, item `end_at='2026-04-28 15:37:00'`, schedule before 60min → `remind_at='14:37:00'` | reminder `fired`, `Notification::count()==1` |
| `test_does_not_fire_one_second_before_remind_at` | `setTestNow('2026-04-28 14:36:59')`, same setup (`remind_at='14:37:00'`) | reminder still `pending`, no notification |
| `test_handles_end_at_with_non_zero_seconds` | `end_at='2026-04-28 15:37:42'`, before 60min → `remind_at='14:37:42'`, `setTestNow('14:38:00')` | reminder `fired` (now > remind_at) |
| `test_remind_at_stored_with_full_datetime_precision` | Item `end_at='2026-04-28 15:37:42'`, schedule before 30min | `TaskAssignmentReminder::first()->remind_at->format('Y-m-d H:i:s') === '2026-04-28 15:07:42'` |

### A2. Coverage gaps (10 tests, append to same file)

| Test | Setup | Assertion |
|------|-------|-----------|
| `test_fires_before_on_after_in_single_run` | 1 item, all 3 events enabled, all reminders due | 3 notifications, 3 reminders `fired`, event_keys map correctly |
| `test_fires_for_each_assigned_user` | 1 item, 3 users assigned to it | `Notification::count()==3`, `NotificationDelivery::count()==3` |
| `test_fires_for_each_channel_in_schedule` | Schedule `channels=['mail','sms','zalo']` | 1 notification, 3 deliveries (one per channel), 3 jobs queued |
| `test_cancels_when_item_status_is_cancelled` | Item `processing_status='cancelled'`, reminder pending | reminder `cancelled`, no notification |
| `test_cancels_when_document_has_no_organization` | `document.organization_id=0` | reminder `cancelled` |
| `test_cancels_when_item_hard_deleted` | Reminder pending, item deleted from DB | reminder `cancelled` (hits `if (! $item)` branch) |
| `test_cancels_when_schedule_channels_empty` | Schedule `channels=[]` | reminder `cancelled` |
| `test_cancels_when_schedule_belongs_to_other_organization` | Item in org A, schedule's event_config in org B | reminder `cancelled` |
| `test_processes_more_than_100_reminders_in_chunks` | 150 reminders due | all `fired`, exit code 0 (verifies `chunk(100)` correctness) |
| `test_does_not_refire_already_fired_reminder` | Reminder `status='fired'`, `remind_at` past | no new notification, status remains `fired` |

### A3. Timezone (4 tests, new file [tests/Feature/Notification/ProcessRemindersTimezoneTest.php](../../../tests/Feature/Notification/ProcessRemindersTimezoneTest.php))

| Test | Setup | Assertion |
|------|-------|-----------|
| `test_now_uses_app_timezone_not_utc` | Default `app.timezone='Asia/Ho_Chi_Minh'` | `now()->getTimezone()->getName() === 'Asia/Ho_Chi_Minh'` |
| `test_remind_at_comparison_consistent_in_app_timezone` | `setTestNow('2026-04-28 14:37:00 Asia/Ho_Chi_Minh')`, reminder `remind_at='2026-04-28 14:37:00'` (no TZ in DB) | reminder fires (no off-by-7h) |
| `test_no_implicit_utc_shift_when_storing_end_at` | Item `end_at='2026-04-28 15:00:00'` | DB raw value (via `DB::table(...)->select(...)->first()->end_at`) equals `'2026-04-28 15:00:00'` exactly |
| `test_command_fires_correctly_under_app_timezone_change` | `Config::set('app.timezone', 'UTC')`, `setTestNow('2026-04-28 07:37:00 UTC')`, item with `end_at='2026-04-28 08:37:00'`, schedule before 60min | Reminder fires (proves `now()` follows current `app.timezone` config, not a hard-coded TZ) |

### Helpers

- Reuse existing `makeItemWithAssignee()` in `ProcessRemindersCommandTest`.
- Add `makeItemWithMultipleAssignees(int $count): TaskAssignmentItem` for multi-user test.
- Add `makeMultipleItemsWithReminders(int $count): array` for chunk-150 test.
- Reuse `InteractsWithNotifications` trait (`enableEvent`, `addReminderSchedule`, `seedNotificationConfig`, `resolveTestOrganization`).

## (B) Manual E2E

### B.1 Extend `SimulateReminderTimingCommand`

File: [app/Console/Commands/SimulateReminderTimingCommand.php](../../../app/Console/Commands/SimulateReminderTimingCommand.php) (currently 295 LOC, will become ~400).

#### New options

| Option | Purpose | Default |
|--------|---------|---------|
| `--deadline-at=` | Absolute end_at like `2026-04-28 14:37:00`. Overrides `--deadline-min` when set. Validated as parseable `Y-m-d H:i:s` and > `now() + before-min`. | null |
| `--users=N` | Assign N users (sequential by id from DB). Overrides `--assignee-email`. Range 1-10. | 1 |
| `--force-status-after=Nm:done\|cancelled` | After N minutes into the wait loop, update `processing_status` to test cancel branch. Regex `/^(\d+)m:(done\|cancelled)$/`. | null |
| `--check-tz` | Print `app.timezone`, `now()->getTimezone()`, `now()` raw, DB `NOW()` at start. | false |
| `--no-organization` | Create document with `organization_id=0` to test cancel branch. | false |
| `--empty-channels` | Override schedule channels = []. | false |
| `--cross-org-schedule` | Create schedule under a different org than the item's org. | false |

#### Output changes

- Reminder timeline table prints `Y-m-d H:i:s` for `remind_at` instead of just `H:i:s`.
- Wait loop logs `now()->format('Y-m-d H:i:s')` each poll iteration.
- When `--check-tz`:
  ```
  === Timezone check ===
  app.timezone: Asia/Ho_Chi_Minh
  now() TZ:    Asia/Ho_Chi_Minh
  now() raw:   2026-04-28 14:37:00
  DB NOW():    2026-04-28 14:37:00
  Match:       OK
  ```

#### New private helpers

- `printTimezoneCheck(): void`
- `parseDeadlineAt(?string $input): ?Carbon`
- `applyForceStatus(int $itemId, Carbon $startAt, string $spec): void` (called inside wait loop)
- `assignAdditionalUsers(int $itemId, int $count, int $deptId): void`

#### Unchanged

- DB schema, migrations.
- Cleanup logic (already idempotent).
- Class structure: single `handle()` + private helpers.

### B.2 Manual checklist

File: `docs/superpowers/specs/2026-04-28-test-cronjob-checklist.md` (new).

#### Pre-flight (run once at top of checklist)

```
Terminal 1: php artisan schedule:work
Terminal 2: php artisan notification:simulate-reminder <options>
```

#### 10 Scenarios

| # | Scenario | Command | Verify | Expected |
|---|----------|---------|--------|----------|
| 1 | Happy path basic | `notification:simulate-reminder --wait-min=8` | Output table + `notifications` / `notification_deliveries` tables | 3 reminders (before/on/after) fire on time, 3 notifications, 3 deliveries `sent` for `mail` channel |
| 2 | Datetime with non-zero seconds | `--deadline-at='2026-04-28 14:37:42' --before-min=2 --after-min=2 --wait-min=10` | Reminder timeline table; `task_assignment_reminders.remind_at` raw value | `remind_at` raw value retains `:42` seconds; reminder fires on the first cron tick where `now() >= remind_at` (i.e. the minute `:38:00` for a `remind_at` of `:35:42`) |
| 3 | Timezone check | `--check-tz --wait-min=0` | Timezone block at start | `app.timezone=Asia/Ho_Chi_Minh`, `now()` and DB `NOW()` differ by < 5s, no 7h drift |
| 4 | Multi-channel | `--channels=mail,sms,zalo --wait-min=8` | `notification_deliveries` count per reminder | Each fired reminder produces 3 deliveries (one per channel) |
| 5 | Multi-user | `--users=3 --wait-min=8` | `notifications` count per reminder | Each fired reminder produces 3 notifications (one per user) |
| 6 | Item done mid-wait | `--force-status-after=3m:done --wait-min=10 --before-min=1 --deadline-min=8` | Reminder statuses after wait completes | Reminder `before` (due at +7min, after status flip) → `cancelled`; any reminder already fired before the 3min flip stays `fired` |
| 7 | Item cancelled mid-wait | `--force-status-after=3m:cancelled --wait-min=10 --before-min=1 --deadline-min=8` | Reminder statuses | Same shape as #6: pending reminders due after the flip become `cancelled` |
| 8 | No organization | `--no-organization --wait-min=5` | Reminder statuses | All reminders → `cancelled` (`organizationId === 0` branch) |
| 9 | Empty channels | `--empty-channels --wait-min=5` | Reminder statuses | All reminders → `cancelled` (`empty($schedule->channels)` branch) |
| 10 | Cross-org schedule | `--cross-org-schedule --wait-min=5` | Reminder statuses | All reminders → `cancelled` (`config->organization_id !== organizationId` branch) |

Each row also documents: pre-conditions (any seed needed), post-cleanup (auto unless `--no-cleanup`), and a SQL one-liner for verifying state in DB.

## Deliverables

| File | Action | Est. LOC |
|------|--------|----------|
| `tests/Feature/Notification/ProcessRemindersCommandTest.php` | Modify (append 14 tests A1+A2 + 2 helpers) | +500 |
| `tests/Feature/Notification/ProcessRemindersTimezoneTest.php` | Create (4 tests A3) | +200 |
| `app/Console/Commands/SimulateReminderTimingCommand.php` | Modify (7 new options + 4 helpers + output tweaks) | +120 |
| `docs/superpowers/specs/2026-04-28-test-cronjob-checklist.md` | Create (10-scenario manual checklist) | n/a |
| `docs/superpowers/specs/2026-04-28-test-cronjob-design.md` | Create (this file) | n/a |

## Implementation order

1. Extend `SimulateReminderTimingCommand` (B.1) — provides the tooling for manual scenarios.
2. Write the manual checklist (B.2).
3. Run scenarios 1-3 from the checklist as a smoke test of the extended command.
4. Write A1 tests (time-precision) in `ProcessRemindersCommandTest.php`.
5. Write A2 tests (coverage gaps) in same file.
6. Write A3 tests (timezone) in new file.
7. Run `php artisan test --filter=ProcessReminders` — expect all green.
8. Run full manual checklist (all 10 scenarios) for retest.
9. Single commit at end (per user preference).

## Risks & open questions

- **Risk:** `Carbon::setTestNow()` may not propagate to MySQL `NOW()`. Mitigation: A3 tests use Carbon-based assertions only; manual #3 uses real wall-clock comparison (`abs(now - DB now) < 5s`).
- **Risk:** Test #A2 `test_processes_more_than_100_reminders_in_chunks` may be slow due to 150 inserts. Mitigation: use `DB::table()->insert([...])` batch instead of Eloquent `create()` per row.
- **Risk:** `--force-status-after` uses `sleep()` inside the existing wait loop — may cause polling drift. Mitigation: check status update at each 30s poll iteration, not in a separate timer.
- **Open:** Should A3 timezone tests run in CI by default? They're TZ-sensitive; if CI runs in UTC, behavior differs. **Decision:** assume CI uses `APP_TIMEZONE=Asia/Ho_Chi_Minh` (matches `.env.example`); document in test comments that CI must set this.
