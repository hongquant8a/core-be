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
     * @param  ?int  $meetingAgendaId  Optional — lọc theo chương trình họp. Null = xuất toàn meeting.
     * @param  bool  $onlyMine  Nếu true → chỉ xuất đăng ký của auth user (đại biểu self-export).
     */
    public function __construct(
        private int $meetingId,
        private string $type,
        private ?int $meetingAgendaId = null,
        private bool $onlyMine = false,
    ) {}

    public function collection()
    {
        $authUserId = auth()->id() ?? \Illuminate\Support\Facades\Auth::guard('sanctum')->id();

        return MeetingDiscussionRegistration::query()
            ->with(['agenda', 'participant.attendee.user', 'participant'])
            ->where('meeting_id', $this->meetingId)
            ->where('type', $this->type)
            ->when($this->meetingAgendaId, fn ($q, $id) => $q->where('meeting_agenda_id', $id))
            ->when($this->onlyMine && $authUserId, function ($q) use ($authUserId) {
                $q->whereHas('participant.attendee', fn ($q2) => $q2->where('user_id', $authUserId));
            })
            ->when($this->onlyMine && ! $authUserId, fn ($q) => $q->whereRaw('1 = 0'))
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
                // Chất vấn → answer_content (Nội dung trả lời). Thảo luận → operator_note (Ghi chú thảo luận).
                'extra' => ($this->type === 'question' ? $item->answer_content : $item->operator_note) ?? '',
                'status' => $this->statusLabel($item->status),
                // "Có" nếu có tệp đính kèm (media_id != null), "Không" nếu không.
                'has_attachment' => $item->media_id ? 'Có' : 'Không',
            ]);
    }

    public function headings(): array
    {
        // Cột "Ghi chú thảo luận" (type=discussion) hoặc "Nội dung trả lời" (type=question).
        $extra = $this->type === 'question' ? 'Nội dung trả lời' : 'Ghi chú thảo luận';

        return ['STT', 'Chương trình', 'Người đăng ký', 'Thời gian đăng ký', 'Nội dung', $extra, 'Trạng thái', 'Đính kèm'];
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
