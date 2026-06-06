<?php

use App\Modules\Core\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'system_password'],
            [
                'value'     => null,
                'group'     => Setting::GROUP_SECURITY,
                'is_public' => false,
                'type'      => 'password',
                'label'     => 'Mật khẩu hệ thống',
                'sort_order' => 0,
            ]
        );
    }

    public function down(): void
    {
        Setting::where('key', 'system_password')->delete();
    }
};
