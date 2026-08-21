<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Model Setting – cấu hình hệ thống dạng key-value.
 */
class Setting extends Model implements HasMedia
{
    use InteractsWithMedia;
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'group',
        'is_public',
        'type',
        'label',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** TTL cache (giây). */
    public const CACHE_TTL = 3600;

    public const CACHE_KEY_PUBLIC = 'settings.public';

    public const CACHE_KEY_ALL = 'settings.all';

    // ─── Setting groups ────────────────────────────────────────────────────────
    public const GROUP_GENERAL      = 'general';
    public const GROUP_EMAIL        = 'email';
    public const GROUP_SMS          = 'sms';
    public const GROUP_NOTIFICATION = 'notification';
    public const GROUP_ZALO         = 'zalo';      // Zalo OA (free-text)
    public const GROUP_ZALO_MINI_APP = 'zalo_mini_app'; // Zalo Mini App (đăng nhập)
    public const GROUP_ZALO_ZNS     = 'zalo_zns';  // Zalo ZNS via WorldSMS
    public const GROUP_CHAT         = 'chat';
    public const GROUP_LOG          = 'log';
    public const GROUP_SSO_DANANG   = 'sso_danang';
    public const GROUP_SSO_CBCCVC   = 'sso_cbccvc';
    public const GROUP_AUTH         = 'auth';
    public const GROUP_SECURITY     = 'security';
    public const GROUP_API          = 'api';

    // ─── Zalo OA keys (group: zalo) ───────────────────────────────────────────
    public const KEY_ZALO_ENABLED       = 'zalo_enabled';
    public const KEY_ZALO_APP_ID        = 'zalo_app_id';
    public const KEY_ZALO_APP_SECRET    = 'zalo_app_secret';
    public const KEY_ZALO_ACCESS_TOKEN  = 'zalo_access_token';
    public const KEY_ZALO_REFRESH_TOKEN = 'zalo_refresh_token';

    // ─── Zalo Mini App keys (group: zalo_mini_app) ───────────────────────────
    // Tách bạch khỏi Zalo OA: Mini App là app riêng trên Zalo Platform, có App ID
    // và Secret riêng. appsecret_proof khi gọi graph.zalo.me/v2.0/me phải ký bằng
    // secret của chính Mini App, ký bằng secret OA sẽ bị Zalo từ chối.
    public const KEY_ZALO_MINI_APP_ID     = 'zalo_mini_app_id';
    public const KEY_ZALO_MINI_APP_SECRET = 'zalo_mini_app_secret';

    // ─── Zalo ZNS keys (group: zalo_zns) ─────────────────────────────────────
    public const KEY_ZNS_ENABLED              = 'zns_enabled';
    public const KEY_ZNS_SERVER               = 'zns_server';
    public const KEY_ZNS_USERNAME             = 'zns_username';
    public const KEY_ZNS_PASSWORD             = 'zns_password';
    public const KEY_ZNS_SENDER               = 'zns_sender';
    public const KEY_ZNS_TEMPLATE_ID          = 'zns_template_id';
    public const KEY_ZNS_EXTRA_PARAMS         = 'zns_extra_params';
    public const KEY_ZNS_SMS_FAILOVER_SENDER  = 'zns_sms_failover_sender';
    public const KEY_ZNS_SMS_FAILOVER_UNICODE = 'zns_sms_failover_unicode';

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function booted()
    {
        static::creating(function (Setting $setting) {
            $setting->created_by = $setting->updated_by = auth()->id();
        });

        static::updating(function (Setting $setting) {
            $setting->updated_by = auth()->id();
        });

        static::saved(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }

    /** Xóa cache cấu hình. */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PUBLIC);
        Cache::forget(self::CACHE_KEY_ALL);
    }

    /**
     * Lấy giá trị cấu hình theo key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return self::castValue($setting->value, $setting->type) ?? $default;
    }

    /**
     * Ép kiểu value theo type.
     */
    public static function castValue(?string $value, string $type = 'string'): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'integer'  => (int) $value,
            'boolean'  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json'     => json_decode($value, true),
            'image'    => $value,
            'password' => $value,
            default    => $value,
        };
    }
}
