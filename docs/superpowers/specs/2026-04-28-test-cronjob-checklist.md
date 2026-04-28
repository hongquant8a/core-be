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
