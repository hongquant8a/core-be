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
        // Admin page
        ['key' => 'admin_app_name', 'value' => 'QuânDH Core', 'group' => 'admin_page', 'is_public' => true, 'type' => 'string', 'label' => 'Tên ứng dụng', 'sort_order' => 1],
        ['key' => 'admin_logo_title', 'value' => 'Hệ thống quản trị', 'group' => 'admin_page', 'is_public' => true, 'type' => 'string', 'label' => 'Tiêu đề logo', 'sort_order' => 2],
        ['key' => 'admin_welcome_title', 'value' => 'Chào mừng đến với hệ thống', 'group' => 'admin_page', 'is_public' => true, 'type' => 'string', 'label' => 'Tiêu đề chào mừng', 'sort_order' => 3],
        ['key' => 'admin_app_description', 'value' => '', 'group' => 'admin_page', 'is_public' => true, 'type' => 'text', 'label' => 'Mô tả ứng dụng', 'sort_order' => 4],
        ['key' => 'admin_background_image', 'value' => null, 'group' => 'admin_page', 'is_public' => true, 'type' => 'image', 'label' => 'Ảnh nền', 'sort_order' => 5],
        // Org select page
        ['key' => 'org_select_title', 'value' => 'Chọn tổ chức', 'group' => 'org_select_page', 'is_public' => true, 'type' => 'string', 'label' => 'Tiêu đề trang chọn tổ chức', 'sort_order' => 1],
        ['key' => 'org_select_description', 'value' => '', 'group' => 'org_select_page', 'is_public' => true, 'type' => 'text', 'label' => 'Mô tả trang chọn tổ chức', 'sort_order' => 2],
        ['key' => 'org_select_background_image', 'value' => null, 'group' => 'org_select_page', 'is_public' => true, 'type' => 'image', 'label' => 'Ảnh nền', 'sort_order' => 3],
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
        ['key' => 'fcm_enabled', 'value' => '0', 'group' => 'api', 'is_public' => false, 'type' => 'boolean', 'label' => 'Bật FCM', 'sort_order' => 7],
        ['key' => 'firebase_service_account', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'json', 'label' => 'Firebase Service Account', 'sort_order' => 8],
        ['key' => 'api_google_maps_url', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'Google Maps API URL', 'sort_order' => 9],
        ['key' => 'api_google_maps_token', 'value' => null, 'group' => 'api', 'is_public' => false, 'type' => 'string', 'label' => 'Google Maps Token', 'sort_order' => 11],
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
        // Zalo
        ['key' => 'zalo_enabled', 'value' => '0', 'group' => 'zalo', 'is_public' => false, 'type' => 'boolean', 'label' => 'Bật Zalo', 'sort_order' => 0],
        ['key' => 'zalo_server', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'string', 'label' => 'Máy chủ Zalo', 'sort_order' => 1],
        ['key' => 'zalo_username', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'string', 'label' => 'Tên đăng nhập', 'sort_order' => 2],
        ['key' => 'zalo_password', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'string', 'label' => 'Mật khẩu', 'sort_order' => 3],
        ['key' => 'zalo_sender', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'string', 'label' => 'Người gửi', 'sort_order' => 4],
        ['key' => 'zalo_template_id', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'string', 'label' => 'Mẫu tin nhắn ID', 'sort_order' => 5],
        ['key' => 'zalo_extra_params', 'value' => null, 'group' => 'zalo', 'is_public' => false, 'type' => 'json', 'label' => 'Tham số bổ sung', 'sort_order' => 6],
        // Chat
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
    ];

    public function run(): void
    {
        foreach (self::$items as $item) {
            Setting::updateOrCreate(
                ['key' => $item['key']],
                $item
            );
        }

        Setting::clearCache();
    }
}
