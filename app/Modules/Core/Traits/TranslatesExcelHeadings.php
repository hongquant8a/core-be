<?php

namespace App\Modules\Core\Traits;

/**
 * Dịch row Excel có header tiếng Việt → key kỹ thuật để validate / map vào model.
 *
 * Yêu cầu Import class khai báo `public const FIELD_LABELS = [field_key => 'Nhãn tiếng Việt']`.
 * Header không nằm trong map sẽ pass-through nguyên xi (giữ key gốc) để các fallback
 * snake_case cũ trong code vẫn hoạt động khi user đẩy file cũ lên.
 *
 * File mẫu (ImportTemplateExport) gắn dấu " *" vào header cột bắt buộc để cán bộ biết cột nào
 * phải nhập — dấu * được bỏ trước khi so khớp nên file mẫu vẫn upload lại được.
 */
trait TranslatesExcelHeadings
{
    protected function translateHeadings(array $row): array
    {
        $labelToKey = array_flip(static::FIELD_LABELS);
        $out = [];

        foreach ($row as $header => $value) {
            $headerStr = (string) $header;
            $key = $labelToKey[$headerStr]
                ?? $labelToKey[$this->stripRequiredMarker($headerStr)]
                ?? $headerStr;
            $out[$key] = $value;
        }

        return $out;
    }

    /** Bỏ dấu " *" đánh dấu cột bắt buộc do file mẫu chèn vào cuối header. */
    private function stripRequiredMarker(string $header): string
    {
        return trim((string) preg_replace('/\s*\*+\s*$/u', '', $header));
    }
}
