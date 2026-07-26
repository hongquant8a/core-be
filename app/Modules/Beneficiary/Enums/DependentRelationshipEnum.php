<?php

namespace App\Modules\Beneficiary\Enums;

/**
 * Quan hệ của THÂN NHÂN với người có công (đọc theo chiều: thân nhân là <quan hệ> của người có công).
 *
 * `spouse` (Vợ/Chồng) đã tách thành `wife`/`husband` từ 26/07/2026 — gộp chung khiến báo cáo
 * không tách được nam/nữ và cán bộ phải mở từng hồ sơ để biết. Dữ liệu cũ đã chuyển đổi theo
 * giới tính thân nhân bằng migration.
 */
enum DependentRelationshipEnum: string
{
    case Wife = 'wife';
    case Husband = 'husband';
    case Child = 'child';
    case Grandchild = 'grandchild';
    case Father = 'father';
    case Mother = 'mother';
    case OlderBrother = 'older_brother';
    case OlderSister = 'older_sister';
    case YoungerSibling = 'younger_sibling';
    case FosterParent = 'foster_parent';
    case Guardian = 'guardian';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    public function label(): string
    {
        return match ($this) {
            self::Wife => 'Vợ',
            self::Husband => 'Chồng',
            self::Child => 'Con',
            self::Grandchild => 'Cháu',
            self::Father => 'Cha',
            self::Mother => 'Mẹ',
            self::OlderBrother => 'Anh',
            self::OlderSister => 'Chị',
            self::YoungerSibling => 'Em',
            self::FosterParent => 'Người nuôi dưỡng',
            self::Guardian => 'Người giám hộ',
        };
    }
}
