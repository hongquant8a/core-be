<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MeetingVoteResponseSummarySheet extends \App\Modules\Core\Exports\AbstractExcelExport implements FromCollection, WithTitle
{
    public function __construct(
        private string $type,
        private ?int $meetingId = null,
        private ?int $topicId = null
    ) {}

    public function title(): string
    {
        return $this->type === 'total' ? 'Theo tổng số' : 'Theo số có mặt';
    }

    public function collection()
    {
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

        $eligibleCountByMeeting = [];

        return $topics->values()->map(function ($topic, $i) use (&$eligibleCountByMeeting) {
            $base = MeetingVoteResponse::query()->where('meeting_vote_topic_id', $topic->id);

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
                'not_voted' => $this->formatVoteCell($notVotedCount, $eligibleCount, $votedCount, true),
            ];
        });
    }

    private function formatVoteCell(int $count, int $total, int $present, bool $isNotVoted = false): string
    {
        if ($count === 0) {
            return '0';
        }

        if ($this->type === 'total') {
            $pct = $total > 0 ? round($count / $total * 100, 1) : 0;
            return sprintf("%d (%s%%)", $count, $pct);
        }

        // type === 'present'
        if ($isNotVoted) {
            // Chưa biểu quyết đối với danh sách có mặt không thực sự có ý nghĩa nếu họ không vote.
            // Nhưng nếu chia theo có mặt (người đã vote) thì phần trăm này có thể không cần chia.
            // Hoặc có thể hiểu "có mặt" = $present, nhưng not_voted là những người không có mặt hoặc chưa vote.
            // Thông thường sẽ lấy / $total, vì họ không nằm trong $present.
            // Để giữ logic, ta dùng $total cho cột này nếu chọn 'present' hoặc tuỳ ý.
            // Nhưng spec thường là: nếu đếm % trên tổng -> chia tổng. Đếm % trên có mặt -> chia người đã vote ($present).
            $pct = $total > 0 ? round($count / $total * 100, 1) : 0;
            return sprintf("%d (%s%%)", $count, $pct);
        }

        $pct = $present > 0 ? round($count / $present * 100, 1) : 0;
        return sprintf("%d (%s%%)", $count, $pct);
    }

    public function headings(): array
    {
        return ['STT', 'Nội dung biểu quyết', 'Đồng ý / Tán thành', 'Không đồng ý / Không tán thành', 'Không ý kiến', 'Chưa biểu quyết'];
    }
}
