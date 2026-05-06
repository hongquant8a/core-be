<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Thay 6 setting key của ZNS cũ (zalo_server, zalo_username, zalo_password,
     * zalo_sender, zalo_template_id, zalo_extra_params) bằng 4 key của OA Message
     * (zalo_app_id, zalo_app_secret, zalo_access_token, zalo_refresh_token).
     */
    public function up(): void
    {
        DB::table('settings')->whereIn('key', [
            'zalo_server',
            'zalo_username',
            'zalo_password',
            'zalo_sender',
            'zalo_template_id',
            'zalo_extra_params',
        ])->delete();

        $now = now();
        $newItems = [
            ['key' => 'zalo_app_id', 'label' => 'App ID', 'sort_order' => 1],
            ['key' => 'zalo_app_secret', 'label' => 'Secret Key', 'sort_order' => 2],
            ['key' => 'zalo_access_token', 'label' => 'Access Token', 'sort_order' => 3],
            ['key' => 'zalo_refresh_token', 'label' => 'Refresh Token', 'sort_order' => 4],
        ];
        foreach ($newItems as $item) {
            $exists = DB::table('settings')->where('key', $item['key'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('settings')->insert([
                'key' => $item['key'],
                'value' => null,
                'group' => 'zalo',
                'is_public' => false,
                'type' => 'string',
                'label' => $item['label'],
                'sort_order' => $item['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Cập nhật label cho zalo_enabled (giữ giá trị cũ).
        DB::table('settings')->where('key', 'zalo_enabled')->update(['label' => 'Bật Zalo OA']);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'zalo_app_id',
            'zalo_app_secret',
            'zalo_access_token',
            'zalo_refresh_token',
        ])->delete();

        $now = now();
        $oldItems = [
            ['key' => 'zalo_server', 'label' => 'Máy chủ Zalo', 'type' => 'string', 'sort_order' => 1],
            ['key' => 'zalo_username', 'label' => 'Tên đăng nhập', 'type' => 'string', 'sort_order' => 2],
            ['key' => 'zalo_password', 'label' => 'Mật khẩu', 'type' => 'string', 'sort_order' => 3],
            ['key' => 'zalo_sender', 'label' => 'Người gửi', 'type' => 'string', 'sort_order' => 4],
            ['key' => 'zalo_template_id', 'label' => 'Mẫu tin nhắn ID', 'type' => 'string', 'sort_order' => 5],
            ['key' => 'zalo_extra_params', 'label' => 'Tham số bổ sung', 'type' => 'json', 'sort_order' => 6],
        ];
        foreach ($oldItems as $item) {
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

        DB::table('settings')->where('key', 'zalo_enabled')->update(['label' => 'Bật Zalo']);
    }
};
