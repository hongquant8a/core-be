# Department Representative — Design

**Date:** 2026-04-28
**Scope:** Add per-department representative flag for task auto-assignment.

## Goal

Mark one user per `task_assignment_department` as the department's representative ("đại diện"). When a task is created and the executing department is selected on the frontend, the representative is auto-filled into the assignee field. User can override before submit.

## Context

- Existing pivot `task_assignment_users(user_id, task_assignment_department_id, is_primary, status, organization_id, ...)`.
- `is_primary` already exists with **different semantic**: per-user marker for "user's primary department" (used when one user belongs to multiple departments). Setting `is_primary=true` for one row sets all other rows of THIS USER to false.
- The new "đại diện" concept is per-department: one user per dept marked. Setting `is_representative=true` for one row sets all other rows of THIS DEPARTMENT to false.
- These two flags are orthogonal and coexist on the same row.

## Non-goals

- No change to `is_primary` semantic.
- No change to task creation (BE) flow — the auto-fill is FE-only behavior, BE only exposes the flag.
- No auto-promote behavior on remove. Department can have no representative (rep is optional per option A).
- No bulk-set rep across departments.

## Architecture

### Data model

New column on `task_assignment_users`:

```
is_representative BOOLEAN NOT NULL DEFAULT false
INDEX (task_assignment_department_id, is_representative)
```

Migration: `database/migrations/2026_04_28_000000_add_is_representative_to_task_assignment_users.php`

Application-level invariant (no DB-level partial unique constraint): at most one row per `(task_assignment_department_id, organization_id)` has `is_representative=true`. Enforced by `TaskAssignmentDepartmentService::setRepresentative()` in a transaction. Bypassing the service (direct DB writes) can violate the invariant — accepted risk, mirrors the existing `is_primary` pattern.

Model [`TaskAssignmentUser`](../../../app/Modules/TaskAssignment/Models/TaskAssignmentUser.php) gains:
- `'is_representative'` in `$fillable`
- `'is_representative' => 'boolean'` in `$casts`

### API

**No new endpoint and no new permission.** All write operations go through the existing `POST /api/task-assignment-departments/{id}/users` (sync). The existing `PATCH /api/task-assignment-departments/{id}/users/{userId}` (remove user) is untouched.

#### `POST /api/task-assignment-departments/{id}/users` (extended)

Request body:

```json
{
  "user_ids": [1, 2, 3],
  "representative_user_id": 2
}
```

- `user_ids`: required, array of user IDs (existing).
- `representative_user_id`: nullable integer. **If present**, must be in `user_ids` (validated by `Rule::in($user_ids)`). After sync completes, the matching row is set `is_representative=true` and all other rows of the dept are set `false`.
- **If absent or null**, no change to representative state. Representatives that get removed because the user is no longer in `user_ids` lose the flag automatically (their row is deleted by sync).

Backward compatibility: existing FE not aware of `representative_user_id` keeps working unchanged.

#### `GET /api/task-assignment-departments/{id}/users` (extended response)

Each user item now includes `is_representative: bool`:

```json
{
  "data": [
    { "id": 12, "user_id": 5, "name": "Đặng Minh Tuấn", "email": "...", "status": "active", "is_representative": true },
    { "id": 13, "user_id": 8, "name": "Nhân viên", "email": "...", "status": "active", "is_representative": false }
  ]
}
```

### Service layer

[`TaskAssignmentDepartmentService`](../../../app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php) changes:

1. **`syncUsers(TaskAssignmentDepartment $department, array $userIds, ?int $representativeUserId = null): void`**
   - Wrap entire body in `DB::transaction`.
   - Existing add/remove logic unchanged.
   - After sync: if `$representativeUserId !== null`, call `setRepresentative($department, $representativeUserId)`.

2. **`setRepresentative(TaskAssignmentDepartment $department, ?int $userId): void`** — new private method.
   - If `$userId` not null, verify membership via `TaskAssignmentUser::where(...)->exists()`. Otherwise throw `ValidationException` with message "Người đại diện phải thuộc danh sách thành viên."
   - Clear: `UPDATE task_assignment_users SET is_representative=false WHERE task_assignment_department_id=$id AND organization_id=$orgId`.
   - If `$userId !== null`: set `is_representative=true` for that user's row.

3. **`removeUser()` — unchanged.** When the rep is removed, the row is deleted; dept naturally ends up without a rep.

4. **`getUsers()` — unchanged.** The relation already returns the model; the controller mapping adds the field to the response.

### Controller

[`TaskAssignmentDepartmentController`](../../../app/Modules/TaskAssignment/Controllers/TaskAssignmentDepartmentController.php):

1. **`users()`** response mapper adds `'is_representative' => (bool) $tau->is_representative`.
2. **`syncUsers()`** passes `$request->input('representative_user_id')` (defaults to null) as third arg.

### Request

[`SyncDepartmentUsersRequest`](../../../app/Modules/TaskAssignment/Requests/SyncDepartmentUsersRequest.php):

```php
public function rules(): array
{
    return [
        'user_ids' => ['required', 'array'],
        'user_ids.*' => ['integer', 'exists:users,id'],
        'representative_user_id' => ['nullable', 'integer', Rule::in($this->user_ids ?? [])],
    ];
}

public function messages(): array
{
    return array_merge(parent::messages() ?? [], [
        'representative_user_id.in' => 'Người đại diện phải nằm trong danh sách thành viên được chọn.',
    ]);
}
```

### FE behavior (informational)

Out of scope for this spec, but documented for context:
- When dept dropdown changes, FE calls `GET /api/task-assignment-departments/{id}/users`.
- FE picks the user where `is_representative === true` and pre-fills the "Người thực hiện" field.
- If no user has `is_representative === true`, FE leaves the field empty (user must pick).

## Edge cases

| Case | Behavior |
|------|----------|
| Sync with `representative_user_id` not in `user_ids` | Validation 422 "Người đại diện phải nằm trong danh sách thành viên được chọn." |
| Sync omits current rep from `user_ids` | Row deleted by existing remove logic → dept has no rep |
| Sync with `representative_user_id` for a user not yet in dept (but in `user_ids` of this call) | OK — sync inserts the user first within the same transaction, then `setRepresentative` finds the row |
| Sync with same rep as before | No-op effect, both writes idempotent |
| Sync without `representative_user_id` field | Existing rep state untouched |
| Sync with `representative_user_id: null` explicit | Existing rep state untouched (per spec: only set, never clear via this path) |
| `removeUser` of current rep | Row deleted; dept has no rep. No auto-promote |

Note on the "set null to clear" intent: this spec **does not** clear the rep when `representative_user_id` is null/absent in sync. Clearing requires removing the user from the dept (sync without that user_id). This keeps `representative_user_id=null` ambiguous-safe (means "I have no opinion, leave it alone").

## Multi-tenant isolation

`TaskAssignmentUser` has `HasOrganizationScope`. All service queries filter by `organization_id`. Setting rep in org A does not touch org B rows even if the same `dept_id` somehow exists (it shouldn't, given FK + unique on `(user_id, dept_id, org_id)`).

## Testing

New file: `tests/Feature/TaskAssignment/DepartmentRepresentativeTest.php`

| Test | Verifies |
|------|----------|
| `test_sync_users_without_representative_does_not_set_any_rep` | Sync with `representative_user_id` absent → all rows `is_representative=false` |
| `test_sync_users_with_representative_sets_flag_correctly` | rep=user 2 in `user_ids=[1,2,3]` → only user 2 has flag |
| `test_sync_users_with_rep_not_in_user_ids_fails_validation` | rep=99 not in `user_ids=[1,2,3]` → 422 |
| `test_sync_users_switching_representative_clears_old_rep` | Two consecutive syncs with different rep → only the latest user has flag |
| `test_get_users_includes_is_representative_field` | Response shape includes `is_representative` per item |
| `test_remove_user_who_is_rep_leaves_department_without_rep` | Remove rep via `removeUser` → no rep in dept |
| `test_sync_excluding_current_rep_clears_rep` | Sync without old rep in `user_ids` → no rep in dept |
| `test_rep_flag_isolated_per_organization` | Setting rep in org A leaves org B unchanged |
| `test_rep_flag_independent_of_is_primary` | One user can have both flags simultaneously |

Existing `syncUsers` tests (if any) must continue to pass without changes (backward compat).

## Deliverables

| File | Action | Notes |
|------|--------|-------|
| `database/migrations/2026_04_28_000000_add_is_representative_to_task_assignment_users.php` | Create | Add column + index |
| `app/Modules/TaskAssignment/Models/TaskAssignmentUser.php` | Modify | Fillable + cast |
| `app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php` | Modify | `syncUsers` extends, new private `setRepresentative` |
| `app/Modules/TaskAssignment/Controllers/TaskAssignmentDepartmentController.php` | Modify | `users()` response, `syncUsers()` pass-through |
| `app/Modules/TaskAssignment/Requests/SyncDepartmentUsersRequest.php` | Modify | New validation rule |
| `tests/Feature/TaskAssignment/DepartmentRepresentativeTest.php` | Create | 9 tests |
| `docs/superpowers/specs/2026-04-28-department-representative-design.md` | Create | This file |

No frontend changes in this spec.

## Risks

- **Application-level invariant only**: a direct SQL UPDATE on `is_representative` could create two reps in one dept. Mitigation: tests verify the service path; no other code path writes this column.
- **Nullable semantics for `representative_user_id`**: documented above as "no opinion, leave alone." If product later wants "clear via API", we'll add an explicit verb (e.g., `clear_representative: true`) or a separate endpoint. Don't overload null.
