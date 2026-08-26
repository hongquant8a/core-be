<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Người thực hiện nộp một báo cáo tiến độ → báo cho người giao việc biết có báo
 * cáo mới cần xem/duyệt. Fire ở TaskAssignmentReportService::store.
 */
class ReportSubmitted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentItem $item,
        public TaskAssignmentItemReport $report,
    ) {}
}
