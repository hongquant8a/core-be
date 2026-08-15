<?php

namespace App\Modules\Beneficiary\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Ném khi cán bộ xoá một mục danh mục đang được hồ sơ tham chiếu (`restrictOnDelete`).
 *
 * Thông báo cố ý nói luôn đường đi tiếp: chuyển sang "Ngừng sử dụng". Đây chính là lý do cột
 * `status` tồn tại trên bảng danh mục — không nói ra thì cán bộ gặp 409 sẽ bế tắc, tưởng
 * rằng dữ liệu bị kẹt vĩnh viễn.
 */
class CatalogInUseException extends Exception
{
    public function __construct(
        private readonly string $catalogName,
        private readonly int $usageCount,
    ) {
        parent::__construct(sprintf(
            'Không thể xoá "%s" vì đang có %d bản ghi sử dụng. '
            .'Nếu chỉ muốn ẩn khỏi danh sách chọn khi nhập hồ sơ mới, hãy chuyển sang trạng thái "Ngừng sử dụng".',
            $catalogName,
            $usageCount,
        ));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'CATALOG_IN_USE',
            'errors' => [
                'name' => $this->catalogName,
                'usage_count' => $this->usageCount,
            ],
        ], 409);
    }
}
