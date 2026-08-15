<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;

/**
 * @group Beneficiary - Enum
 *
 * @header X-Organization-Id ID tổ chức (bắt buộc do middleware set.permissions.team, dù dữ liệu không tenant-scoped). Example: 1
 */
class EnumController extends Controller
{
    /**
     * Danh sách enum của module Người có công
     *
     * Chỉ có hai enum tĩnh. Loại đối tượng và Mối quan hệ KHÔNG ở đây — chúng là danh mục
     * DB, FE gọi `/beneficiary-types` và `/beneficiary-relationships`.
     *
     * `catalog_status` chỉ áp cho ba bảng danh mục; hồ sơ người có công không có trạng thái.
     *
     * @response 200 {"success": true, "data": {"gender": [{"value": "male", "label": "Nam"}, {"value": "female", "label": "Nữ"}, {"value": "other", "label": "Khác"}], "catalog_status": [{"value": "active", "label": "Đang sử dụng"}, {"value": "inactive", "label": "Ngừng sử dụng"}]}}
     */
    public function index()
    {
        return $this->success([
            'gender' => $this->mapEnum(GenderEnum::cases()),
            'catalog_status' => $this->mapEnum(CatalogStatusEnum::cases()),
        ]);
    }

    private function mapEnum(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
