<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingService
{
    public function __construct(private MediaService $mediaService) {}

    /**
     * Lấy cấu hình công khai (is_public = true), nhóm theo group.
     */
    public function getPublic(): array
    {
        return Cache::remember(Setting::CACHE_KEY_PUBLIC, Setting::CACHE_TTL, function () {
            return Setting::where('is_public', true)
                ->orderBy('group')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('group')
                ->map(fn ($items) => $items->map(fn ($item) => [
                    'key' => $item->key,
                    'value' => $this->resolveValue($item),
                ])->pluck('value', 'key')->all())
                ->all();
        });
    }

    /**
     * Lấy toàn bộ cấu hình, nhóm theo group.
     */
    public function getAll(): array
    {
        return Cache::remember(Setting::CACHE_KEY_ALL, Setting::CACHE_TTL, function () {
            return Setting::orderBy('group')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('group')
                ->map(fn ($items) => $items->map(fn ($item) => [
                    'key' => $item->key,
                    'value' => $this->resolveValue($item),
                ])->pluck('value', 'key')->all())
                ->all();
        });
    }

    /**
     * Lấy danh sách các kênh thông báo khả dụng (đã bật trong cấu hình).
     */
    public function getAvailableChannels(): array
    {
        $all = $this->getAll();
        
        $flatSettings = [];
        foreach ($all as $group => $items) {
            foreach ($items as $k => $v) {
                $flatSettings[$k] = $v;
            }
        }
        
        $channels = [];
        
        if (!empty($flatSettings['email_enabled'])) {
            $channels[] = ['value' => 'mail', 'title' => 'Email', 'label' => 'Email'];
        }
        if (!empty($flatSettings['fcm_enabled'])) {
            $channels[] = ['value' => 'fcm', 'title' => 'Thông báo đẩy (App)', 'label' => 'Thông báo đẩy (App)'];
        }
        if (!empty($flatSettings['sms_enabled'])) {
            $channels[] = ['value' => 'sms', 'title' => 'SMS', 'label' => 'SMS'];
        }
        if (!empty($flatSettings['zalo_enabled'])) {
            $channels[] = ['value' => 'zalo', 'title' => 'Zalo OA', 'label' => 'Zalo OA'];
        }
        if (!empty($flatSettings['zns_enabled'])) {
            $channels[] = ['value' => 'zalo_zns', 'title' => 'Zalo ZNS', 'label' => 'Zalo ZNS'];
        }
        
        return $channels;
    }

    /**
     * Lấy giá trị một key. Nếu private và không có quyền thì trả null.
     */
    public function getByKey(string $key): ?array
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return null;
        }

        return [
            'key' => $setting->key,
            'value' => $this->resolveValue($setting),
            'group' => $setting->group,
            'label' => $setting->label,
            'type' => $setting->type,
        ];
    }

    /**
     * Cập nhật nhiều key. Chỉ cập nhật các key tồn tại trong DB.
     */
    public function update(array $data): array
    {
        $validKeys = Setting::pluck('id', 'key')->all();

        DB::transaction(function () use ($data, $validKeys) {
            foreach ($data as $key => $value) {
                if (! isset($validKeys[$key])) {
                    continue;
                }

                $setting = Setting::find($validKeys[$key]);
                if (! $setting) {
                    continue;
                }

                if ($setting->type === 'image') {
                    $this->handleImageUpload($setting, $value);
                } else {
                    $setting->value = $this->stringifyValue($value, $setting->type);
                    $setting->save();
                }
            }
        });

        Setting::clearCache();

        return $this->getAll();
    }

    /**
     * Resolve giá trị setting — nếu type=image thì trả URL từ Spatie media.
     */
    protected function resolveValue(Setting $setting): mixed
    {
        if ($setting->type === 'image') {
            $media = $setting->getFirstMedia('settings');

            return $media ? '/storage/'.$media->id.'/'.$media->file_name : null;
        }

        return Setting::castValue($setting->value, $setting->type);
    }

    /**
     * Upload ảnh cho setting qua MediaService, xóa ảnh cũ nếu có.
     */
    protected function handleImageUpload(Setting $setting, mixed $value): void
    {
        // Nếu value = null hoặc rỗng → xóa ảnh
        if (! $value || (is_string($value) && $value === '')) {
            $setting->clearMediaCollection('settings');
            $setting->value = null;
            $setting->save();

            return;
        }

        // Nếu value là file upload
        if ($value instanceof UploadedFile) {
            // Xóa ảnh cũ
            $setting->clearMediaCollection('settings');

            // Upload mới qua MediaService
            $media = $this->mediaService->uploadOne($setting, $value, 'settings', ['disk' => 'public']);
            $setting->value = '/storage/'.$media->id.'/'.$media->file_name;
            $setting->save();

            return;
        }

        // Nếu value là string (giữ nguyên URL cũ, không thay đổi)
    }

    /**
     * Chuyển value sang string để lưu DB.
     */
    protected function stringifyValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($type === 'json') {
            return is_string($value) ? $value : json_encode($value);
        }

        if ($type === 'boolean') {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
