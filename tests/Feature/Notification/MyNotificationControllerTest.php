<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyNotificationControllerTest extends TestCase
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

    public function test_index_returns_only_current_user_notifications(): void
    {
        $u1 = $this->createUser();
        $u2 = $this->createUser();
        Notification::factory()->count(2)->create(['user_id' => $u1->id]);
        Notification::factory()->count(5)->create(['user_id' => $u2->id]);
        Sanctum::actingAs($u1);

        $res = $this->getJson('/api/notifications/me');

        $res->assertOk();
        $this->assertSame(2, $res->json('data.total'));
    }

    public function test_unread_count(): void
    {
        $user = $this->createUser();
        Notification::factory()->count(3)->unread()->create(['user_id' => $user->id]);
        Notification::factory()->count(2)->read()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/notifications/me/unread-count');

        $res->assertOk();
        $this->assertSame(3, $res->json('data.unread_count'));
    }

    public function test_mark_as_read(): void
    {
        $user = $this->createUser();
        $n = Notification::factory()->unread()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $res = $this->patchJson("/api/notifications/me/{$n->id}/read");

        $res->assertOk();
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_all_read(): void
    {
        $user = $this->createUser();
        Notification::factory()->count(3)->unread()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $res = $this->patchJson('/api/notifications/me/read-all');

        $res->assertOk();
        $this->assertSame(3, $res->json('data.updated'));
        $this->assertSame(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_destroy_own_notification(): void
    {
        $user = $this->createUser();
        $n = Notification::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $res = $this->deleteJson("/api/notifications/me/{$n->id}");

        $res->assertOk();
        $this->assertNull(Notification::find($n->id));
    }

    public function test_cannot_read_others_notification(): void
    {
        $u1 = $this->createUser();
        $u2 = $this->createUser();
        $n = Notification::factory()->unread()->create(['user_id' => $u2->id]);
        Sanctum::actingAs($u1);

        $res = $this->patchJson("/api/notifications/me/{$n->id}/read");

        $res->assertNotFound();
    }
}
