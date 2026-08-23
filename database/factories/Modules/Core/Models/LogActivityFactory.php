<?php

namespace Database\Factories\Modules\Core\Models;

use App\Modules\Core\Models\LogActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Models\LogActivity>
 */
class LogActivityFactory extends Factory
{
    protected $model = LogActivity::class;

    public function definition(): array
    {
        return [
            'description' => fake()->sentence(),
            'user_type' => 'User',
            'user_id' => null,
            'organization_id' => null,
            'route' => fake()->url(),
            'method_type' => fake()->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'status_code' => fake()->randomElement([200, 201, 204, 400, 401, 403, 404, 422, 500]),
            'ip_address' => fake()->ipv4(),
            'country' => fake()->country(),
            'user_agent' => fake()->userAgent(),
            'device_id' => fake()->uuid(),
            'request_data' => [
                'sample' => fake()->word(),
            ],
        ];
    }
}
