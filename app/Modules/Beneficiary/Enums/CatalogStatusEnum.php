<?php

namespace App\Modules\Beneficiary\Enums;

/**
 * Trạng thái của ba bảng danh mục (Tổ dân phố/Thôn, Loại đối tượng, Mối quan hệ).
 *
 * Bảng chính `beneficiaries` KHÔNG có trạng thái — chỉ danh mục mới có, vì mục danh mục cũ
 * phải ngừng dùng cho hồ sơ mới nhưng vẫn giữ nguyên cho các hồ sơ đang tham chiếu (mà
 * `restrictOnDelete` không cho xoá).
 *
 * Nhãn cố ý là "Đang sử dụng / Ngừng sử dụng" chứ không phải "Hoạt động / Ngừng hoạt động":
 * trạng thái này điều khiển đúng một thứ — mục còn được chọn khi nhập hồ sơ mới hay không.
 */
enum CatalogStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang sử dụng',
            self::Inactive => 'Ngừng sử dụng',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
