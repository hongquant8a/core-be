<?php

namespace App\Modules\Scheduling\Imports;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SchedulingEmployeeImport implements ToModel, WithHeadingRow, WithValidation
{
    private int $successCount = 0;

    public function __construct(private int $organizationId) {}

    public function model(array $row)
    {
        $email = $row['email'] ?? null;
        $userName = $row['ten_dang_nhap'] ?? null;
        
        $userId = null;
        if ($userName) {
            $userId = User::where('user_name', $userName)->value('id');
        } elseif ($email) {
            $userId = User::where('email', $email)->value('id');
        }

        // Avoid duplication within the same organization
        $existing = SchedulingEmployee::where('organization_id', $this->organizationId)
            ->where(function ($q) use ($userId, $email) {
                if ($userId) {
                    $q->where('user_id', $userId);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->exists();

        if ($existing) {
            return null;
        }

        $this->successCount++;

        return new SchedulingEmployee([
            'organization_id' => $this->organizationId,
            'user_id' => $userId,
            'name' => $row['ho_ten'] ?? 'N/A',
            'position_name' => $row['chuc_vu'] ?? null,
            'department' => $row['phong_ban'] ?? null,
            'phone' => $row['dien_thoai'] ?? null,
            'email' => $email,
            'status' => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'ho_ten' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ];
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
}
