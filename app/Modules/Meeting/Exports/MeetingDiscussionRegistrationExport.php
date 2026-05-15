<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Enums\MeetingDiscussionStatusEnum;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất danh sách thảo luận hoặc chất vấn — filter type=discussion|question + meeting_id.
 * Columns: STT, Chương trình (agenda.title), Người đăng ký, Thời gian đăng ký, Nội dung, Trạng thái.
 */
class MeetingDiscussionRegistrationExport extends AbstractExcelExport implements FromCollection
{
    /**
     * @param  int  $meetingId
     * @param  string  $type  discussion | question
     */
    public function __construct(private int $meetingId, private string $type) {}

    public function collection()
    {
        return MeetingDiscussionRegistration::query()
            ->with(['agenda', 'participant.attendee.user', 'participant'])
            ->where('meeting_id', $this->meetingId)
            ->where('type', $this->type)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn ($item, $i) => [
                'stt' => $i + 1,
                'agenda' => $item->agenda?->content ?? '',
                'registrant' => $item->participant?->display_name
                    ?? $item->participant?->attendee?->user?->name
                    ?? '',
                'registered_at' => $item->created_at?->format('H:i:s d/m/Y'),
                'content' => $item->content,
                'operator_note' => $item->operator_note ?? '',
                'status' => $this->statusLabel($item->status),
            ]);
    }

    public function headings(): array
    {
        // Cột "Ghi chú thảo luận" (type=discussion) hoặc "Nội dung trả lời" (type=question).
        $extra = $this->type === 'question' ? 'Nội dung trả lời' : 'Ghi chú thảo luận';

        return ['STT', 'Chương trình', 'Người đăng ký', 'Thời gian đăng ký', 'Nội dung', $extra, 'Trạng thái'];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            MeetingDiscussionStatusEnum::Registered->value => 'Đã đăng ký',
            MeetingDiscussionStatusEnum::Completed->value => 'Đã hoàn thành',
            default => (string) $status,
        };
    }
}
