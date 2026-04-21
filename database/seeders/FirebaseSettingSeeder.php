<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seed tạm để deploy: bổ sung cấu hình Firebase FCM cho FE Web SDK.
 *
 * - Move `fcm_enabled` + `firebase_service_account` từ group `api` → `notification`.
 * - Thêm 7 field public (Web SDK config) + 1 private VAPID key (tuỳ chọn).
 *
 * Idempotent, chạy nhiều lần không phát sinh duplicate.
 *
 * Cách chạy trên prod:
 *   php artisan db:seed --class=FirebaseSettingSeeder
 */
class FirebaseSettingSeeder extends Seeder
{
    /**
     * @var array<int,array<string,mixed>>
     */
    protected static array $items = [
        ['key' => 'fcm_enabled', 'value' => '0', 'group' => 'notification', 'is_public' => false, 'type' => 'boolean', 'label' => 'Bật FCM', 'sort_order' => 1],
        ['key' => 'firebase_service_account', 'value' => null, 'group' => 'notification', 'is_public' => false, 'type' => 'json', 'label' => 'Firebase Service Account (BE Admin SDK)', 'sort_order' => 2],
        ['key' => 'firebase_private_vapid_key', 'value' => null, 'group' => 'notification', 'is_public' => false, 'type' => 'string', 'label' => 'Firebase Private VAPID Key (BE ký push, tuỳ chọn)', 'sort_order' => 3],
        ['key' => 'firebase_api_key', 'value' => null, 'group' => 'notification', 'is_public' => true, 'type' => 'string', 'label' => 'Firebase Web API Key', 'sort_order' => 4],
        ['key' => 'firebase_auth_domain', 'value' => null, 'group' => 'notification', 'is_public' => true, 'type' => 'string', 'label' => 'Firebase Auth Domain', 'sort_order' => 5],
        ['key' => 'firebase_project_id', 'value' => null, 'group' => 'notification', 'is_public' => true, 'type' => 'string', 'label' => 'Firebase Project ID', 'sort_order' => 6],
        ['key' => 'firebase_storage_bucket', 'value' => null, 'group' => 'notification', 'is_public' => true, 'type' => 'string', 'label' => 'Firebase Storage Bucket', 'sort_order' => 7],
        ['key' => 'firebase_messaging_sender_id', 'value' => null, 'group' => 'notification', 'is_public' => true, 'type' => 'string', 'label' => 'Firebase Messaging Sender ID', 'sort_order' => 8],
        ['key' => 'firebase_app_id', 'value' => null, 'group' => 'notification', 'is_public' => true, 'type' => 'string', 'label' => 'Firebase Web App ID', 'sort_order' => 9],
        ['key' => 'firebase_vapid_key', 'value' => null, 'group' => 'notification', 'is_public' => true, 'type' => 'string', 'label' => 'Firebase Public VAPID Key', 'sort_order' => 10],
    ];

    public function run(): void
    {
        foreach (self::$items as $item) {
            $existing = Setting::where('key', $item['key'])->first();

            if ($existing) {
                // Chỉ update metadata (group, is_public, type, label, sort_order), giữ nguyên value đã set trên prod.
                $existing->update([
                    'group' => $item['group'],
                    'is_public' => $item['is_public'],
                    'type' => $item['type'],
                    'label' => $item['label'],
                    'sort_order' => $item['sort_order'],
                ]);
            } else {
                Setting::create($item);
            }
        }

        Setting::clearCache();
    }
}
