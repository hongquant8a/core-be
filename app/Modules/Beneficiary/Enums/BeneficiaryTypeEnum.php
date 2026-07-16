<?php

namespace App\Modules\Beneficiary\Enums;

/**
 * 12 nhóm người có công theo Điều 3, Pháp lệnh 02/2020/UBTVQH14.
 */
enum BeneficiaryTypeEnum: string
{
    case PreRevolution1945 = 'pre_revolution_1945';
    case Revolution1945To1945Uprising = 'revolution_1945_to_1945_uprising';
    case Martyr = 'martyr';
    case VietnameseHeroicMother = 'vietnamese_heroic_mother';
    case HeroOfArmedForces = 'hero_of_armed_forces';
    case HeroOfLabor = 'hero_of_labor';
    case WarInvalid = 'war_invalid';
    case DiseaseInvalid = 'disease_invalid';
    case AgentOrangeVictim = 'agent_orange_victim';
    case FormerPrisoner = 'former_prisoner';
    case ResistanceActivist = 'resistance_activist';
    case RevolutionSupporter = 'revolution_supporter';

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
            self::PreRevolution1945 => 'Người hoạt động cách mạng trước ngày 01/01/1945',
            self::Revolution1945To1945Uprising => 'Người hoạt động cách mạng từ 01/01/1945 đến Khởi nghĩa tháng Tám năm 1945',
            self::Martyr => 'Liệt sĩ',
            self::VietnameseHeroicMother => 'Bà mẹ Việt Nam anh hùng',
            self::HeroOfArmedForces => 'Anh hùng Lực lượng vũ trang nhân dân',
            self::HeroOfLabor => 'Anh hùng Lao động trong thời kỳ kháng chiến',
            self::WarInvalid => 'Thương binh, người hưởng chính sách như thương binh',
            self::DiseaseInvalid => 'Bệnh binh',
            self::AgentOrangeVictim => 'Người hoạt động kháng chiến bị nhiễm chất độc hóa học',
            self::FormerPrisoner => 'Người hoạt động cách mạng, kháng chiến, bảo vệ Tổ quốc, làm nghĩa vụ quốc tế bị địch bắt tù, đày',
            self::ResistanceActivist => 'Người hoạt động kháng chiến giải phóng dân tộc, bảo vệ Tổ quốc, làm nghĩa vụ quốc tế',
            self::RevolutionSupporter => 'Người có công giúp đỡ cách mạng',
        };
    }
}
