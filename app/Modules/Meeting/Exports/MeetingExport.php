<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Meeting\Models\Meeting;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MeetingExport implements FromCollection, WithHeadings
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return Meeting::query()
            ->with(['meetingType', 'meetingLocation', 'creator', 'editor'])
            ->filter($this->filters)
            ->get()
            ->values()
            ->map(fn ($item, $i) => [
                'stt' => $i + 1,
                'title' => $item->title,
                'meeting_type' => $item->meetingType?->name,
                'meeting_location' => $item->meetingLocation?->name,
                'is_public' => $item->is_public ? 'Có' : 'Không',
                'start_time' => $item->start_time?->format('H:i:s d/m/Y'),
                'end_time' => $item->end_time?->format('H:i:s d/m/Y'),
                'status' => $item->status,
                'view_count' => $item->view_count ?? 0,
                'published_at' => $item->published_at?->format('H:i:s d/m/Y'),
                'created_by' => $item->creator?->name ?? 'N/A',
                'updated_by' => $item->editor?->name ?? 'N/A',
                'created_at' => $item->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $item->updated_at?->format('H:i:s d/m/Y'),
                'id' => $item->id,
            ]);
    }

    public function headings(): array
    {
        return [
            'STT', 'Tiêu đề', 'Loại cuộc họp', 'Địa điểm', 'Công khai',
            'Thời gian bắt đầu', 'Thời gian kết thúc', 'Trạng thái',
            'Lượt xem', 'Thời gian phát hành',
            'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID',
        ];
    }
}
