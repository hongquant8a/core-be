<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use App\Modules\Meeting\Models\MeetingGuest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MeetingGuestExport implements FromCollection, WithHeadings
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return MeetingGuest::query()
            ->with(['creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($item, $i) => [
                $i + 1,
                $item->name,
                $item->email,
                $item->phone,
                $item->note ?? '',
                MeetingCatalogStatusEnum::tryFrom((string) $item->status)?->label() ?? (string) $item->status,
                $item->creator?->name ?? '',
                $item->editor?->name ?? '',
                $item->created_at?->format('H:i:s d/m/Y') ?? '',
                $item->updated_at?->format('H:i:s d/m/Y') ?? '',
                $item->id,
            ]);
    }

    public function headings(): array
    {
        return [
            'STT',
            'Họ tên',
            'Email',
            'Số điện thoại',
            'Ghi chú',
            'Trạng thái',
            'Người tạo',
            'Người cập nhật',
            'Ngày tạo',
            'Ngày cập nhật',
            'ID',
        ];
    }
}
