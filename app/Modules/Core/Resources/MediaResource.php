<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tệp đính kèm (spatie media-library).
 *
 * `id` chính là giá trị frontend gửi lại qua `keep_media_ids[]` khi lưu form trọn gói —
 * media nào không nằm trong danh sách đó sẽ bị xoá.
 *
 * `file_name` là tên gốc đã qua bộ sanitize của spatie nên hiển thị thẳng được, không cần
 * custom property `original_name`.
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'url' => $this->getUrl(),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'human_size' => $this->human_readable_size,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
        ];
    }
}
