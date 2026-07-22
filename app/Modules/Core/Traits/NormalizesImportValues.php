<?php

namespace App\Modules\Core\Traits;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Chuẩn hóa giá trị ô Excel khi import để round-trip Export→Import hoạt động và dữ liệu người
 * dùng nhập tay đa dạng vẫn hợp lệ. Dùng chung cho các Import class của mọi module.
 */
trait NormalizesImportValues
{
    /**
     * Nhận value gốc hoặc nhãn tiếng Việt của enum, trả về value hợp lệ (hoặc nguyên gốc để rule bắt lỗi).
     *
     * @param  array<int, \BackedEnum>  $cases
     */
    protected function normalizeEnum($value, array $cases): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        foreach ($cases as $case) {
            if (strcasecmp($value, $case->value) === 0 || strcasecmp($value, $case->label()) === 0) {
                return $case->value;
            }
        }

        return $value;
    }

    /** Chuẩn hóa ngày từ Excel serial / d/m/Y / Y-m-d về chuỗi Y-m-d. */
    protected function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
        }

        $value = trim((string) $value);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $parsed = \DateTime::createFromFormat($format, $value);
            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        // Không parse được → trả nguyên gốc để rule `date` báo lỗi rõ ràng.
        return $value;
    }

    /**
     * Đổi mọi ô chuỗi rỗng / chỉ có khoảng trắng thành null.
     *
     * Ô Excel trống trả về '' (không phải null) → cột số/decimal/date nhận '' sẽ lỗi SQL
     * (vd "Incorrect decimal value: '' for column injury_rate"). Gọi ngay sau translateHeadings.
     */
    protected function nullifyBlanks(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    /** Chuẩn hóa boolean: chấp nhận 1/0, true/false, có/không, nhãn Còn sống/Đã mất. Không rõ → null. */
    protected function normalizeBoolean($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $value = mb_strtolower(trim((string) $value));

        if (in_array($value, ['1', 'true', 'có', 'x', 'còn sống', 'yes'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'không', 'đã mất', 'no'], true)) {
            return false;
        }

        return null;
    }

    /**
     * Dựng chuỗi ghi chú liệt kê đầy đủ giá trị hợp lệ của enum để gắn vào ô header file mẫu.
     * Vd: "Giá trị hợp lệ: male (Nam), female (Nữ), other (Khác)".
     *
     * @param  array<int, \BackedEnum>  $cases
     */
    protected static function enumHint(array $cases): string
    {
        $parts = array_map(fn ($case) => $case->value.' ('.$case->label().')', $cases);

        return 'Giá trị hợp lệ: '.implode(', ', $parts);
    }
}
