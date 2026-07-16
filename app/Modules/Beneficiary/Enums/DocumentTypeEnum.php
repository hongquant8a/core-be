<?php

namespace App\Modules\Beneficiary\Enums;

enum DocumentTypeEnum: string
{
    case Decision = 'decision';
    case IdCard = 'id_card';
    case DeathCertificate = 'death_certificate';
    case MedicalCertificate = 'medical_certificate';
    case Other = 'other';

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
            self::Decision => 'Quyết định công nhận',
            self::IdCard => 'Giấy tờ tùy thân',
            self::DeathCertificate => 'Giấy chứng tử',
            self::MedicalCertificate => 'Giấy chứng nhận y tế',
            self::Other => 'Khác',
        };
    }
}
