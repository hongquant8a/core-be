<?php

namespace App\Modules\Core\Enums;

/**
 * Nhóm cấu hình hệ thống.
 */
enum SettingGroupEnum: string
{
    case General = 'general';
    case AdminPage = 'admin_page';
    case OrgSelectPage = 'org_select_page';
    case Social = 'social';
    case Api = 'api';
    case Notification = 'notification';
    case Email = 'email';
    case Sms = 'sms';
    case Zalo = 'zalo';
    case Chat = 'chat';
    case Log = 'log';
    case SsoDanang = 'sso_danang';
    case SsoCbccvc = 'sso_cbccvc';
    case Auth = 'auth';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation. */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::General => 'Thông tin chung',
            self::AdminPage => 'Trang quản trị',
            self::OrgSelectPage => 'Trang chọn tổ chức',
            self::Social => 'Mạng xã hội',
            self::Api => 'Kết nối API',
            self::Notification => 'Cấu hình thông báo',
            self::Email => 'Cấu hình Email',
            self::Sms => 'Cấu hình SMS',
            self::Zalo => 'Cấu hình Zalo',
            self::Chat => 'Chat nội bộ',
            self::Log => 'Cấu hình nhật ký',
            self::SsoDanang => 'SSO Đà Nẵng',
            self::SsoCbccvc => 'CBCCVC',
            self::Auth => 'Xác thực chung',
        };
    }
}
