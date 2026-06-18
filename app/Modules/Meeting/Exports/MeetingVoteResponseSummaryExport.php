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
            ->with('meeting.chairperson')
            ->get()
            ->sortBy(fn ($t) => sprintf('%010d|%010d',
                $treeIndex[$t->meeting_agenda_id] ?? PHP_INT_MAX,
                $t->sort_order ?? 0
            ))
            ->values();

        // Cache tổng số người có quyền biểu quyết theo meeting_id
        $eligibleCountByMeeting = [];

        return $topics->values()->map(function ($topic, $i) use (&$eligibleCountByMeeting) {
            $base = MeetingVoteResponse::query()->where('meeting_vote_topic_id', $topic->id);

            // Tổng người có quyền biểu quyết = participants + chairperson
            $meetingId = $topic->meeting_id;
            if (! isset($eligibleCountByMeeting[$meetingId])) {
                $participantUserIds = MeetingParticipant::query()
                    ->where('meeting_id', $meetingId)
                    ->with('attendee')
                    ->get()
                    ->pluck('attendee.user_id')
                    ->filter()
                    ->unique()
                    ->values();

                $chairUserId = $topic->meeting?->chairperson?->user_id;
                if ($chairUserId && ! $participantUserIds->contains($chairUserId)) {
                    $participantUserIds->push($chairUserId);
                }

                $eligibleCountByMeeting[$meetingId] = $participantUserIds->count();
            }

            $votedCount = (clone $base)->count();
            $eligibleCount = $eligibleCountByMeeting[$meetingId];

            $agreeCount = (clone $base)->whereIn('option', ['agree', 'approve'])->count();
            $disagreeCount = (clone $base)->whereIn('option', ['disagree', 'reject'])->count();
            $abstainCount = (clone $base)->where('option', 'abstain')->count();
            $notVotedCount = max(0, $eligibleCount - $votedCount);

            return [
                'stt' => $i + 1,
                'topic' => $topic->title,
                'agree' => $this->formatVoteCell($agreeCount, $eligibleCount, $votedCount),
                'disagree' => $this->formatVoteCell($disagreeCount, $eligibleCount, $votedCount),
                'abstain' => $this->formatVoteCell($abstainCount, $eligibleCount, $votedCount),
                'not_voted' => $notVotedCount . ($eligibleCount > 0 ? ' (' . round($notVotedCount / $eligibleCount * 100, 1) . '%)' : ''),
            ];
        });
    }

    private function formatVoteCell(int $count, int $total, int $present): string
    {
        if ($count === 0) {
            return '0';
        }
        $pctTotal = $total > 0 ? round($count / $total * 100, 1) : 0;
        $pctPresent = $present > 0 ? round($count / $present * 100, 1) : 0;

        return sprintf("%d (Tổng: %s%% - Có mặt: %s%%)", $count, $pctTotal, $pctPresent);
    }

    public function headings(): array
    {
        return ['STT', 'Nội dung biểu quyết', 'Đồng ý / Tán thành', 'Không đồng ý / Không tán thành', 'Không ý kiến', 'Chưa biểu quyết'];
    }
}
