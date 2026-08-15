<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class BeneficiaryDocumentFactory extends Factory
{
    protected $model = BeneficiaryDocument::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'beneficiary_id' => Beneficiary::factory(),
            'name' => $this->faker->randomElement([
                'Quyết định trợ cấp', 'Giấy chứng nhận', 'Huân chương', 'Biên bản xác minh',
            ]),
            'note' => null,
        ];
    }
}
