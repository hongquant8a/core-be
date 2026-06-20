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
        $presentCountByMeeting = [];

        return $topics->values()->map(function ($topic, $i) use (&$eligibleCountByMeeting, &$presentCountByMeeting) {
            $base = MeetingVoteResponse::query()->where('meeting_vote_topic_id', $topic->id);

            $meetingId = $topic->meeting_id;
            if (! isset($eligibleCountByMeeting[$meetingId])) {
                $participants = MeetingParticipant::query()
                    ->where('meeting_id', $meetingId)
                    ->with(['attendee', 'attendance'])
                    ->get();

                $participantUserIds = $participants->pluck('attendee.user_id')->filter()->unique()->values();
                $presentUserIds = $participants->filter(fn ($p) => $p->attendance && $p->attendance->status === 'present')
                    ->pluck('attendee.user_id')->filter()->unique()->values();

                $eligibleCountByMeeting[$meetingId] = $participantUserIds->count();
                $presentCountByMeeting[$meetingId] = $presentUserIds->count();
            }

            $eligibleCount = $eligibleCountByMeeting[$meetingId];
            $presentCount = $presentCountByMeeting[$meetingId];
            $votedCount = (clone $base)->count();

            $agreeCount = (clone $base)->whereIn('option', ['agree', 'approve'])->count();
            $disagreeCount = (clone $base)->whereIn('option', ['disagree', 'reject'])->count();
            $abstainCount = (clone $base)->where('option', 'abstain')->count();
            $notVotedCount = $this->type === 'total'
                ? max(0, $eligibleCount - $votedCount)
                : max(0, $presentCount - $votedCount);

            return [
                'stt' => $i + 1,
                'topic' => $topic->title,
                'agree' => $this->formatVoteCell($agreeCount, $eligibleCount, $presentCount),
                'disagree' => $this->formatVoteCell($disagreeCount, $eligibleCount, $presentCount),
                'abstain' => $this->formatVoteCell($abstainCount, $eligibleCount, $presentCount),
                'not_voted' => $this->formatVoteCell($notVotedCount, $eligibleCount, $presentCount),
            ];
        });
    }

    private function formatVoteCell(int $count, int $total, int $present): string
    {
        if ($count === 0) {
            return '0';
        }

        if ($this->type === 'total') {
            $pct = $total > 0 ? round($count / $total * 100, 1) : 0;
            return sprintf("%d (%s%%)", $count, $pct);
        }

        // type === 'present'
        $pct = $present > 0 ? round($count / $present * 100, 1) : 0;
        return sprintf("%d (%s%%)", $count, $pct);
    }

    public function headings(): array
    {
        return ['STT', 'Nội dung biểu quyết', 'Đồng ý / Tán thành', 'Không đồng ý / Không tán thành', 'Không ý kiến', 'Chưa biểu quyết'];
    }
}
