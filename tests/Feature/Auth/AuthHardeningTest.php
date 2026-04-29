<?php

namespace Tests\Feature\Auth;

use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_is_throttled_after_5_attempts(): void
    {
        // RateLimiter cache lives across tests — clear ip key explicitly is overkill;
        // each test runs in isolation but cache may persist. We just hit the endpoint
        // with bad creds 5 times → 6th must return 429.
        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson('/api/auth/login', [
                'email' => 'nope@example.com',
                'password' => 'wrong',
            ]);
            $this->assertContains($res->status(), [422, 401, 400], "Attempt #{$i} unexpected status {$res->status()}");
        }

        $sixth = $this->postJson('/api/auth/login', [
            'email' => 'nope@example.com',
            'password' => 'wrong',
        ]);
        $sixth->assertStatus(429);
    }

    public function test_logout_clears_fcm_token(): void
    {
        $user = User::factory()->create(['fcm_token' => 'fcm-device-A-abc123']);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/auth/logout');

        $res->assertOk();
        $this->assertNull($user->fresh()->fcm_token, 'fcm_token must be cleared on logout');
    }

    public function test_logout_when_no_fcm_token_does_not_fail(): void
    {
        $user = User::factory()->create(['fcm_token' => null]);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/auth/logout');

        $res->assertOk();
        $this->assertNull($user->fresh()->fcm_token);
    }
}
