<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Cấu hình cuộc họp — 1 row / 1 organization. 3 trường ảnh quản qua Spatie media.
 *  - projector_image: ảnh nền màn trình chiếu (Tab 8)
 *  - chairperson_signature: ảnh chữ ký chủ tọa (chèn cuối biên bản .docx)
 *  - qr_icon: ảnh icon hiển thị giữa QR code điểm danh
 *
 * Service auto find-or-create theo organization_id từ context Spatie team —
 * client không cần truyền organization_id.
 */
class MeetingSetting extends TenantModel implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    public const COLLECTION_PROJECTOR = 'meeting-setting-projector';

    public const COLLECTION_SIGNATURE = 'meeting-setting-signature';

    public const COLLECTION_QR_ICON = 'meeting-setting-qr-icon';

    protected $fillable = [
        'organization_id',
        'projector_image_media_id',
        'chairperson_signature_media_id',
        'qr_icon_media_id',
        'allow_host_management',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'allow_host_management' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (MeetingSetting $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingSetting $model) => $model->updated_by = auth()->id());
    }

    public function projectorImage()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'projector_image_media_id');
    }

    public function chairpersonSignature()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'chairperson_signature_media_id');
    }

    public function qrIcon()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'qr_icon_media_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION_PROJECTOR)->singleFile();
        $this->addMediaCollection(self::COLLECTION_SIGNATURE)->singleFile();
        $this->addMediaCollection(self::COLLECTION_QR_ICON)->singleFile();
    }
}
