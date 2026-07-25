<?php

namespace App\Http\Controllers;

use App\Modules\Core\Exports\ImportErrorsExport;
use App\Modules\Core\Traits\RespondsWithJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

abstract class Controller
{
    use RespondsWithJson;

    /**
     * Chuẩn hóa response cho import: các dòng hợp lệ vẫn được import (không abort khi 1 dòng lỗi),
     * đồng thời trả về số dòng lỗi validation đã bỏ qua kèm chi tiết từng dòng (số dòng, cột,
     * thông báo lỗi, giá trị gốc) để cán bộ sửa và nhập lại đúng các dòng đó.
     *
     * Khi có lỗi, đính kèm luôn `error_file` — 1 file Excel (base64) tổng hợp lỗi (cột: STT,
     * Hàng số, Cột, Lỗi, Giá trị) để cán bộ tải về đối chiếu, không phải tự đọc JSON.
     *
     * @param  Collection  $failures  Collection<\Maatwebsite\Excel\Validators\Failure> từ Import::failures()
     * @param  array<string, string>  $columnLabels  Map field_key => 'Nhãn tiếng Việt' (vd Import::FIELD_LABELS)
     *                                                để cột "Cột" hiển thị nhãn thay vì key kỹ thuật.
     */
    protected function importResult(Collection $failures, string $entity, array $columnLabels = []): JsonResponse
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

        $data = [
            'failed_count' => $failedCount,
            'errors' => $errors,
            // File Excel lỗi (null khi không có lỗi) — FE decode base64 để cán bộ tải về.
            'error_file' => $failedCount > 0 ? $this->buildImportErrorFile($failures, $entity, $columnLabels) : null,
        ];

        return $this->success($data, $message);
    }

    /**
     * Sinh file Excel tổng hợp lỗi import (dạng base64) — mỗi lỗi 1 dòng.
     *
     * @param  array<string, string>  $columnLabels
     * @return array{name: string, mime: string, base64: string}
     */
    private function buildImportErrorFile(Collection $failures, string $entity, array $columnLabels): array
    {
        $rows = [];
        $stt = 1;

        foreach ($failures as $failure) {
            $column = $failure->attribute();
            $value = $failure->values()[$column] ?? null;

            $rows[] = [
                $stt++,
                $failure->row(),
                $columnLabels[$column] ?? $column,
                implode('; ', $failure->errors()),
                is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) ($value ?? ''),
            ];
        }

        $binary = Excel::raw(new ImportErrorsExport($rows), ExcelWriter::XLSX);
        $slug = Str::slug($entity) ?: 'import';

        return [
            'name' => "loi-import-{$slug}.xlsx",
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'base64' => base64_encode($binary),
        ];
    }
}
