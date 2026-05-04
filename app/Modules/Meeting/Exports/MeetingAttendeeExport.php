<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Meeting\Models\MeetingAttendee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MeetingAttendeeExport implements FromCollection, WithHeadings
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return MeetingAttendee::query()
            ->with(['creator', 'editor', 'group', 'user.profile'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($item, $i) => [
                'stt' => $i + 1,
                'name' => $item->user?->name,
                'email' => $item->user?->email,
                'phone' => $item->user?->profile?->phone,
                'group_name' => $item->group?->name,
                'position_name' => $item->position_name,
                'department_name' => $item->department_name,
                'status' => $item->status,
                'note' => $item->note,
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
            'STT', 'Họ tên', 'Email', 'Số điện thoại', 'Nhóm đại biểu',
            'Chức vụ', 'Đơn vị', 'Trạng thái', 'Ghi chú',
            'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID',
        ];
    }
}
