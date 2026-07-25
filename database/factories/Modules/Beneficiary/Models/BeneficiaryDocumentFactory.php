<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\BeneficiaryDocument>
 */
class BeneficiaryDocumentFactory extends Factory
{
    protected $model = BeneficiaryDocument::class;

    public function definition(): array
    {
        return [
            'beneficiary_id' => Beneficiary::factory(),
            'name' => $this->faker->randomElement([
                'Giấy chứng nhận thương binh',
                'Quyết định công nhận',
                'CCCD',
                'Giấy khai sinh',
            ]),
            'note' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
