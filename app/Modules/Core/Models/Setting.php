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
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            'image' => $value,
            default => $value,
        };
    }
}
