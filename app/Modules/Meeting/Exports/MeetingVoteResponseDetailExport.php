<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Enums\MeetingBallotModeEnum;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất chi tiết biểu quyết của 1 topic — mỗi row = 1 phiếu của 1 user.
 * Anonymize tên nếu topic.ballot_mode = anonymous (đảm bảo bảo mật theo spec).
 *
 * Columns: STT, Nội dung biểu quyết, Tên đại biểu, Biểu quyết.
 */
class MeetingVoteResponseDetailExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private int $topicId) {}

    public function collection()
    {
        $topic = MeetingVoteTopic::find($this->topicId);
        if (! $topic) {
            return collect();
        }

        $isAnonymous = $topic->ballot_mode === MeetingBallotModeEnum::Anonymous->value;

        return MeetingVoteResponse::query()
            ->with(['participant.attendee.user', 'user'])
            ->where('meeting_vote_topic_id', $this->topicId)
            ->orderBy('voted_at')
            ->get()
            ->values()
            ->map(function ($r, $i) use ($topic, $isAnonymous) {
                // Ưu tiên display_name từ participant (snapshot), fallback user.name (chair/op không có participant).
                $name = $r->participant?->display_name
                    ?? $r->participant?->attendee?->user?->name
                    ?? $r->user?->name
                    ?? '';

                return [
                    'stt' => $i + 1,
                    'topic' => $topic->title,
                    'voter' => $isAnonymous ? 'Ẩn danh' : $name,
                    'option' => $this->optionLabel($r->option),
                ];
            });
    }

    public function headings(): array
    {
        return ['STT', 'Nội dung biểu quyết', 'Tên đại biểu', 'Biểu quyết'];
    }

    private function optionLabel(?string $option): string
    {
        return match ($option) {
            'approve' => 'Tán thành',
            'reject' => 'Không tán thành',
            'agree' => 'Đồng ý',
            'disagree' => 'Không đồng ý',
            'abstain' => 'Không ý kiến',
            default => (string) $option,
        };
    }
}
