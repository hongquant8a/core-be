<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Permission;
use Illuminate\Console\Command;

class FixSchedulingOfficeLabel extends Command
{
    protected $signature = 'fix:scheduling-office-label';
    protected $description = 'Đổi description permission group schedules-office từ "Lịch công tác - Lãnh đạo" thành "Lịch công tác - Văn phòng"';

    public function handle(): int
    {
        $updated = Permission::where('name', 'schedules-office')
            ->where('parent_id', null)
            ->update(['description' => 'Lịch công tác - Văn phòng']);

        if ($updated) {
            $this->info("Đã cập nhật {$updated} permission group.");
            return self::SUCCESS;
        }

        // Thử update cả các record có description cũ
        $updated = Permission::where('name', 'schedules-office')
            ->where('description', 'Lịch công tác - Lãnh đạo')
            ->update(['description' => 'Lịch công tác - Văn phòng']);

        $this->info("Đã cập nhật {$updated} permission group (theo description cũ).");
        return self::SUCCESS;
    }
}
