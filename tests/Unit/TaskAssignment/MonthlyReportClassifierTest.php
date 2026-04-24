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

    public function test_cancelled_task_is_cancelled_bucket(): void
    {
        $item = (object) [
            'processing_status' => 'cancelled',
            'deadline_type' => 'has_deadline',
            'end_at' => Carbon::parse('2026-03-10'),
        ];
        $this->assertSame('cancelled', MonthlyReportSummarySheet::classify($item, $this->now));
    }

    public function test_paused_task_is_in_flight(): void
    {
        $item = (object) [
            'processing_status' => 'paused',
            'deadline_type' => 'has_deadline',
            'end_at' => Carbon::parse('2026-04-15'),
        ];
        $this->assertSame('in_flight', MonthlyReportSummarySheet::classify($item, $this->now));
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
