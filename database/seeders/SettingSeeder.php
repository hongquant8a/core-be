<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seed cấu hình mặc định vào bảng settings.
 */
class SettingSeeder extends Seeder
{
    protected static array $items = [
        // General
        ['key' => 'copyright', 'value' => '', 'group' => 'general', 'is_public' => true, 'type' => 'string', 'label' => 'Thông tin bản quyền', 'sort_order' => 1],
        ['key' => 'designed_by', 'value' => '', 'group' => 'general', 'is_public' => true, 'type' => 'string', 'label' => 'Thiết kế bởi', 'sort_order' => 2],
        ['key' => 'language', 'value' => 'vi', 'group' => 'general', 'is_public' => true, 'type' => 'string', 'label' => 'Ngôn ngữ', 'sort_order' => 3],
        ['key' => 'time_format', 'value' => 'H:i:s d/m/Y', 'group' => 'general', 'is_public' => true, 'type' => 'string', 'label' => 'Định dạng thời gian', 'sort_order' => 4],
        ['key' => 'icon', 'value' => null, 'group' => 'general', 'is_public' => true, 'type' => 'image', 'label' => 'Biểu tượng favicon', 'sort_order' => 5],
        ['key' => 'logo', 'value' => null, 'group' => 'general', 'is_public' => true, 'type' => 'image', 'label' => 'Logo trang', 'sort_order' => 6],
        ['key' => 'contact_email', 'value' => null, 'group' => 'general', 'is_public' => false, 'type' => 'string', 'label' => 'Email tiếp nhận yêu cầu liên hệ', 'sort_order' => 7],
        ['key' => 'organization_name', 'value' => '', 'group' => 'general', 'is_public' => true, 'type' => 'string', 'label' => 'Tên đơn vị', 'sort_order' => 8],
        ['key' => 'app_name', 'value' => 'QuânDH Core', 'group' => 'general', 'is_public' => true, 'type' => 'string', 'label' => 'Tên ứng dụng', 'sort_order' => 9],
        ['key' => 'app_description', 'value' => '', 'group' => 'general', 'is_public' => true, 'type' => 'text', 'label' => 'Mô tả ứng dụng', 'sort_order' => 10],
        ['key' => 'app_title', 'value' => 'Tiêu đề trang', 'group' => 'general', 'is_public' => true, 'type' => 'string', 'label' => 'Tiêu đề trang', 'sort_order' => 11],
        // Admin page — admin_app_name/admin_app_description/admin_welcome_title đã chuyển sang general.app_name/app_description.
        // seed:cleanup-obsolete sẽ tự xóa 3 key cũ trong DB.
        ['key' => 'admin_logo_title', 'value' => 'Hệ thống quản trị', 'group' => 'admin_page', 'is_public' => true, 'type' => 'string', 'label' => 'Tiêu đề trang đăng nhập(cạnh logo Trang quản trị)', 'sort_order' => 1],
        ['key' => 'admin_background_image', 'value' => null, 'group' => 'admin_page', 'is_public' => true, 'type' => 'image', 'label' => 'Ảnh nền', 'sort_order' => 2],
        // Org select page — bỏ org_select_description (đợi cleanup).
        ['key' => 'org_select_title', 'value' => 'Chọn tổ chức', 'group' => 'org_select_page', 'is_public' => true, 'type' => 'string', 'label' => 'Tiêu đề trang chọn tổ chức', 'sort_order' => 1],
        ['key' => 'org_select_background_image', 'value' => null, 'group' => 'org_select_page', 'is_public' => true, 'type' => 'image', 'label' => 'Ảnh nền', 'sort_order' => 2],
        // Social
        ['key' => 'social_facebook', 'value' => null, 'group' => 'social', 'is_public' => true, 'type' => 'string', 'label' => 'Facebook', 'sort_order' => 1],
        ['key' => 'social_twitter', 'value' => null, 'group' => 'social', 'is_public' => true, 'type' => 'string', 'label' => 'Twitter', 'sort_order' => 2],
        ['key' => 'social_youtube', 'value' => null, 'group' => 'social', 'is_public' => true, 'type' => 'string', 'label' => 'YouTube', 'sort_order' => 3],
        ['key' => 'social_tiktok', 'value' => null, 'group' => 'social', 'is_public' => true, 'type' => 'string', 'label' => 'TikTok', 'sort_order' => 4],
        ['key' => 'social_gmail', 'value' => null, 'group' => 'social', 'is_public' => true, 'type' => 'string', 'label' => 'Gmail', 'sort_order' => 5],
        ['key' => 'social_email', 'value' => null, 'group' => 'social', 'is_public' => true, 'type' => 'string', 'label' => 'Email', 'sort_order' => 6],
        // API
        ['key' => 'api_gemini_url', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'Gemini API URL', 'sort_order' => 1],
        ['key' => 'api_gemini_token', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'Gemini Token', 'sort_order' => 2],
        ['key' => 'api_deepseek_url', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'DeepSeek API URL', 'sort_order' => 3],
        ['key' => 'api_deepseek_token', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'DeepSeek Token', 'sort_order' => 4],
        ['key' => 'api_chatgpt_url', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'ChatGPT API URL', 'sort_order' => 5],
        ['key' => 'api_chatgpt_token', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'ChatGPT Token', 'sort_order' => 6],
        ['key' => 'api_google_maps_url', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'Google Maps API URL', 'sort_order' => 9],
        ['key' => 'api_google_maps_token', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'Google Maps Token', 'sort_order' => 11],
        // Notification - Firebase FCM (BE + FE Web SDK)
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
        // Email
        ['key' => 'email_enabled', 'value' => '0', 'group' => 'email', 'is_public' => false, 'type' => 'boolean', 'label' => 'Bật Email', 'sort_order' => 0],
        ['key' => 'email_protocol', 'value' => 'smtp', 'group' => 'email', 'is_public' => false, 'type' => 'string', 'label' => 'Giao thức', 'sort_order' => 1],
        ['key' => 'email_sender_name', 'value' => '', 'group' => 'email', 'is_public' => false, 'type' => 'string', 'label' => 'Tên người gửi', 'sort_order' => 2],
        ['key' => 'email_sender_address', 'value' => null, 'group' => 'email', 'is_public' => false, 'type' => 'string', 'label' => 'Địa chỉ email gửi', 'sort_order' => 3],
        ['key' => 'email_smtp_host', 'value' => null, 'group' => 'email', 'is_public' => false, 'type' => 'string', 'label' => 'Máy chủ SMTP', 'sort_order' => 4],
        ['key' => 'email_smtp_username', 'value' => null, 'group' => 'email', 'is_public' => false, 'type' => 'string', 'label' => 'Tài khoản SMTP', 'sort_order' => 5],
        ['key' => 'email_smtp_password', 'value' => null, 'group' => 'email', 'is_public' => false, 'type' => 'string', 'label' => 'Mật khẩu SMTP', 'sort_order' => 6],
        ['key' => 'email_smtp_port', 'value' => '587', 'group' => 'email', 'is_public' => false, 'type' => 'string', 'label' => 'Cổng SMTP', 'sort_order' => 7],
        ['key' => 'email_smtp_encryption', 'value' => 'tls', 'group' => 'email', 'is_public' => false, 'type' => 'string', 'label' => 'Loại bảo mật', 'sort_order' => 8],
        ['key' => 'email_test_address', 'value' => null, 'group' => 'email', 'is_public' => false, 'type' => 'string', 'label' => 'Email kiểm thử', 'sort_order' => 9],
        // SMS
        ['key' => 'sms_enabled', 'value' => '0', 'group' => 'sms', 'is_public' => false, 'type' => 'boolean', 'label' => 'Bật SMS', 'sort_order' => 0],
        ['key' => 'sms_server', 'value' => null, 'group' => 'sms', 'is_public' => false, 'type' => 'string', 'label' => 'Máy chủ SMS', 'sort_order' => 1],
        ['key' => 'sms_username', 'value' => null, 'group' => 'sms', 'is_public' => false, 'type' => 'string', 'label' => 'Tên đăng nhập', 'sort_order' => 2],
        ['key' => 'sms_password', 'value' => null, 'group' => 'sms', 'is_public' => false, 'type' => 'string', 'label' => 'Mật khẩu', 'sort_order' => 3],
        ['key' => 'sms_test_phone', 'value' => null, 'group' => 'sms', 'is_public' => false, 'type' => 'string', 'label' => 'Số điện thoại kiểm thử', 'sort_order' => 4],
        // Zalo OA Message — free-form text qua Official Account API v2.0 (channel key: 'zalo')
        ['key' => 'zalo_enabled', 'value' => '0', 'group' => 'zalo', 'is_public' => false, 'type' => 'boolean', 'label' => 'Bật Zalo OA', 'sort_order' => 0],
        ['key' => 'zalo_app_id', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'string', 'label' => 'App ID', 'sort_order' => 1],
        ['key' => 'zalo_app_secret', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'string', 'label' => 'Secret Key', 'sort_order' => 2],
        ['key' => 'zalo_access_token', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'string', 'label' => 'Access Token', 'sort_order' => 3],
        ['key' => 'zalo_refresh_token', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'string', 'label' => 'Refresh Token', 'sort_order' => 4],
        // Zalo ZNS — template-based qua WorldSMS relay (South Telecom) (channel key: 'zalo_zns')
        // Endpoint: POST https://api-04.worldsms.vn/apidebit/sendZNS
        // Auth: Basic base64(username:password)
        ['key' => 'zns_enabled', 'value' => '0', 'group' => 'zalo_zns', 'is_public' => false, 'type' => 'boolean', 'label' => 'Bật Zalo ZNS', 'sort_order' => 0],
        ['key' => 'zns_server', 'value' => 'https://api-04.worldsms.vn/apidebit/sendZNS', 'group' => 'zalo_zns', 'is_public' => false, 'type' => 'string', 'label' => 'Endpoint ZNS', 'sort_order' => 1],
        ['key' => 'zns_username', 'value' => null, 'group' => 'zalo_zns', 'is_public' => false, 'type' => 'string', 'label' => 'Tên đăng nhập WorldSMS', 'sort_order' => 2],
        ['key' => 'zns_password', 'value' => null, 'group' => 'zalo_zns', 'is_public' => false, 'type' => 'string', 'label' => 'Mật khẩu WorldSMS', 'sort_order' => 3],
        ['key' => 'zns_sender', 'value' => null, 'group' => 'zalo_zns', 'is_public' => false, 'type' => 'string', 'label' => 'Zalo OA Sender ID (from)', 'sort_order' => 4],
        ['key' => 'zns_template_id', 'value' => null, 'group' => 'zalo_zns', 'is_public' => false, 'type' => 'string', 'label' => 'Template ID đã duyệt ZNS', 'sort_order' => 5],
        ['key' => 'zns_extra_params', 'value' => null, 'group' => 'zalo_zns', 'is_public' => false, 'type' => 'json', 'label' => 'Tham số bổ sung template_data (JSON)', 'sort_order' => 6],
        // SMS Failover (tùy chọn) — gửi SMS nếu ZNS thất bại
        ['key' => 'zns_sms_failover_sender', 'value' => null, 'group' => 'zalo_zns', 'is_public' => false, 'type' => 'string', 'label' => 'SMS Failover Brandname (tùy chọn)', 'sort_order' => 7],
        ['key' => 'zns_sms_failover_unicode', 'value' => '1', 'group' => 'zalo_zns', 'is_public' => false, 'type' => 'boolean', 'label' => 'SMS Failover unicode (có dấu)', 'sort_order' => 8],
        // Chat
        ['key' => 'chat_enabled', 'value' => '0', 'group' => 'chat', 'is_public' => false, 'type' => 'boolean', 'label' => 'Bật Chat', 'sort_order' => 0],
        ['key' => 'chat_server', 'value' => null, 'group' => 'chat', 'is_public' => false, 'type' => 'string', 'label' => 'Máy chủ Chat', 'sort_order' => 1],
        ['key' => 'chat_api_key', 'value' => null, 'group' => 'chat', 'is_public' => false, 'type' => 'string', 'label' => 'API Key', 'sort_order' => 2],
        ['key' => 'chat_sender', 'value' => null, 'group' => 'chat', 'is_public' => false, 'type' => 'string', 'label' => 'Người gửi', 'sort_order' => 3],
        ['key' => 'chat_receiver', 'value' => null, 'group' => 'chat', 'is_public' => false, 'type' => 'string', 'label' => 'Người nhận', 'sort_order' => 4],
        ['key' => 'chat_room', 'value' => null, 'group' => 'chat', 'is_public' => false, 'type' => 'string', 'label' => 'Phòng chat', 'sort_order' => 5],
        ['key' => 'chat_message', 'value' => null, 'group' => 'chat', 'is_public' => false, 'type' => 'string', 'label' => 'Tin nhắn', 'sort_order' => 6],
        ['key' => 'chat_department', 'value' => null, 'group' => 'chat', 'is_public' => false, 'type' => 'string', 'label' => 'Phòng ban', 'sort_order' => 7],
        ['key' => 'chat_email_title', 'value' => null, 'group' => 'chat', 'is_public' => false, 'type' => 'string', 'label' => 'Tiêu đề mail', 'sort_order' => 8],
        ['key' => 'chat_test_type', 'value' => null, 'group' => 'chat', 'is_public' => false, 'type' => 'string', 'label' => 'Loại kiểm tra', 'sort_order' => 9],
        // Log
        ['key' => 'log_retention_days', 'value' => '90', 'group' => 'log', 'is_public' => false, 'type' => 'integer', 'label' => 'Số ngày giữ nhật ký', 'sort_order' => 1],
        // SSO Đà Nẵng
        ['key' => 'sso_danang_enabled', 'value' => '0', 'group' => 'sso_danang', 'is_public' => true, 'type' => 'boolean', 'label' => 'Bật SSO Đà Nẵng', 'sort_order' => 1],
        ['key' => 'sso_danang_base_url', 'value' => 'https://sso.danang.gov.vn', 'group' => 'sso_danang', 'is_public' => true, 'type' => 'string', 'label' => 'Base URL', 'sort_order' => 2],
        ['key' => 'sso_danang_client_id', 'value' => null, 'group' => 'sso_danang', 'is_public' => true, 'type' => 'string', 'label' => 'Client ID', 'sort_order' => 3],
        ['key' => 'sso_danang_client_secret', 'value' => null, 'group' => 'sso_danang', 'is_public' => false, 'type' => 'string', 'label' => 'Client Secret', 'sort_order' => 4],
        ['key' => 'sso_danang_redirect_uri', 'value' => null, 'group' => 'sso_danang', 'is_public' => true, 'type' => 'string', 'label' => 'Redirect URI', 'sort_order' => 5],
        ['key' => 'sso_danang_scope', 'value' => 'openid profile email', 'group' => 'sso_danang', 'is_public' => true, 'type' => 'string', 'label' => 'Scope', 'sort_order' => 6],
        // CBCCVC
        ['key' => 'sso_cbccvc_enabled', 'value' => '0', 'group' => 'sso_cbccvc', 'is_public' => true, 'type' => 'boolean', 'label' => 'Bật CBCCVC', 'sort_order' => 1],
        ['key' => 'sso_cbccvc_base_url', 'value' => 'https://cbccvc.danang.gov.vn', 'group' => 'sso_cbccvc', 'is_public' => true, 'type' => 'string', 'label' => 'Base URL', 'sort_order' => 2],
        // Auth chung
        ['key' => 'auth_auto_create_default_role_id', 'value' => null, 'group' => 'auth', 'is_public' => false, 'type' => 'integer', 'label' => 'Role mặc định khi tạo user qua SSO', 'sort_order' => 1],
    ];

    public function run(): void
    {
        foreach (self::$items as $item) {
            $setting = Setting::firstOrNew(['key' => $item['key']]);
            
            // Chỉ gán value nếu record chưa tồn tại (chưa được tạo/người dùng chưa sửa)
            if (! $setting->exists) {
                $setting->value = $item['value'];
            }
            
            $setting->group = $item['group'];
            $setting->is_public = $item['is_public'];
            $setting->type = $item['type'];
            $setting->label = $item['label'];
            $setting->sort_order = $item['sort_order'];
            $setting->save();
        }

        Setting::clearCache();
    }
}
