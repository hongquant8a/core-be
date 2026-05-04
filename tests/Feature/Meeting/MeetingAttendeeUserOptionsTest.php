<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingAttendee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingAttendeeUserOptionsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'opt-org'], ['name' => 'Opt Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);
    }

    private function makeUserInOrg(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole('Admin');

        return $user;
    }

    public function test_returns_active_users_in_organization(): void
    {
        $u1 = $this->makeUserInOrg('Nguyễn Văn A');
        $u2 = $this->makeUserInOrg('Trần Thị B');

        $res = $this->getJson('/api/meeting-attendees/user-options', ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($u1->id, $ids);
        $this->assertContains($u2->id, $ids);
    }

    public function test_excludes_users_already_linked_as_attendee(): void
    {
        $u1 = $this->makeUserInOrg('Nguyễn Văn A');
        $u2 = $this->makeUserInOrg('Trần Thị B');
        MeetingAttendee::create([
            'organization_id' => $this->org->id,
            'user_id' => $u1->id,
            'name' => $u1->name,
            'status' => 'active',
        ]);

        $res = $this->getJson('/api/meeting-attendees/user-options', ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertNotContains($u1->id, $ids);
        $this->assertContains($u2->id, $ids);
    }

    public function test_search_filters_by_name_or_email(): void
    {
        $u1 = $this->makeUserInOrg('Nguyễn Văn A');
        $u2 = $this->makeUserInOrg('Trần Thị B');

        $res = $this->getJson('/api/meeting-attendees/user-options?search=Nguyễn', ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($u1->id, $ids);
        $this->assertNotContains($u2->id, $ids);
    }

    public function test_user_without_users_index_permission_can_still_open_dropdown(): void
    {
        // Simulate clerk role that has meeting-attendees.store but NOT users.index.
        $clerk = User::factory()->create();
        $clerk->givePermissionTo('meeting-attendees.store');
        Sanctum::actingAs($clerk);

        $this->makeUserInOrg('Nguyễn Văn A');

        $res = $this->getJson('/api/meeting-attendees/user-options', ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $this->assertGreaterThanOrEqual(1, count($res->json('data')));
    }
}
