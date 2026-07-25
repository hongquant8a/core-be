<?php

namespace Database\Factories\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Models\ResidentialArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Beneficiary\Models\ResidentialArea>
 */
class ResidentialAreaFactory extends Factory
{
    protected $model = ResidentialArea::class;

    public function definition(): array
    {
        return [
            'name' => 'Tổ '.$this->faker->numberBetween(1, 30),
            'note' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
