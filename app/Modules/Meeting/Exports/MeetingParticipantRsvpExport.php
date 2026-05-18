<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Models\MeetingParticipant;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất xác nhận tham gia của đại biểu — chỉ focus RSVP, không bao gồm điểm danh.
 * Tách riêng khỏi MeetingAttendanceExport (vốn gộp cả response + attendance).
 *
 * Columns: STT, Tên đại biểu, Chức vụ, Xác nhận tham gia, Lý do, Thời gian phản hồi.
 */
class MeetingParticipantRsvpExport extends AbstractExcelExport implements FromCollection
{
    private const RESPONSE_LABELS = [
        'accepted' => 'Đồng ý',
        'declined' => 'Từ chối',
        'pending' => 'Chưa phản hồi',
    ];

    public function __construct(private int $meetingId) {}

    public function collection()
    {
        return MeetingParticipant::query()
            ->with('attendee')
            ->where('meeting_id', $this->meetingId)
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn ($p, $i) => [
                'stt' => $i + 1,
                'name' => $p->display_name ?? $p->attendee?->name ?? '',
                'position' => $p->position_name ?? '',
                'response' => self::RESPONSE_LABELS[$p->response_status] ?? (string) $p->response_status,
                // Lý do chỉ hiển thị khi response_status=declined; còn lại trống để khỏi gây nhiễu.
                'reason' => $p->response_status === 'declined' ? (string) ($p->absence_reason ?? '') : '',
                'responded_at' => $p->responded_at?->format('H:i:s d/m/Y') ?? '',
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên đại biểu', 'Chức vụ', 'Xác nhận tham gia', 'Lý do', 'Thời gian phản hồi'];
    }
}
