<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Văn bản giao việc bị sửa SAU KHI đã ban hành → báo cho người đang thực hiện
 * các công việc thuộc văn bản đó: nội dung chỉ đạo họ đang bám theo đã đổi.
 *
 * Chỉ fire khi văn bản đang ở trạng thái đã ban hành — sửa bản nháp là việc
 * soạn thảo bình thường, không ai cần biết.
 *
 * Fire ở TaskAssignmentDocumentService::update.
 */
class DocumentUpdated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentDocument $document,
    ) {}
}
