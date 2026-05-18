<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Enums\MeetingAttendanceStatusEnum;
use App\Modules\Meeting\Models\MeetingParticipant;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất danh sách đại biểu tham dự cuộc họp + trạng thái điểm danh.
 * Lấy từ meeting_participants (đầy đủ list được mời), join attendance để biết trạng thái.
 *
 * Columns:
 *  - STT
 *  - Tên đại biểu
 *  - Chức vụ
 *  - Xác nhận tham gia (response_status: pending/accepted/declined)
 *  - Đã điểm danh (đại biểu đã bấm/quét hay chưa: yes/no)
 *  - Đã xác nhận điểm danh (thư ký đã duyệt -> present: yes/no)
 *  - Giờ điểm danh
 */
class MeetingAttendanceExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private int $meetingId) {}

    public function collection()
    {
        return MeetingParticipant::query()
            ->with(['attendee', 'attendance'])
            ->where('meeting_id', $this->meetingId)
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function ($p, $i) {
                $att = $p->attendance;
                $hasCheckin = $att !== null;
                $approved = $hasCheckin && $att->status === MeetingAttendanceStatusEnum::Present->value;

                return [
                    'stt' => $i + 1,
                    'name' => $p->display_name ?? $p->attendee?->name ?? '',
                    'position' => $p->position_name ?? '',
                    'response' => $this->responseLabel($p->response_status),
                    // "Rồi/Chưa" thay vì "Có/Không" — đúng ngữ cảnh hành động đã/chưa xảy ra.
                    'checked_in' => $hasCheckin ? 'Rồi' : 'Chưa',
                    'approved' => $approved ? 'Rồi' : 'Chưa',
                    'checked_in_at' => $att?->checked_in_at?->format('H:i:s d/m/Y') ?? '',
                ];
            });
    }

    public function headings(): array
    {
        return [
            'STT',
            'Tên đại biểu',
            'Chức vụ',
            'Xác nhận tham gia',
            'Đã điểm danh',
            'Đã xác nhận điểm danh',
            'Giờ điểm danh',
        ];
    }

    private function responseLabel(?string $status): string
    {
        return match ($status) {
            'accepted' => 'Đồng ý',
            'declined' => 'Từ chối',
            'pending' => 'Chưa phản hồi',
            default => (string) $status,
        };
    }
}
