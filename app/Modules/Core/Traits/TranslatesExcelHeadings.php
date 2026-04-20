<?php

namespace App\Modules\Core\Traits;

/**
 * Dịch row Excel có header tiếng Việt → key kỹ thuật để validate / map vào model.
 *
 * Yêu cầu Import class khai báo `public const FIELD_LABELS = [field_key => 'Nhãn tiếng Việt']`.
 * Header không nằm trong map sẽ pass-through nguyên xi (giữ key gốc) để các fallback
 * snake_case cũ trong code vẫn hoạt động khi user đẩy file cũ lên.
 */
trait TranslatesExcelHeadings
{
    protected function translateHeadings(array $row): array
    {
        $labelToKey = array_flip(static::FIELD_LABELS);
        $out = [];

        foreach ($row as $header => $value) {
            $key = $labelToKey[$header] ?? $header;
            $out[$key] = $value;
        }

        return $out;
    }
}
