# Monthly Report New Format Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite Excel monthly report (`exportMonthlyReport`) to match the new UBND summary format — 7 fixed columns on the summary sheet, and a 3-parallel-column layout (Đang giao | Hoàn thành | Trễ hạn) plus 3 blank columns for manual notes on each department sheet.

**Architecture:** Rewrite `MonthlyReportSummarySheet` and `MonthlyReportDepartmentSheet` in place. Drop `MonthlyReportNextMonthSheet` from the export pipeline (keep the file). Reuse the existing `MonthlyReportExport` orchestrator; only touch `sheets()` to remove the next-month sheet. Classification logic (Đang / Hoàn thành / Trễ hạn) is extracted into a private helper on each sheet for clarity.

**Tech Stack:** Laravel 11, `maatwebsite/excel` 3.x, PhpSpreadsheet, Carbon, PHPUnit.

---

## Context For The Engineer

You have zero knowledge of this codebase. Read this before touching code.

### Relevant models & enums

- `App\Modules\TaskAssignment\Models\TaskAssignmentItem` — the "task/nhiệm vụ" entity. Columns used: `processing_status`, `deadline_type`, `end_at`, `created_at`. Has many-to-many `users` via pivot `task_assignment_item_user` with a `department_id` column on the pivot. This pivot is how items link to departments.
- `App\Modules\TaskAssignment\Models\TaskAssignmentDepartment` — the "phòng/đơn vị". Fields used: `code` (e.g. `KTHT`, `VHXH`), `name` (e.g. `Phòng Kinh tế…`), `status` (`active`), `sort_order`.
- `App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum` — cases: `Todo='todo'`, `InProgress='in_progress'`, `Reported='reported'`, `Done='done'`, `Overdue='overdue'`, `Paused='paused'`, `Cancelled='cancelled'`.
- `App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum` — cases: `HasDeadline='has_deadline'`, `NoDeadline='no_deadline'`.

### Entry point & orchestration

- HTTP route: `GET /api/task-assignment/items/export-monthly-report?month=YYYY-MM` → `TaskAssignmentItemController::exportMonthlyReport` → `TaskAssignmentItemService::exportMonthlyReport(string $month)` → `new MonthlyReportExport($month)`.
- `MonthlyReportExport` implements `WithMultipleSheets`; its `sheets()` method returns an array of per-sheet classes.
- Each sheet class implements `FromArray` + `WithTitle` + `WithStyles` + `ShouldAutoSize`. `array()` returns rows (row 1 = spreadsheet row 1). `styles()` applies merges/fills/borders.

### Classification rules (decided with stakeholder; do not change without asking)

Given a collection of items filtered by `created_at <= endOfMonth($month)` and linked to a department:

- **Trễ hạn (Overdue):** `deadline_type === 'has_deadline'` AND `end_at` is in the past (relative to "now") AND `processing_status NOT IN ('done', 'cancelled')`.
- **Hoàn thành (Done):** `processing_status === 'done'`.
- **Đang giao (In-flight):** `processing_status IN ('todo', 'in_progress')` AND the item is NOT in the Trễ hạn bucket (i.e. not overdue by the rule above).

The three buckets are mutually exclusive by construction (an item is Trễ hạn first if it matches; otherwise Hoàn thành if done; otherwise Đang giao if todo/in_progress; otherwise it falls into no bucket — e.g. `paused`, `reported`, `cancelled` without an overdue deadline). **Tổng số = total item count**, which may exceed `Đang + Hoàn thành + Trễ` when items with other statuses exist. The summary-row screenshot data matches this rule (7+18+0=25, 21+23+4=48).

### Cutoff date for the title

Use the last day of `$month` as the cutoff. Title reads: `TỔNG HỢP NHIỆM VỤ TRÊN PHẦN MỀM GIAO VIỆC CỦA CÁC PHÒNG, ĐƠN VỊ đến {d} tháng {m}` (e.g. `đến 31 tháng 3`). Same title text on the summary sheet AND every department sheet.

### Other rules from the stakeholder

- Tab names: use `$department->code` (truncated to 31 chars, Excel sheet-name limit).
- Three columns `ĐỀ XUẤT, KIẾN NGHỊ` / `XIN Ý KIẾN BÁO CÁO LÃNH ĐẠO UBND TUẦN ĐẾN` / `GHI CHÚ` on department sheets: leave blank for the user to fill in manually after export.
- No filter on "UBND giao" — count **all** items of the department.
- `MonthlyReportNextMonthSheet.php` — keep the file on disk (do not delete), but remove it from the `sheets()` array in `MonthlyReportExport`.
- STT on summary sheet uses Roman numerals (I, II, III, IV, V, VI, VII, …).

---

## File Structure

**Rewrite in place:**
- `app/Modules/TaskAssignment/Exports/MonthlyReportSummarySheet.php` — new 7-column layout (STT Roman, ĐƠN VỊ, Tổng, Đang giao, Hoàn thành, Trễ hạn, Ghi chú) + TỔNG CỘNG row.
- `app/Modules/TaskAssignment/Exports/MonthlyReportDepartmentSheet.php` — new 7-column layout with 3 parallel task lists + 3 blank columns + 1 "Tổng hợp" count row.

**Modify:**
- `app/Modules/TaskAssignment/Exports/MonthlyReportExport.php:18-38` — remove the next-month sheet from `sheets()`.

**Keep untouched (but on disk):**
- `app/Modules/TaskAssignment/Exports/MonthlyReportNextMonthSheet.php` — leave as-is per stakeholder.

**New tests:**
- `tests/Unit/TaskAssignment/MonthlyReportClassifierTest.php` — unit tests for the bucket-classification rule.
- `tests/Feature/TaskAssignment/MonthlyReportExportTest.php` — smoke test for the end-to-end export (sheet count, sheet titles, row counts).

---

## Task 1: Unit-test the classifier logic

We pull the Đang/Hoàn thành/Trễ hạn decision into a tiny pure helper so the rule is testable without DB. Put it on a new private trait-like helper on each sheet (we'll inline it in both sheets for locality — but test it once via the summary sheet).

**Files:**
- Create: `tests/Unit/TaskAssignment/MonthlyReportClassifierTest.php`
- Modify: `app/Modules/TaskAssignment/Exports/MonthlyReportSummarySheet.php` (add a `public static function classify(object $item, \Carbon\Carbon $now): string` that returns `'in_flight'|'done'|'overdue'|'other'`)

- [ ] **Step 1: Write failing classifier tests**

File: `tests/Unit/TaskAssignment/MonthlyReportClassifierTest.php`

```php
<?php

namespace Tests\Unit\TaskAssignment;

use App\Modules\TaskAssignment\Exports\MonthlyReportSummarySheet;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MonthlyReportClassifierTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-03-31 12:00:00');
    }

    public function test_done_task_is_done(): void
    {
        $item = (object) [
            'processing_status' => 'done',
            'deadline_type' => 'has_deadline',
            'end_at' => Carbon::parse('2026-03-01'),
        ];
        $this->assertSame('done', MonthlyReportSummarySheet::classify($item, $this->now));
    }

    public function test_in_progress_not_overdue_is_in_flight(): void
    {
        $item = (object) [
            'processing_status' => 'in_progress',
            'deadline_type' => 'has_deadline',
            'end_at' => Carbon::parse('2026-04-15'),
        ];
        $this->assertSame('in_flight', MonthlyReportSummarySheet::classify($item, $this->now));
    }

    public function test_todo_no_deadline_is_in_flight(): void
    {
        $item = (object) [
            'processing_status' => 'todo',
            'deadline_type' => 'no_deadline',
            'end_at' => null,
        ];
        $this->assertSame('in_flight', MonthlyReportSummarySheet::classify($item, $this->now));
    }

    public function test_in_progress_past_deadline_is_overdue(): void
    {
        $item = (object) [
            'processing_status' => 'in_progress',
            'deadline_type' => 'has_deadline',
            'end_at' => Carbon::parse('2026-03-10'),
        ];
        $this->assertSame('overdue', MonthlyReportSummarySheet::classify($item, $this->now));
    }

    public function test_done_past_deadline_is_still_done(): void
    {
        $item = (object) [
            'processing_status' => 'done',
            'deadline_type' => 'has_deadline',
            'end_at' => Carbon::parse('2026-03-10'),
        ];
        $this->assertSame('done', MonthlyReportSummarySheet::classify($item, $this->now));
    }

    public function test_cancelled_past_deadline_is_other(): void
    {
        $item = (object) [
            'processing_status' => 'cancelled',
            'deadline_type' => 'has_deadline',
            'end_at' => Carbon::parse('2026-03-10'),
        ];
        $this->assertSame('other', MonthlyReportSummarySheet::classify($item, $this->now));
    }

    public function test_paused_task_is_other(): void
    {
        $item = (object) [
            'processing_status' => 'paused',
            'deadline_type' => 'has_deadline',
            'end_at' => Carbon::parse('2026-04-15'),
        ];
        $this->assertSame('other', MonthlyReportSummarySheet::classify($item, $this->now));
    }

    public function test_paused_past_deadline_is_overdue(): void
    {
        $item = (object) [
            'processing_status' => 'paused',
            'deadline_type' => 'has_deadline',
            'end_at' => Carbon::parse('2026-03-10'),
        ];
        $this->assertSame('overdue', MonthlyReportSummarySheet::classify($item, $this->now));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MonthlyReportClassifierTest`
Expected: FAIL — `MonthlyReportSummarySheet::classify` does not exist (Error: "Call to undefined method").

- [ ] **Step 3: Add the `classify` static method**

Add to the top of the class body in `app/Modules/TaskAssignment/Exports/MonthlyReportSummarySheet.php` (before `public function __construct`):

```php
    /**
     * Classify an item into one of 4 buckets for the monthly report.
     *
     * Order matters: overdue is checked first so an in_progress task past its
     * deadline falls into 'overdue', not 'in_flight'. Done is terminal —
     * a completed task stays 'done' even if its deadline passed.
     *
     * @param  object  $item  Must expose processing_status, deadline_type, end_at (Carbon|string|null).
     * @return 'in_flight'|'done'|'overdue'|'other'
     */
    public static function classify(object $item, \Carbon\Carbon $now): string
    {
        if ($item->processing_status === 'done') {
            return 'done';
        }

        $isOverdue = $item->deadline_type === 'has_deadline'
            && $item->end_at
            && \Carbon\Carbon::parse($item->end_at)->lt($now)
            && ! in_array($item->processing_status, ['done', 'cancelled'], true);

        if ($isOverdue) {
            return 'overdue';
        }

        if (in_array($item->processing_status, ['todo', 'in_progress'], true)) {
            return 'in_flight';
        }

        return 'other';
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=MonthlyReportClassifierTest`
Expected: PASS — 8 tests passing.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/TaskAssignment/Exports/MonthlyReportSummarySheet.php tests/Unit/TaskAssignment/MonthlyReportClassifierTest.php
git commit -m "feat(monthly-report): add classifier for đang/hoàn thành/trễ hạn buckets"
```

---

## Task 2: Rewrite `MonthlyReportSummarySheet` to the new 7-column layout

**Files:**
- Modify: `app/Modules/TaskAssignment/Exports/MonthlyReportSummarySheet.php` (full rewrite of `array()` and `styles()`; keep `classify()` + `__construct` signature `string $month`).

- [ ] **Step 1: Replace `array()` method**

In `app/Modules/TaskAssignment/Exports/MonthlyReportSummarySheet.php`, replace the entire body of `array()` with:

```php
    public function array(): array
    {
        $monthStart = Carbon::parse($this->month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $now = Carbon::now();

        $departments = TaskAssignmentDepartment::where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        $rows = [];

        // Row 1: Title (merged in styles())
        $rows[] = [
            'TỔNG HỢP NHIỆM VỤ TRÊN PHẦN MỀM GIAO VIỆC CỦA CÁC PHÒNG, ĐƠN VỊ đến '
                . $monthEnd->format('j') . ' tháng ' . $monthEnd->format('n'),
        ];

        // Row 2: Column headers
        $rows[] = [
            'STT',
            'ĐƠN VỊ',
            'TỔNG SỐ NHIỆM VỤ UBND GIAO',
            'NHIỆM VỤ ĐANG GIAO',
            'NHIỆM VỤ HOÀN THÀNH',
            'NHIỆM VỤ TRỄ HẠN',
            'GHI CHÚ',
        ];

        $totals = ['total' => 0, 'in_flight' => 0, 'done' => 0, 'overdue' => 0];

        foreach ($departments as $i => $dept) {
            $items = TaskAssignmentItem::whereHas(
                'users',
                fn ($q) => $q->where('task_assignment_item_user.department_id', $dept->id)
            )
                ->where('created_at', '<=', $monthEnd)
                ->get();

            $counts = ['in_flight' => 0, 'done' => 0, 'overdue' => 0, 'other' => 0];
            foreach ($items as $item) {
                $counts[self::classify($item, $now)]++;
            }

            $total = $items->count();
            $rows[] = [
                $this->toRoman($i + 1),
                $dept->name,
                $total,
                $counts['in_flight'],
                $counts['done'],
                $counts['overdue'],
                '',
            ];

            $totals['total'] += $total;
            $totals['in_flight'] += $counts['in_flight'];
            $totals['done'] += $counts['done'];
            $totals['overdue'] += $counts['overdue'];
        }

        // Totals row
        $rows[] = [
            '',
            'TỔNG CỘNG',
            $totals['total'],
            $totals['in_flight'],
            $totals['done'],
            $totals['overdue'],
            '',
        ];

        return $rows;
    }

    private function toRoman(int $n): string
    {
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        $out = '';
        foreach ($map as $value => $symbol) {
            while ($n >= $value) {
                $out .= $symbol;
                $n -= $value;
            }
        }
        return $out;
    }
```

- [ ] **Step 2: Replace `styles()` method**

Replace the entire body of `styles()` with:

```php
    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Merge the title row across all 7 columns
        $sheet->mergeCells('A1:G1');

        // Borders + center alignment on the table (headers + data + totals)
        $sheet->getStyle("A2:G{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Left-align department name column in data rows
        if ($lastRow >= 3) {
            $sheet->getStyle("B3:B{$lastRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // Row heights
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(50);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            $lastRow => [
                'font' => ['bold' => true],
            ],
        ];
    }
```

- [ ] **Step 3: Remove now-unused imports and fields**

Still in `MonthlyReportSummarySheet.php`:

1. Delete the `private array $itemTypes;` and `private array $statusLabels;` properties.
2. Delete the `private int $tableStartRow = 6;` property.
3. Empty the body of `__construct` so only the `private string $month` promoted property remains:

```php
    public function __construct(private string $month) {}
```

4. Delete the `private function flatStatusTypeKeys(array $typeIds): array` helper.
5. Remove these imports (they were only used by the deleted code):

```php
use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemType;
```

Keep these imports (still used): `TaskAssignmentDepartment`, `TaskAssignmentItem`, `Carbon`, `FromArray`, `ShouldAutoSize`, `WithStyles`, `WithTitle`, `Alignment`, `Border`, `Fill`, `Worksheet`.

- [ ] **Step 4: Re-run classifier tests to confirm still passing**

Run: `php artisan test --filter=MonthlyReportClassifierTest`
Expected: PASS — classifier still works after the rewrite.

- [ ] **Step 5: Sanity-check `php -l` syntax**

Run: `php -l app/Modules/TaskAssignment/Exports/MonthlyReportSummarySheet.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/TaskAssignment/Exports/MonthlyReportSummarySheet.php
git commit -m "feat(monthly-report): rewrite summary sheet to 7-column UBND layout"
```

---

## Task 3: Rewrite `MonthlyReportDepartmentSheet` to the 3-parallel-column layout

The department sheet now lists task names in three independent columns (Đang giao | Hoàn thành | Trễ hạn). Column heights differ — shorter columns have trailing blank cells. Row count = `max(count(in_flight), count(done), count(overdue))`.

**Layout:**

```
Row 1: TỔNG HỢP NHIỆM VỤ TRÊN PHẦN MỀM GIAO VIỆC CỦA CÁC PHÒNG, ĐƠN VỊ đến {d} tháng {m}   (merged A1:G1)
Row 2: [code] | NHIỆM VỤ ĐANG GIAO | NHIỆM VỤ HOÀN THÀNH | NHIỆM VỤ TRỄ HẠN | ĐỀ XUẤT, KIẾN NGHỊ | XIN Ý KIẾN BÁO CÁO LÃNH ĐẠO UBND TUẦN ĐẾN | GHI CHÚ
Row 3: "Tổng hợp" | {count in_flight} | {count done} | {count overdue} | '' | '' | ''
Row 4..N: {i} | {task name in_flight[i] or ''} | {task name done[i] or ''} | {task name overdue[i] or ''} | '' | '' | ''
```

The leftmost column (column A): row 2 is the department code; row 3 is the literal text `"Tổng hợp"`; rows 4..N are 1-based row numbers (sequential STT).

**Files:**
- Modify: `app/Modules/TaskAssignment/Exports/MonthlyReportDepartmentSheet.php` (full rewrite of `array()` and `styles()`; keep `__construct(string $month, TaskAssignmentDepartment $department)` signature and `title()`).

- [ ] **Step 1: Replace `array()` method**

Replace `array()` in `app/Modules/TaskAssignment/Exports/MonthlyReportDepartmentSheet.php` with:

```php
    public function array(): array
    {
        $monthStart = Carbon::parse($this->month . '-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $now = Carbon::now();

        $items = TaskAssignmentItem::whereHas(
            'users',
            fn ($q) => $q->where('task_assignment_item_user.department_id', $this->department->id)
        )
            ->where('created_at', '<=', $monthEnd)
            ->orderBy('end_at')
            ->get();

        $inFlight = [];
        $done = [];
        $overdue = [];
        foreach ($items as $item) {
            $bucket = MonthlyReportSummarySheet::classify($item, $now);
            if ($bucket === 'in_flight') {
                $inFlight[] = $item->name;
            } elseif ($bucket === 'done') {
                $done[] = $item->name;
            } elseif ($bucket === 'overdue') {
                $overdue[] = $item->name;
            }
        }

        $rows = [];

        // Row 1: Title (merged in styles())
        $rows[] = [
            'TỔNG HỢP NHIỆM VỤ TRÊN PHẦN MỀM GIAO VIỆC CỦA CÁC PHÒNG, ĐƠN VỊ đến '
                . $monthEnd->format('j') . ' tháng ' . $monthEnd->format('n'),
        ];

        // Row 2: Column headers (cell A2 is the department code)
        $rows[] = [
            $this->department->code,
            'NHIỆM VỤ ĐANG GIAO',
            'NHIỆM VỤ HOÀN THÀNH',
            'NHIỆM VỤ TRỄ HẠN',
            'ĐỀ XUẤT, KIẾN NGHỊ',
            'XIN Ý KIẾN BÁO CÁO LÃNH ĐẠO UBND TUẦN ĐẾN',
            'GHI CHÚ',
        ];

        // Row 3: Summary counts
        $rows[] = [
            'Tổng hợp',
            count($inFlight),
            count($done),
            count($overdue),
            '',
            '',
            '',
        ];

        // Rows 4..N: Task names, 3 parallel columns
        $maxLen = max(count($inFlight), count($done), count($overdue));
        for ($i = 0; $i < $maxLen; $i++) {
            $rows[] = [
                $i + 1,
                $inFlight[$i] ?? '',
                $done[$i] ?? '',
                $overdue[$i] ?? '',
                '',
                '',
                '',
            ];
        }

        return $rows;
    }
```

- [ ] **Step 2: Replace `styles()` method**

Replace `styles()` with:

```php
    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Merge the title row across 7 columns
        $sheet->mergeCells('A1:G1');

        // Borders + alignment on the whole table (rows 2..lastRow)
        if ($lastRow >= 2) {
            $sheet->getStyle("A2:G{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);
        }

        // Center STT column (A) and summary counts (row 3, B..D)
        if ($lastRow >= 3) {
            $sheet->getStyle("A3:A{$lastRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B3:D3')->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Task-name columns wider
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(30);
        $sheet->getColumnDimension('G')->setWidth(15);

        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(50);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            3 => [
                'font' => ['bold' => true],
            ],
        ];
    }
```

- [ ] **Step 3: Remove now-unused imports, helpers, and fields**

Still in `MonthlyReportDepartmentSheet.php`:

1. Delete the `private function priorityLabel(string $priority): string` method.
2. Remove now-unused imports:

```php
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use PhpOffice\PhpSpreadsheet\Style\Fill;
```

Keep all other existing imports.

- [ ] **Step 4: Confirm `title()` unchanged**

Verify `title()` still returns `mb_substr($this->department->code, 0, 31)`. No edit needed.

- [ ] **Step 5: Sanity-check syntax**

Run: `php -l app/Modules/TaskAssignment/Exports/MonthlyReportDepartmentSheet.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/TaskAssignment/Exports/MonthlyReportDepartmentSheet.php
git commit -m "feat(monthly-report): rewrite department sheet to 3-parallel-column UBND layout"
```

---

## Task 4: Remove next-month sheet from export pipeline

**Files:**
- Modify: `app/Modules/TaskAssignment/Exports/MonthlyReportExport.php`

- [ ] **Step 1: Simplify `sheets()` and constructor**

Replace the full content of `app/Modules/TaskAssignment/Exports/MonthlyReportExport.php` with:

```php
<?php

namespace App\Modules\TaskAssignment\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyReportExport implements WithMultipleSheets
{
    public function __construct(private string $month) {}

    public function sheets(): array
    {
        $sheets = [new MonthlyReportSummarySheet($this->month)];

        $departments = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        foreach ($departments as $dept) {
            $sheets[] = new MonthlyReportDepartmentSheet($this->month, $dept);
        }

        return $sheets;
    }
}
```

- [ ] **Step 2: Check service call site still compiles**

Verify no caller passes a second argument to `MonthlyReportExport`:

Run: `grep -rn "new MonthlyReportExport" app/`
Expected: Only `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php:160` — and it passes only `$month`. No edits needed.

- [ ] **Step 3: Sanity-check syntax**

Run: `php -l app/Modules/TaskAssignment/Exports/MonthlyReportExport.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/TaskAssignment/Exports/MonthlyReportExport.php
git commit -m "refactor(monthly-report): drop next-month sheet from export pipeline"
```

---

## Task 5: End-to-end feature test

Verify the export produces the expected sheet count, titles, and cell values with real DB fixtures.

**Files:**
- Create: `tests/Feature/TaskAssignment/MonthlyReportExportTest.php`

- [ ] **Step 1: Check how Feature tests are bootstrapped**

Run: `cat tests/Feature/ExampleTest.php`
Expected: See whether the project uses `RefreshDatabase`, what base class to extend (`Tests\TestCase`), and how the DB is set up. Adapt the test below accordingly.

If `Tests\TestCase` does not exist, fall back to `use Tests\TestCase;` and check `tests/TestCase.php` for the base. If `RefreshDatabase` causes issues (no test DB configured), document that the test requires a test DB and stop — do not silently fall back to mocking.

- [ ] **Step 2: Write the failing feature test**

File: `tests/Feature/TaskAssignment/MonthlyReportExportTest.php`

```php
<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\TaskAssignment\Exports\MonthlyReportExport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelContract;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MonthlyReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_produces_summary_plus_one_sheet_per_active_department(): void
    {
        $dept1 = TaskAssignmentDepartment::factory()->create([
            'code' => 'KTHT',
            'name' => 'Kinh tế Hạ tầng',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $dept2 = TaskAssignmentDepartment::factory()->create([
            'code' => 'VHXH',
            'name' => 'Văn hóa Xã hội',
            'status' => 'active',
            'sort_order' => 2,
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'mr_') . '.xlsx';
        Excel::store(new MonthlyReportExport('2026-03'), basename($tmp), null, ExcelContract::XLSX);
        // Excel::store writes relative to the default disk; adjust path lookup if needed.

        $path = storage_path('app/' . basename($tmp));
        $this->assertFileExists($path);

        $book = IOFactory::load($path);
        $this->assertSame(3, $book->getSheetCount(), 'Expected 1 summary + 2 dept sheets');
        $this->assertSame('Tổng hợp', $book->getSheet(0)->getTitle());
        $this->assertSame('KTHT', $book->getSheet(1)->getTitle());
        $this->assertSame('VHXH', $book->getSheet(2)->getTitle());

        // Summary title contains the cutoff phrase
        $title = $book->getSheet(0)->getCell('A1')->getValue();
        $this->assertStringContainsString('đến 31 tháng 3', $title);

        @unlink($path);
    }

    public function test_summary_counts_match_classifier_buckets(): void
    {
        $dept = TaskAssignmentDepartment::factory()->create([
            'code' => 'VP',
            'name' => 'Văn phòng',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        // Seed: 1 done, 1 in_progress (future deadline), 1 in_progress (past deadline = overdue)
        $this->makeItemFor($dept, 'done', 'no_deadline', null);
        $this->makeItemFor($dept, 'in_progress', 'has_deadline', '2030-01-01');
        $this->makeItemFor($dept, 'in_progress', 'has_deadline', '2020-01-01');

        $tmp = tempnam(sys_get_temp_dir(), 'mr_') . '.xlsx';
        Excel::store(new MonthlyReportExport('2026-03'), basename($tmp), null, ExcelContract::XLSX);
        $path = storage_path('app/' . basename($tmp));

        $summary = IOFactory::load($path)->getSheet(0);
        // Row 3 is the first (and only) department data row
        $this->assertSame(3, (int) $summary->getCell('C3')->getValue(), 'Tổng');
        $this->assertSame(1, (int) $summary->getCell('D3')->getValue(), 'Đang giao');
        $this->assertSame(1, (int) $summary->getCell('E3')->getValue(), 'Hoàn thành');
        $this->assertSame(1, (int) $summary->getCell('F3')->getValue(), 'Trễ hạn');

        @unlink($path);
    }

    private function makeItemFor(TaskAssignmentDepartment $dept, string $status, string $deadlineType, ?string $endAt): TaskAssignmentItem
    {
        $item = TaskAssignmentItem::factory()->create([
            'processing_status' => $status,
            'deadline_type' => $deadlineType,
            'end_at' => $endAt,
            'created_at' => '2026-03-15',
        ]);
        // Attach via the pivot with department_id
        $item->users()->attach(1, ['department_id' => $dept->id]);
        return $item;
    }
}
```

- [ ] **Step 3: Run and debug**

Run: `php artisan test --filter=MonthlyReportExportTest`

If factories or `RefreshDatabase` are not wired up for these models, the test will fail with missing-factory errors. In that case:

1. Check whether `TaskAssignmentDepartment` / `TaskAssignmentItem` have factories (run `find database/factories -name 'TaskAssignment*'`).
2. If they don't, reduce the test to creating rows via direct `DB::table(...)->insert(...)` calls using the columns listed in the Context section. Keep the same assertions.
3. If `RefreshDatabase` is incompatible with the project's DB config, talk to the user before proceeding — do not silently switch to mocks.

Expected (after fixes): PASS — 2 tests.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/TaskAssignment/MonthlyReportExportTest.php
git commit -m "test(monthly-report): end-to-end export sheet structure + counts"
```

---

## Task 6: Manual smoke test in the running app

Tests cover structure, but humans need to eyeball the result against the spec screenshots.

- [ ] **Step 1: Boot the app and hit the endpoint**

Run the dev server the usual way for this project (e.g. `php artisan serve` plus any queue/asset tooling). Log in as a user with `task-assignment-items.exportMonthlyReport` permission, and download the report for a month with real data:

```
GET /api/task-assignment/items/export-monthly-report?month=2026-03
```

- [ ] **Step 2: Open the file in Excel and verify**

Check against the spec screenshots:

1. Sheet 1 title is `Tổng hợp`; title cell reads `TỔNG HỢP NHIỆM VỤ... đến 31 tháng 3`.
2. Summary sheet has exactly 7 columns: STT (Roman I, II, …), ĐƠN VỊ, Tổng, Đang giao, Hoàn thành, Trễ hạn, Ghi chú. One row per active department plus a TỔNG CỘNG row.
3. Each subsequent tab's name matches a `department.code` (e.g. `VP`, `KTHT`, `VHXH`).
4. On each department tab: row 2 cell A is the department code; row 3 cell A is `Tổng hợp` and cells B/C/D hold the bucket counts; rows 4+ list task names in three parallel columns.
5. Columns `ĐỀ XUẤT, KIẾN NGHỊ`, `XIN Ý KIẾN BÁO CÁO LÃNH ĐẠO UBND TUẦN ĐẾN`, `GHI CHÚ` are all blank.
6. No next-month sheet appears.

- [ ] **Step 3: Report back**

If any visual detail (colors, merges, column widths, row heights) diverges from the screenshots in a way that bothers the user, ask before tweaking — don't refactor styling speculatively.

---

## Out of Scope (explicitly)

- Deleting `MonthlyReportNextMonthSheet.php` — stakeholder said keep the file, just unwire it.
- Changing the controller or route.
- Adding a frontend UI toggle for the new format.
- Localizing the title or column headers.
- Exporting PDF.
- Any permission changes (`exportMonthlyReport` perm already exists).
