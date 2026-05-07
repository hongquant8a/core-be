<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Khôi phục 6 setting key ZNS cũ (server/username/password/sender/template_id/extra_params)
     * song song với 4 key OA hiện hành. Mục đích: giữ ZaloZnsChannel làm fallback dormant —
     * khi cần swap ngược về ZNS, settings đã có sẵn không phải seed thủ công.
     *
     * Các sort_order cao hơn nhóm OA (10+) để UI render OA trước, ZNS legacy sau.
     */
    public function up(): void
    {
        $now = now();
        $items = [
            ['key' => 'zalo_server', 'label' => '[Legacy ZNS] Máy chủ Zalo', 'type' => 'string', 'sort_order' => 11],
            ['key' => 'zalo_username', 'label' => '[Legacy ZNS] Tên đăng nhập', 'type' => 'string', 'sort_order' => 12],
            ['key' => 'zalo_password', 'label' => '[Legacy ZNS] Mật khẩu', 'type' => 'string', 'sort_order' => 13],
            ['key' => 'zalo_sender', 'label' => '[Legacy ZNS] Người gửi (OA sender ID)', 'type' => 'string', 'sort_order' => 14],
            ['key' => 'zalo_template_id', 'label' => '[Legacy ZNS] Mẫu tin nhắn ID', 'type' => 'string', 'sort_order' => 15],
            ['key' => 'zalo_extra_params', 'label' => '[Legacy ZNS] Tham số bổ sung', 'type' => 'json', 'sort_order' => 16],
        ];
        foreach ($items as $item) {
            $exists = DB::table('settings')->where('key', $item['key'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('settings')->insert([
                'key' => $item['key'],
                'value' => null,
                'group' => 'zalo',
                'is_public' => false,
                'type' => $item['type'],
                'label' => $item['label'],
                'sort_order' => $item['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'zalo_server',
            'zalo_username',
            'zalo_password',
            'zalo_sender',
            'zalo_template_id',
            'zalo_extra_params',
        ])->delete();
    }
};
