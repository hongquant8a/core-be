<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Enums\MeetingBallotModeEnum;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất chi tiết biểu quyết của 1 topic — mỗi row = 1 người có quyền biểu quyết
 * (đại biểu được mời + chủ trì), kể cả người chưa biểu quyết.
 * Anonymize tên nếu topic.ballot_mode = anonymous (đảm bảo bảo mật theo spec).
 *
 * Columns: STT, Nội dung biểu quyết, Tên đại biểu, Biểu quyết.
 * Cuối file: 2 bảng tổng kết theo tổng đại biểu và theo đại biểu có mặt.
 */
class MeetingVoteResponseDetailExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private int $topicId) {}

    public function collection()
    {
        $topic = MeetingVoteTopic::with('meeting.chairperson.user')->find($this->topicId);
        if (! $topic || ! $topic->meeting) {
            return collect();
        }

        $isAnonymous = $topic->ballot_mode === MeetingBallotModeEnum::Anonymous->value;
        $meeting = $topic->meeting;

        // Tập hợp những người có quyền biểu quyết VÀ có mặt
        $voterRows = [];
        $seenUserIds = [];

        // Đại biểu (participants) có mặt — dedup theo user_id
        $participants = MeetingParticipant::query()
            ->with(['attendee.user'])
            ->where('meeting_id', $meeting->id)
            ->whereHas('attendance', fn ($q) => $q->where('status', 'present'))
            ->get();

        foreach ($participants as $p) {
            $userId = $p->attendee?->user_id;
            if ($userId && in_array($userId, $seenUserIds, true)) {
                continue;
            }
            $name = $p->display_name
                ?? $p->attendee?->user?->name
                ?? '';

            $voterRows[] = [
                'user_id' => $userId,
                'name' => $name,
                'option' => null,
            ];
            if ($userId) {
                $seenUserIds[] = $userId;
            }
        }

        // Lấy phiếu đã bỏ, index theo user_id
        $responses = MeetingVoteResponse::query()
            ->where('meeting_vote_topic_id', $this->topicId)
            ->get()
            ->keyBy('user_id');

        // Gán option từ phiếu đã bỏ (nếu có)
        foreach ($voterRows as &$row) {
            $response = $responses->get($row['user_id']);
            $row['option'] = $response ? $this->optionLabel($response->option) : 'Chưa biểu quyết';
            $row['raw_option'] = $response?->option;
        }
        unset($row);

        // Sắp xếp: người đã biểu quyết trước, rồi đến người chưa biểu quyết
        usort($voterRows, function ($a, $b) {
            $aVoted = $a['option'] !== 'Chưa biểu quyết' ? 0 : 1;
            $bVoted = $b['option'] !== 'Chưa biểu quyết' ? 0 : 1;
            return $aVoted <=> $bVoted;
        });

        return collect($voterRows)->values()->map(function ($r, $i) use ($topic, $isAnonymous) {
            return [
                'stt'    => $i + 1,
                'topic'  => $topic->title,
                'voter'  => $isAnonymous ? 'Ẩn danh' : $r['name'],
                'option' => $r['option'],
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
            'approve'  => 'Tán thành',
            'reject'   => 'Không tán thành',
            'agree'    => 'Đồng ý',
            'disagree' => 'Không đồng ý',
            'abstain'  => 'Không ý kiến',
            default    => (string) $option,
        };
    }
}
