<?php

namespace App\Http\Controllers;

use App\Modules\Core\Traits\RespondsWithJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

abstract class Controller
{
    use RespondsWithJson;

    /**
     * Chuẩn hóa response cho import: các dòng hợp lệ vẫn được import (không abort khi 1 dòng lỗi),
     * đồng thời trả về số dòng lỗi validation đã bỏ qua kèm chi tiết từng dòng (số dòng, cột,
     * thông báo lỗi, giá trị gốc) để cán bộ sửa và nhập lại đúng các dòng đó.
     *
     * @param  Collection  $failures  Collection<\Maatwebsite\Excel\Validators\Failure> từ Import::failures()
     */
    protected function importResult(Collection $failures, string $entity): JsonResponse
    {
        $errors = $failures->map(fn ($failure) => [
            'row' => $failure->row(),
            'column' => $failure->attribute(),
            'errors' => $failure->errors(),
            'values' => $failure->values(),
        ])->values();

        $failedCount = $errors->count();

        $message = $failedCount === 0
            ? "Import {$entity} thành công."
            : "Import {$entity} hoàn tất — đã bỏ qua {$failedCount} dòng lỗi, vui lòng kiểm tra và nhập lại các dòng này.";

        return $this->success([
            'failed_count' => $failedCount,
            'errors' => $errors,
        ], $message);
    }
}
