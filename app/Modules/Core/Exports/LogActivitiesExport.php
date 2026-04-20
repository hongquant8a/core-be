<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Models\LogActivity;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LogActivitiesExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function collection()
    {
        $logs = LogActivity::with(['user', 'organization'])
            ->filter($this->filters)
            ->get();

        return $logs->map(fn (LogActivity $log) => [
            'id' => $log->id,
            'description' => $log->description,
            'user_name' => $log->user?->name ?? 'Guest',
            'organization_name' => $log->organization?->name ?? 'N/A',
            'route' => $log->route,
            'method_type' => $log->method_type,
            'status_code' => $log->status_code,
            'ip_address' => $log->ip_address,
            'country' => $log->country,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $log->updated_at?->format('H:i:s d/m/Y'),
        ]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Mô tả',
            'Tên người dùng',
            'Tên tổ chức',
            'Đường dẫn',
            'Phương thức',
            'Mã trạng thái',
            'Địa chỉ IP',
            'Quốc gia',
            'Trình duyệt',
            'Ngày tạo',
            'Ngày cập nhật',
        ];
    }
}
