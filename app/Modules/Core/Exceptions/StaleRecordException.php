<?php

namespace App\Modules\Core\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Ném khi `lock_version` client gửi lên không khớp `updated_at` hiện tại của bản ghi
 * (optimistic lock — hai người cùng mở một form, người sau ghi đè mất thay đổi người trước).
 *
 * Trả đúng format lỗi chung của `RespondsWithJson` — KHÔNG tự chế
 * `response()->json(['message' => ...], 409)`, vì frontend đọc lỗi theo một khuôn duy nhất.
 */
class StaleRecordException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage()
                ?: 'Bản ghi đã được người khác cập nhật. Vui lòng tải lại trang.',
            'error_code' => 'STALE_RECORD',
        ], 409);
    }
}
