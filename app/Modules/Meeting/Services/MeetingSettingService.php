<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Models\MeetingSetting;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Singleton cấu hình cuộc họp per organization. Service auto-resolve org từ context Spatie team —
 * không nhận organization_id từ FE.
 *
 * Mỗi field ảnh là singleton collection ở Spatie media (replace khi upload mới).
 */
class MeetingSettingService
{
    /** Map field key (FE gửi) -> [DB column, media collection]. */
    private const FIELD_MAP = [
        'projector_image' => ['projector_image_media_id', MeetingSetting::COLLECTION_PROJECTOR],
        'chairperson_signature' => ['chairperson_signature_media_id', MeetingSetting::COLLECTION_SIGNATURE],
        'qr_icon' => ['qr_icon_media_id', MeetingSetting::COLLECTION_QR_ICON],
    ];

    public function __construct(private MediaService $mediaService) {}

    public function show(): MeetingSetting
    {
        return $this->resolveForCurrentOrg()->load(['projectorImage', 'chairpersonSignature', 'qrIcon']);
    }

    /**
     * Upsert — chấp nhận multipart với 1 hoặc nhiều file field.
     * Field nào FE không gửi -> giữ nguyên giá trị cũ.
     *
     * @param  array<string, UploadedFile|null>  $files  key theo FIELD_MAP
     * @param  array<string, bool>  $removeFlags  key=field name, true=xóa file hiện tại
     */
    public function update(array $files, array $removeFlags = []): MeetingSetting
    {
        $setting = $this->resolveForCurrentOrg();

        $storedFiles = [];
        try {
            $setting = DB::transaction(function () use ($setting, $files, $removeFlags, &$storedFiles) {
                foreach (self::FIELD_MAP as $key => [$column, $collection]) {
                    $remove = (bool) ($removeFlags[$key] ?? false);
                    $file = $files[$key] ?? null;

                    // Remove file cũ nếu remove=true hoặc đang upload mới (replace).
                    if (($remove || $file) && $setting->{$column}) {
                        $this->mediaService->removeByIds($setting, [$setting->{$column}], $collection);
                        $setting->{$column} = null;
                    }

                    if ($file) {
                        $media = $this->mediaService->uploadOne($setting, $file, $collection, ['disk' => 'public']);
                        $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                        $setting->{$column} = $media->id;
                    }
                }
                $setting->save();

                return $setting;
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }

        return $setting->load(['projectorImage', 'chairpersonSignature', 'qrIcon']);
    }

    private function resolveForCurrentOrg(): MeetingSetting
    {
        $orgId = $this->resolveCurrentOrganizationId();

        return MeetingSetting::firstOrCreate(['organization_id' => $orgId]);
    }

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
