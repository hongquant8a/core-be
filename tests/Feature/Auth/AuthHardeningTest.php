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

    public function test_logout_clears_fcm_token_for_current_device_only(): void
    {
        $user = User::factory()->create();
        // 2 devices đăng ký cho user
        \App\Modules\Core\Models\FcmToken::create([
            'user_id' => $user->id, 'device_id' => 'device-A',
            'fcm_token' => 'token-A', 'last_used_at' => now(),
        ]);
        \App\Modules\Core\Models\FcmToken::create([
            'user_id' => $user->id, 'device_id' => 'device-B',
            'fcm_token' => 'token-B', 'last_used_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $res = $this->withHeader('X-Device-Id', 'device-A')->postJson('/api/auth/logout');

        $res->assertOk();
        $this->assertSame(0, \App\Modules\Core\Models\FcmToken::where('device_id', 'device-A')->count(),
            'device-A FCM bị xóa');
        $this->assertSame(1, \App\Modules\Core\Models\FcmToken::where('device_id', 'device-B')->count(),
            'device-B FCM giữ nguyên — vẫn nhận push');
    }

    public function test_logout_without_device_id_keeps_all_fcm_tokens(): void
    {
        $user = User::factory()->create();
        \App\Modules\Core\Models\FcmToken::create([
            'user_id' => $user->id, 'device_id' => 'device-X',
            'fcm_token' => 'token-X', 'last_used_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/auth/logout'); // no X-Device-Id

        $res->assertOk();
        $this->assertSame(1, \App\Modules\Core\Models\FcmToken::where('user_id', $user->id)->count(),
            'no device_id header → don\'t guess, keep all');
    }
}
