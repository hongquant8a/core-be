<?php

namespace Tests\Feature\Notification;

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

    private function createUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_saves_fcm_token_when_header_present_and_differs(): void
    {
        $user = $this->createUser(['fcm_token' => null]);
        Sanctum::actingAs($user);

        $this->withHeaders(['X-FCM-Token' => 'new-device-token-abc'])
            ->getJson('/api/user')
            ->assertOk();

        $this->assertSame('new-device-token-abc', $user->fresh()->fcm_token);
    }

    public function test_does_not_update_when_token_matches(): void
    {
        $user = $this->createUser(['fcm_token' => 'existing-token']);
        Sanctum::actingAs($user);

        $this->withHeaders(['X-FCM-Token' => 'existing-token'])
            ->getJson('/api/user')
            ->assertOk();

        $this->assertSame('existing-token', $user->fresh()->fcm_token);
    }

    public function test_skips_when_no_header(): void
    {
        $user = $this->createUser(['fcm_token' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/user')->assertOk();

        $this->assertNull($user->fresh()->fcm_token);
    }
}
