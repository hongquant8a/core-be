<?php

namespace App\Modules\Beneficiary\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bắn sau khi một hồ sơ người có công được lưu trọn gói qua `save-full`.
 *
 * Chỉ mang id chứ không mang model: listener chạy bất đồng bộ, model serialize lúc bắn có
 * thể đã cũ khi listener đọc tới.
 */
class BeneficiaryProfileSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $beneficiaryId,
        public readonly int $organizationId,
    ) {}
}
