<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất tổng hợp biểu quyết — mỗi row = 1 topic + đếm số phiếu theo option.
 * Filter: meeting_id (export tất cả topic của 1 meeting) hoặc meeting_vote_topic_id (1 topic).
 *
 * Columns: STT, Nội dung biểu quyết, Đồng ý / Tán thành, Không đồng ý / Không tán thành, Không ý kiến, Chưa biểu quyết.
 */
class MeetingVoteResponseSummaryExport extends AbstractExcelExport implements \Maatwebsite\Excel\Concerns\WithMultipleSheets
{
    /**
     * @param  ?int  $meetingId  filter mọi topic của meeting
     * @param  ?int  $topicId    filter 1 topic
     */
    public function __construct(private ?int $meetingId = null, private ?int $topicId = null) {}

    public function sheets(): array
    {
        return [
            new MeetingVoteResponseSummarySheet('total', $this->meetingId, $this->topicId),
            new MeetingVoteResponseSummarySheet('present', $this->meetingId, $this->topicId),
        ];
    }
}
