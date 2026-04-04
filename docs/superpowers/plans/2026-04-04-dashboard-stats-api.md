# Dashboard Stats API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bổ sung các API thống kê còn thiếu để phục vụ Dashboard KPI (mục 7.1) và Báo cáo định kỳ (mục 7.2) theo spec.

**Architecture:** Thêm 3 endpoint mới và mở rộng 2 endpoint hiện tại trong module TaskAssignment, theo đúng pattern Service → Controller → Route đang có.

**Tech Stack:** Laravel 11, Eloquent, Carbon, DB facade (raw queries)

---

### Task 1: Add `statsByItemType` endpoint

**Files:**
- Modify: `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php`
- Modify: `app/Modules/TaskAssignment/Controllers/TaskAssignmentItemController.php`
- Modify: `app/Modules/TaskAssignment/Routes/task_assignment_item.php`

- [ ] **Step 1: Add `statsByItemType` method to ItemService**

Add after `statsByUser` method in `TaskAssignmentItemService.php`:

```php
public function statsByItemType(array $filters): array
{
    $filters = $this->applyDepartmentRestriction($filters);

    $itemTypes = \App\Modules\TaskAssignment\Models\TaskAssignmentItemType::where('status', 'active')->get(['id', 'name']);

    $done = TaskProgressStatusEnum::Done->value;
    $cancelled = TaskProgressStatusEnum::Cancelled->value;
    $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

    return $itemTypes->map(function ($type) use ($filters, $done, $cancelled, $hasDeadline) {
        $base = TaskAssignmentItem::where('task_assignment_item_type_id', $type->id)
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('departments', fn ($dq) => $dq->where('department_id', $v)))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()));

        return [
            'item_type_id' => $type->id,
            'item_type_name' => $type->name,
            'total' => (clone $base)->count(),
            'todo' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Todo->value)->count(),
            'in_progress' => (clone $base)->where('processing_status', TaskProgressStatusEnum::InProgress->value)->count(),
            'done' => (clone $base)->where('processing_status', $done)->count(),
            'overdue' => (clone $base)->where('deadline_type', $hasDeadline)
                ->where('end_at', '<', now())
                ->whereNotIn('processing_status', [$done, $cancelled])->count(),
            'paused' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Paused->value)->count(),
            'cancelled' => (clone $base)->where('processing_status', $cancelled)->count(),
        ];
    })->all();
}
```

- [ ] **Step 2: Add controller method**

Add to `TaskAssignmentItemController.php` after `statsByUser`:

```php
/**
 * Thống kê công việc theo loại công việc
 *
 * @queryParam department_id integer Lọc theo phòng ban. Example: 1
 * @queryParam priority string Lọc theo mức độ ưu tiên.
 * @queryParam from_date date Từ ngày (Y-m-d). Example: 2026-01-01
 * @queryParam to_date date Đến ngày (Y-m-d). Example: 2026-12-31
 *
 * @response 200 {"success": true, "data": [{"item_type_id": 1, "item_type_name": "TT Thành ủy giao", "total": 19, "todo": 5, "in_progress": 8, "done": 3, "overdue": 2, "paused": 1, "cancelled": 0}]}
 */
public function statsByItemType(StatsFilterRequest $request)
{
    return $this->success($this->itemService->statsByItemType($request->all()));
}
```

- [ ] **Step 3: Add route**

Add to `task_assignment_item.php` after line 14 (stats-by-time):

```php
Route::get('/stats-by-item-type', [TaskAssignmentItemController::class, 'statsByItemType'])->middleware('permission:task-assignment-items.statsByItemType,web');
```

- [ ] **Step 4: Verify with artisan route:list**

Run: `php artisan route:list --path=task-assignment-items/stats-by-item-type`
Expected: Route shows up with GET method.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php app/Modules/TaskAssignment/Controllers/TaskAssignmentItemController.php app/Modules/TaskAssignment/Routes/task_assignment_item.php
git commit -m "feat(task-assignment): add stats-by-item-type endpoint"
```

---

### Task 2: Add `statsByDocument` endpoint

**Files:**
- Modify: `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php`
- Modify: `app/Modules/TaskAssignment/Controllers/TaskAssignmentItemController.php`
- Modify: `app/Modules/TaskAssignment/Routes/task_assignment_item.php`

- [ ] **Step 1: Add `statsByDocument` method to ItemService**

Add after `statsByItemType` method:

```php
public function statsByDocument(array $filters): array
{
    $filters = $this->applyDepartmentRestriction($filters);

    $done = TaskProgressStatusEnum::Done->value;
    $cancelled = TaskProgressStatusEnum::Cancelled->value;
    $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

    $query = DB::table('task_assignment_items as ti')
        ->join('task_assignment_documents as td', 'td.id', '=', 'ti.task_assignment_document_id')
        ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereExists(function ($sub) use ($v) {
            $sub->select(DB::raw(1))
                ->from('task_assignment_item_department')
                ->whereColumn('task_assignment_item_department.task_assignment_item_id', 'ti.id')
                ->where('task_assignment_item_department.department_id', $v);
        }))
        ->when($filters['task_assignment_type_id'] ?? null, fn ($q, $v) => $q->where('td.task_assignment_type_id', $v))
        ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('td.issue_date', '>=', $v))
        ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('td.issue_date', '<=', $v));

    $results = $query->groupBy('td.id', 'td.name', 'td.issue_date')
        ->selectRaw('td.id as document_id, td.name as document_name, td.issue_date')
        ->selectRaw('COUNT(*) as total_items')
        ->selectRaw("SUM(CASE WHEN ti.processing_status = ? THEN 1 ELSE 0 END) as done", [$done])
        ->selectRaw("SUM(CASE WHEN ti.processing_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
        ->selectRaw("SUM(CASE WHEN ti.deadline_type = ? AND ti.end_at < NOW() AND ti.processing_status NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue", [$hasDeadline, $done, $cancelled])
        ->orderBy('td.issue_date', 'desc')
        ->get();

    return $results->map(fn ($row) => [
        'document_id' => $row->document_id,
        'document_name' => $row->document_name,
        'issue_date' => $row->issue_date,
        'total_items' => (int) $row->total_items,
        'done' => (int) $row->done,
        'in_progress' => (int) $row->in_progress,
        'overdue' => (int) $row->overdue,
        'completion_rate' => $row->total_items > 0 ? round(((int) $row->done / (int) $row->total_items) * 100, 1) : 0,
    ])->all();
}
```

- [ ] **Step 2: Add controller method**

Add to `TaskAssignmentItemController.php`:

```php
/**
 * Thống kê công việc theo văn bản giao việc
 *
 * @queryParam department_id integer Lọc theo phòng ban. Example: 1
 * @queryParam task_assignment_type_id integer Lọc theo loại văn bản. Example: 1
 * @queryParam from_date date Từ ngày ban hành (Y-m-d). Example: 2026-01-01
 * @queryParam to_date date Đến ngày ban hành (Y-m-d). Example: 2026-12-31
 *
 * @response 200 {"success": true, "data": [{"document_id": 1, "document_name": "KH số 123", "issue_date": "2026-03-15", "total_items": 10, "done": 7, "in_progress": 2, "overdue": 1, "completion_rate": 70.0}]}
 */
public function statsByDocument(StatsFilterRequest $request)
{
    return $this->success($this->itemService->statsByDocument($request->all()));
}
```

- [ ] **Step 3: Add route**

Add to `task_assignment_item.php` after stats-by-item-type:

```php
Route::get('/stats-by-document', [TaskAssignmentItemController::class, 'statsByDocument'])->middleware('permission:task-assignment-items.statsByDocument,web');
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php app/Modules/TaskAssignment/Controllers/TaskAssignmentItemController.php app/Modules/TaskAssignment/Routes/task_assignment_item.php
git commit -m "feat(task-assignment): add stats-by-document endpoint"
```

---

### Task 3: Add `statsByTime` to DocumentService

**Files:**
- Modify: `app/Modules/TaskAssignment/Services/TaskAssignmentDocumentService.php`
- Modify: `app/Modules/TaskAssignment/Controllers/TaskAssignmentDocumentController.php`
- Modify: `app/Modules/TaskAssignment/Routes/task_assignment_document.php`
- Create: `app/Modules/TaskAssignment/Requests/DocumentStatsByTimeRequest.php`

- [ ] **Step 1: Create request class**

Create `app/Modules/TaskAssignment/Requests/DocumentStatsByTimeRequest.php`:

```php
<?php

namespace App\Modules\TaskAssignment\Requests;

class DocumentStatsByTimeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'task_assignment_type_id' => 'sometimes|integer|exists:task_assignment_types,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->from_date && $this->to_date) {
                $from = \Carbon\Carbon::parse($this->from_date);
                $to = \Carbon\Carbon::parse($this->to_date);
                if ($from->diffInMonths($to) > 12) {
                    $validator->errors()->add('to_date', 'Khoảng thời gian không được vượt quá 12 tháng.');
                }
            }
        });
    }
}
```

- [ ] **Step 2: Add `statsByTime` method to DocumentService**

Add after `stats` method in `TaskAssignmentDocumentService.php`:

```php
public function statsByTime(array $filters): array
{
    $from = \Carbon\Carbon::parse($filters['from_date'])->startOfMonth();
    $to = \Carbon\Carbon::parse($filters['to_date'])->endOfMonth();

    $draft = TaskAssignmentDocumentStatusEnum::Draft->value;
    $issued = TaskAssignmentDocumentStatusEnum::Issued->value;

    $baseQuery = TaskAssignmentDocument::query()
        ->when($filters['task_assignment_type_id'] ?? null, fn ($q, $v) => $q->where('task_assignment_type_id', $v));

    $results = [];
    $cursor = $from->copy();

    while ($cursor->lte($to)) {
        $monthStart = $cursor->copy()->startOfMonth();
        $monthEnd = $cursor->copy()->endOfMonth();

        $base = (clone $baseQuery)->whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        $results[] = [
            'month' => $cursor->format('Y-m'),
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', $draft)->count(),
            'issued' => (clone $base)->where('status', $issued)->count(),
        ];

        $cursor->addMonth();
    }

    return $results;
}
```

- [ ] **Step 3: Add controller method**

Add to `TaskAssignmentDocumentController.php`:

```php
use App\Modules\TaskAssignment\Requests\DocumentStatsByTimeRequest;

/**
 * Thống kê văn bản giao việc theo thời gian (tháng)
 *
 * @queryParam from_date date required Từ ngày (Y-m-d). Example: 2026-01-01
 * @queryParam to_date date required Đến ngày (Y-m-d, tối đa 12 tháng). Example: 2026-12-31
 * @queryParam task_assignment_type_id integer Lọc theo loại văn bản. Example: 1
 *
 * @response 200 {"success": true, "data": [{"month": "2026-01", "total": 5, "draft": 1, "issued": 4}]}
 */
public function statsByTime(DocumentStatsByTimeRequest $request)
{
    return $this->success($this->documentService->statsByTime($request->all()));
}
```

- [ ] **Step 4: Add route**

Add to `task_assignment_document.php` after stats route (line 11):

```php
Route::get('/stats-by-time', [TaskAssignmentDocumentController::class, 'statsByTime'])->middleware('permission:task-assignment-documents.statsByTime,web');
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/TaskAssignment/Services/TaskAssignmentDocumentService.php app/Modules/TaskAssignment/Controllers/TaskAssignmentDocumentController.php app/Modules/TaskAssignment/Routes/task_assignment_document.php app/Modules/TaskAssignment/Requests/DocumentStatsByTimeRequest.php
git commit -m "feat(task-assignment): add document stats-by-time endpoint"
```

---

### Task 4: Extend `statsByDepartment` with period metrics

**Files:**
- Modify: `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php`

- [ ] **Step 1: Update `statsByDepartment` method**

Replace the return array inside the `$departments->map()` closure to add `new_in_period`, `done_in_period`, `on_time_count`, `overdue_done_count`:

```php
return $departments->map(function ($dept) use ($filters, $done, $cancelled, $hasDeadline) {
    $base = TaskAssignmentItem::whereHas('departments', fn ($q) => $q->where('department_id', $dept->id))
        ->when($filters['processing_status'] ?? null, fn ($q, $v) => $q->where('processing_status', $v))
        ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
        ->when($filters['deadline_type'] ?? null, fn ($q, $v) => $q->where('deadline_type', $v))
        ->when($filters['task_assignment_item_type_id'] ?? null, fn ($q, $v) => $q->where('task_assignment_item_type_id', $v))
        ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
        ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()));

    $total = (clone $base)->count();

    $fromDate = $filters['from_date'] ?? null;
    $toDate = $filters['to_date'] ?? null;
    $toDateEnd = $toDate ? Carbon::parse($toDate)->endOfDay() : null;

    return [
        'department_id' => $dept->id,
        'department_name' => $dept->name,
        'department_code' => $dept->code,
        'total' => $total,
        'todo' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Todo->value)->count(),
        'in_progress' => (clone $base)->where('processing_status', TaskProgressStatusEnum::InProgress->value)->count(),
        'done' => (clone $base)->where('processing_status', $done)->count(),
        'overdue' => (clone $base)->where('deadline_type', $hasDeadline)
            ->where('end_at', '<', now())
            ->whereNotIn('processing_status', [$done, $cancelled])->count(),
        'paused' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Paused->value)->count(),
        'cancelled' => (clone $base)->where('processing_status', $cancelled)->count(),
        'new_in_period' => ($fromDate && $toDate)
            ? TaskAssignmentItem::whereHas('departments', fn ($q) => $q->where('department_id', $dept->id))
                ->whereBetween('created_at', [$fromDate, $toDateEnd])->count()
            : null,
        'done_in_period' => ($fromDate && $toDate)
            ? TaskAssignmentItem::whereHas('departments', fn ($q) => $q->where('department_id', $dept->id))
                ->where('processing_status', $done)
                ->whereBetween('completed_at', [$fromDate, $toDateEnd])->count()
            : null,
        'on_time_count' => (clone $base)->where('processing_status', $done)
            ->where(fn ($q) => $q->whereNull('end_at')->orWhereColumn('completed_at', '<=', 'end_at'))->count(),
        'overdue_done_count' => (clone $base)->where('processing_status', $done)
            ->whereNotNull('end_at')->whereColumn('completed_at', '>', 'end_at')->count(),
    ];
})->all();
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php
git commit -m "feat(task-assignment): extend statsByDepartment with period and completion metrics"
```

---

### Task 5: Extend `statsByUser` with assignment metrics

**Files:**
- Modify: `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php`

- [ ] **Step 1: Update `statsByUser` method**

Add new selectRaw lines and map fields:

```php
public function statsByUser(array $filters): array
{
    $filters = $this->applyDepartmentRestriction($filters);

    $done = TaskProgressStatusEnum::Done->value;
    $cancelled = TaskProgressStatusEnum::Cancelled->value;
    $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

    $fromDate = $filters['from_date'] ?? null;
    $toDate = $filters['to_date'] ?? null;

    $query = DB::table('task_assignment_item_user as tiu')
        ->join('task_assignment_items as ti', 'ti.id', '=', 'tiu.task_assignment_item_id')
        ->join('users as u', 'u.id', '=', 'tiu.user_id')
        ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('tiu.department_id', $v))
        ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('ti.priority', $v))
        ->when($fromDate, fn ($q, $v) => $q->where('ti.created_at', '>=', $v))
        ->when($toDate, fn ($q, $v) => $q->where('ti.created_at', '<=', Carbon::parse($v)->endOfDay()));

    if (! empty($filters['processing_status'])) {
        $query->where('ti.processing_status', $filters['processing_status']);
    }

    $results = $query->groupBy('tiu.user_id', 'u.name')
        ->selectRaw('tiu.user_id, u.name as user_name')
        ->selectRaw('COUNT(*) as total')
        ->selectRaw("SUM(CASE WHEN ti.processing_status = 'todo' THEN 1 ELSE 0 END) as todo")
        ->selectRaw("SUM(CASE WHEN ti.processing_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
        ->selectRaw("SUM(CASE WHEN ti.processing_status = ? THEN 1 ELSE 0 END) as done", [$done])
        ->selectRaw("SUM(CASE WHEN ti.deadline_type = ? AND ti.end_at < NOW() AND ti.processing_status NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue", [$hasDeadline, $done, $cancelled])
        ->selectRaw("SUM(CASE WHEN ti.processing_status = ? AND (ti.end_at IS NULL OR ti.completed_at <= ti.end_at) THEN 1 ELSE 0 END) as on_time_count", [$done])
        ->selectRaw("SUM(CASE WHEN ti.processing_status = ? AND ti.completed_at > ti.end_at THEN 1 ELSE 0 END) as overdue_done_count", [$done])
        ->selectRaw("SUM(CASE WHEN tiu.assignment_status = 'assigned' THEN 1 ELSE 0 END) as assigned_count")
        ->selectRaw("SUM(CASE WHEN tiu.assignment_status IN ('accepted', 'done') THEN 1 ELSE 0 END) as accepted_count");

    if ($fromDate && $toDate) {
        $toDateEnd = Carbon::parse($toDate)->endOfDay();
        $results = $results
            ->selectRaw("SUM(CASE WHEN ti.created_at >= ? AND ti.created_at <= ? THEN 1 ELSE 0 END) as new_in_period", [$fromDate, $toDateEnd])
            ->selectRaw("SUM(CASE WHEN ti.processing_status = ? AND ti.completed_at >= ? AND ti.completed_at <= ? THEN 1 ELSE 0 END) as done_in_period", [$done, $fromDate, $toDateEnd]);
    } else {
        $results = $results
            ->selectRaw("NULL as new_in_period")
            ->selectRaw("NULL as done_in_period");
    }

    $results = $results->get();

    return $results->map(fn ($row) => [
        'user_id' => $row->user_id,
        'user_name' => $row->user_name,
        'total' => (int) $row->total,
        'todo' => (int) $row->todo,
        'in_progress' => (int) $row->in_progress,
        'done' => (int) $row->done,
        'overdue' => (int) $row->overdue,
        'on_time_count' => (int) $row->on_time_count,
        'overdue_done_count' => (int) $row->overdue_done_count,
        'assigned_count' => (int) $row->assigned_count,
        'accepted_count' => (int) $row->accepted_count,
        'new_in_period' => $row->new_in_period !== null ? (int) $row->new_in_period : null,
        'done_in_period' => $row->done_in_period !== null ? (int) $row->done_in_period : null,
    ])->all();
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php
git commit -m "feat(task-assignment): extend statsByUser with assignment and period metrics"
```

---

## API Summary for FE

After implementation, the complete dashboard API surface:

| # | Endpoint | Method | Phục vụ |
|---|----------|--------|---------|
| 1 | `/api/task-assignment-items/stats` | GET | Tổng quan KPI trạng thái |
| 2 | `/api/task-assignment-items/stats-by-department` | GET | CV theo phòng ban + báo cáo tuần |
| 3 | `/api/task-assignment-items/stats-by-user` | GET | CV theo người + báo cáo tuần |
| 4 | `/api/task-assignment-items/stats-by-time` | GET | Xu hướng theo tháng |
| 5 | `/api/task-assignment-items/stats-by-item-type` | GET | Phân bố theo loại CV |
| 6 | `/api/task-assignment-items/stats-by-document` | GET | Tỷ lệ HT từng văn bản |
| 7 | `/api/task-assignment-documents/stats-by-time` | GET | Số VB theo tháng |
| 8 | `/api/task-assignment-items/overdue` | GET | DS quá hạn |
| 9 | `/api/task-assignment-items/upcoming-deadline` | GET | DS sắp đến hạn |
