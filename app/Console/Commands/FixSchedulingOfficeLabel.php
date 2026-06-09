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
        $group = Permission::where('name', 'group:schedules-office')->first();
        if (! $group) {
            $this->error('Không tìm thấy group:schedules-office.');
            return self::FAILURE;
        }

        $group->update(['description' => 'Lịch công tác - Văn phòng']);
        $this->info("Đã cập nhật group label.");

        $updated = Permission::where('parent_id', $group->id)
            ->where('description', 'like', 'Lịch công tác - Lãnh đạo%')
            ->update([
                'description' => \DB::raw("REPLACE(description, 'Lịch công tác - Lãnh đạo', 'Lịch công tác - Văn phòng')")
            ]);

        $this->info("Đã cập nhật {$updated} permission con.");
        return self::SUCCESS;
    }
}
