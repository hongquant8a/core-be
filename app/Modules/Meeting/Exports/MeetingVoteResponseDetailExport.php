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

        // Tập hợp tất cả người có quyền biểu quyết:
        // 1. Đại biểu được mời (MeetingParticipant)
        // 2. Chủ trì cuộc họp (chairperson)
        $voterRows = [];
        $seenUserIds = [];

        // Đại biểu (participants) — dedup theo user_id
        $participants = MeetingParticipant::query()
            ->with('attendee.user')
            ->where('meeting_id', $meeting->id)
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

        // Chủ trì (chairperson) — thêm nếu chưa có trong danh sách đại biểu
        $chairAttendee = $meeting->chairperson;
        if ($chairAttendee && ! in_array($chairAttendee->user_id, $seenUserIds, true)) {
            $voterRows[] = [
                'user_id' => $chairAttendee->user_id,
                'name' => $chairAttendee->user?->name ?? '',
                'option' => null,
            ];
            $seenUserIds[] = $chairAttendee->user_id;
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

        // --- Tính thống kê ---
        $totalDelegates = count($voterRows);
        $votedRows = array_filter($voterRows, fn ($r) => $r['option'] !== 'Chưa biểu quyết');
        $presentCount = count($votedRows);

        // Đếm từng lựa chọn
        $optionCounts = [];
        foreach ($votedRows as $r) {
            $label = $r['option'];
            $optionCounts[$label] = ($optionCounts[$label] ?? 0) + 1;
        }
        $notVotedCount = $totalDelegates - $presentCount;

        // --- Build detail rows ---
        $detailRows = collect($voterRows)->values()->map(function ($r, $i) use ($topic, $isAnonymous) {
            return [
                'stt'    => $i + 1,
                'topic'  => $topic->title,
                'voter'  => $isAnonymous ? 'Ẩn danh' : $r['name'],
                'option' => $r['option'],
            ];
        });

        // --- Separator ---
        $blank = ['stt' => '', 'topic' => '', 'voter' => '', 'option' => ''];

        // --- Summary 1: Theo tổng đại biểu ---
        $summary1 = collect();
        $summary1->push(['stt' => '', 'topic' => 'KẾT QUẢ THEO TỔNG ĐẠI BIỂU', 'voter' => "Tổng: {$totalDelegates} đại biểu", 'option' => '']);
        foreach ($optionCounts as $label => $count) {
            $pct = $totalDelegates > 0 ? round($count / $totalDelegates * 100, 1) : 0;
            $summary1->push(['stt' => '', 'topic' => '', 'voter' => $label, 'option' => "{$count}/{$totalDelegates} ({$pct}%)"]);
        }
        if ($notVotedCount > 0) {
            $pct = $totalDelegates > 0 ? round($notVotedCount / $totalDelegates * 100, 1) : 0;
            $summary1->push(['stt' => '', 'topic' => '', 'voter' => 'Chưa biểu quyết', 'option' => "{$notVotedCount}/{$totalDelegates} ({$pct}%)"]);
        }

        // --- Summary 2: Theo đại biểu có mặt ---
        $summary2 = collect();
        $summary2->push(['stt' => '', 'topic' => 'KẾT QUẢ THEO ĐẠI BIỂU CÓ MẶT', 'voter' => "Có mặt: {$presentCount}/{$totalDelegates} đại biểu", 'option' => '']);
        foreach ($optionCounts as $label => $count) {
            $pct = $presentCount > 0 ? round($count / $presentCount * 100, 1) : 0;
            $summary2->push(['stt' => '', 'topic' => '', 'voter' => $label, 'option' => "{$count}/{$presentCount} ({$pct}%)"]);
        }

        return $detailRows
            ->push($blank)
            ->concat($summary1)
            ->push($blank)
            ->concat($summary2);
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
