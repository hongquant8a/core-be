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
        $updated = Permission::where('name', 'group:schedules-office')
            ->update(['description' => 'Lịch công tác - Văn phòng']);

        $this->info("Đã cập nhật {$updated} permission group.");
        return self::SUCCESS;
    }
}
