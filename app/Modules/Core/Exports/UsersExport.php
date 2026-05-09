<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UsersExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function collection()
    {
        $users = User::with(['creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get();

        return $users->values()->map(fn ($user, $i) => [
            'stt' => $i + 1,
            'name' => $user->name,
            'email' => $user->email,
            'user_name' => $user->user_name,
            'status' => UserStatusEnum::tryFrom((string) $user->status)?->label() ?? $user->status,
            'created_by' => $user->creator?->name ?? 'N/A',
            'updated_by' => $user->editor?->name ?? 'N/A',
            'created_at' => $user->created_at?->format('d/m/Y H:i:s'),
            'updated_at' => $user->updated_at?->format('d/m/Y H:i:s'),
            'id' => $user->id,
        ]);
    }

    public function headings(): array
    {
        return [
            'STT',
            'Họ và tên',
            'Email',
            'Tên đăng nhập',
            'Trạng thái',
            'Người tạo',
            'Người cập nhật',
            'Ngày tạo',
            'Ngày cập nhật',
            'ID',
        ];
    }
}
