<?php

namespace App\Modules\Core\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Apply ORDER BY với collation `utf8mb4_vietnamese_ci` cho cột chứa tên tiếng Việt.
 *
 * MySQL `utf8mb4_unicode_ci` (default) không respect đầy đủ thứ tự alphabet tiếng Việt
 * — Ấ/Ô/Ơ/... không gom đúng group với chữ cơ sở. utf8mb4_vietnamese_ci sửa điều này.
 *
 * Caller chịu trách nhiệm whitelist `$column` (vd qua `in_array($sortBy, $allowed)`)
 * trước khi gọi — helper này không escape cột để tránh chèn trực tiếp vào ORDER BY.
 */
class VietnameseSort
{
    /** Cột thường chứa tên tiếng Việt → cần collation Vietnamese. */
    public const VIETNAMESE_TEXT_COLUMNS = [
        'name',
        'title',
        'display_name',
        'user_name',
    ];

    /**
     * Apply ORDER BY phù hợp:
     *  - Cột text Vietnamese → ORDER BY {col} COLLATE utf8mb4_vietnamese_ci
     *  - Cột khác (id, datetime, integer, ...) → orderBy() thường
     *
     * @param  Builder|QueryBuilder  $query
     */
    public static function apply($query, string $column, ?string $direction = 'asc'): void
    {
        $direction = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';

        if (in_array($column, self::VIETNAMESE_TEXT_COLUMNS, true)) {
            // Column đã được caller whitelist nên an toàn để inline.
            $query->orderByRaw(sprintf('`%s` COLLATE utf8mb4_vietnamese_ci %s', $column, $direction));

            return;
        }

        $query->orderBy($column, $direction);
    }
}
