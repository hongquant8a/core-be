<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingAttendee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateMeetingAttendeeProhibitedTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'upd-attendee-org'], ['name' => 'Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);
    }

    public function test_update_rejects_user_id_with_422(): void
    {
        $userA = User::factory()->create(['status' => 'active']);
        $userB = User::factory()->create(['status' => 'active']);

        $attendee = MeetingAttendee::create([
            'organization_id' => $this->org->id,
            'user_id' => $userA->id,
            'status' => 'active',
        ]);

        $res = $this->putJson("/api/meeting-attendees/{$attendee->id}", [
            'user_id' => $userB->id,
            'note' => 'Đổi user',
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['user_id']);
        $this->assertSame($userA->id, $attendee->fresh()->user_id);
    }

    public function test_update_allows_other_fields_without_user_id(): void
    {
        $userA = User::factory()->create(['status' => 'active']);

        $attendee = MeetingAttendee::create([
            'organization_id' => $this->org->id,
            'user_id' => $userA->id,
            'status' => 'active',
            'note' => 'Cũ',
        ]);

        $res = $this->putJson("/api/meeting-attendees/{$attendee->id}", [
            'note' => 'Mới',
            'position_name' => 'Trưởng ban',
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $fresh = $attendee->fresh();
        $this->assertSame('Mới', $fresh->note);
        $this->assertSame('Trưởng ban', $fresh->position_name);
        $this->assertSame($userA->id, $fresh->user_id);
    }
}
