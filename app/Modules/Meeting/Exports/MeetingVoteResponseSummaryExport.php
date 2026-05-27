<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất tổng hợp biểu quyết — mỗi row = 1 topic + đếm số phiếu theo option.
 * Filter: meeting_id (export tất cả topic của 1 meeting) hoặc meeting_vote_topic_id (1 topic).
 *
 * Columns: STT, Nội dung biểu quyết, Đồng ý / Tán thành, Không đồng ý / Không tán thành, Không ý kiến.
 */
class MeetingVoteResponseSummaryExport extends AbstractExcelExport implements FromCollection
{
    /**
     * @param  ?int  $meetingId  filter mọi topic của meeting
     * @param  ?int  $topicId    filter 1 topic
     */
    public function __construct(private ?int $meetingId = null, private ?int $topicId = null) {}

    public function collection()
    {
        // Agenda có phân cấp parent-child → sort theo tree_index thay vì flat agenda_id.
        // Cần meetingId để build tree map; nếu chỉ có topicId thì derive meetingId từ topic.
        $resolvedMeetingId = $this->meetingId
            ?? MeetingVoteTopic::find($this->topicId)?->meeting_id;
        $treeIndex = $resolvedMeetingId
            ? \App\Modules\Meeting\Models\MeetingAgenda::treeIndexMap($resolvedMeetingId)
            : [];
        $topics = MeetingVoteTopic::query()
            ->when($this->meetingId, fn ($q) => $q->where('meeting_id', $this->meetingId))
            ->when($this->topicId, fn ($q) => $q->where('id', $this->topicId))
            ->get()
            ->sortBy(fn ($t) => sprintf('%010d|%010d',
                $treeIndex[$t->meeting_agenda_id] ?? PHP_INT_MAX,
                $t->sort_order ?? 0
            ))
            ->values();

        return $topics->values()->map(function ($topic, $i) {
            $base = MeetingVoteResponse::query()->where('meeting_vote_topic_id', $topic->id);

            return [
                'stt' => $i + 1,
                'topic' => $topic->title,
                'agree' => (clone $base)->whereIn('option', ['agree', 'approve'])->count(),
                'disagree' => (clone $base)->whereIn('option', ['disagree', 'reject'])->count(),
                'abstain' => (clone $base)->where('option', 'abstain')->count(),
            ];
        });
    }

    public function headings(): array
    {
        return ['STT', 'Nội dung biểu quyết', 'Đồng ý / Tán thành', 'Không đồng ý / Không tán thành', 'Không ý kiến'];
    }
}
