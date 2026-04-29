<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\FcmToken;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncFcmTokenMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $defaultOrg = Organization::where('slug', 'default')->first();
        if ($defaultOrg) {
            $this->withHeader('X-Organization-Id', (string) $defaultOrg->id);
            setPermissionsTeamId($defaultOrg->id);
        }
    }

    private function createUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_upserts_fcm_token_row_when_headers_present(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->withHeaders([
            'X-FCM-Token' => 'new-device-token-abc',
            'X-Device-Id' => 'browser-uuid-1',
            'X-Device-Type' => 'web',
        ])->getJson('/api/user')->assertOk();

        $row = FcmToken::where('user_id', $user->id)->where('device_id', 'browser-uuid-1')->first();
        $this->assertNotNull($row);
        $this->assertSame('new-device-token-abc', $row->fcm_token);
        $this->assertSame('web', $row->device_type);
        $this->assertNotNull($row->last_used_at);
    }

    public function test_skips_when_no_device_id_header(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-FCM-Token' => 'orphan-token'])->getJson('/api/user')->assertOk();

        $this->assertSame(0, FcmToken::where('user_id', $user->id)->count(), 'không có device_id → skip');
    }

    public function test_skips_when_no_fcm_token_header(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-Device-Id' => 'browser-1'])->getJson('/api/user')->assertOk();

        $this->assertSame(0, FcmToken::where('user_id', $user->id)->count());
    }

    public function test_updates_existing_row_for_same_device(): void
    {
        $user = $this->createUser();
        FcmToken::create([
            'user_id' => $user->id, 'device_id' => 'browser-1',
            'fcm_token' => 'old-token', 'last_used_at' => now()->subHour(),
        ]);
        Sanctum::actingAs($user);

        $this->withHeaders([
            'X-FCM-Token' => 'rotated-token',
            'X-Device-Id' => 'browser-1',
        ])->getJson('/api/user')->assertOk();

        $this->assertSame(1, FcmToken::where('user_id', $user->id)->count(), 'vẫn 1 row');
        $this->assertSame('rotated-token', FcmToken::first()->fcm_token);
    }

    public function test_steals_token_from_other_user_if_same_fcm_token(): void
    {
        // Phone bán cho người khác: cùng device, cùng FCM token, user khác
        $oldOwner = $this->createUser();
        FcmToken::create([
            'user_id' => $oldOwner->id, 'device_id' => 'phone-uuid',
            'fcm_token' => 'shared-token', 'last_used_at' => now(),
        ]);

        $newOwner = $this->createUser();
        Sanctum::actingAs($newOwner);

        $this->withHeaders([
            'X-FCM-Token' => 'shared-token',
            'X-Device-Id' => 'phone-uuid',
        ])->getJson('/api/user')->assertOk();

        // Old owner mất row, new owner có row
        $this->assertSame(0, FcmToken::where('user_id', $oldOwner->id)->count());
        $this->assertSame(1, FcmToken::where('user_id', $newOwner->id)->count());
    }
}
