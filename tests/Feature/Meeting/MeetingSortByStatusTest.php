<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingSortByStatusTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'sort-org'], ['name' => 'Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);
    }

    public function test_meetings_sort_by_status_asc(): void
    {
        Meeting::create(['organization_id' => $this->org->id, 'title' => 'M1', 'status' => 'published', 'start_time' => now()]);
        Meeting::create(['organization_id' => $this->org->id, 'title' => 'M2', 'status' => 'draft', 'start_time' => now()]);
        Meeting::create(['organization_id' => $this->org->id, 'title' => 'M3', 'status' => 'cancelled', 'start_time' => now()]);

        $res = $this->getJson('/api/meetings?sort_by=status&sort_order=asc', ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $statuses = collect($res->json('data'))->pluck('status')->all();
        $sorted = $statuses;
        sort($sorted);
        $this->assertSame($sorted, $statuses);
    }

    public function test_meeting_types_sort_by_status_desc(): void
    {
        MeetingType::create(['organization_id' => $this->org->id, 'name' => 'T1', 'status' => 'active']);
        MeetingType::create(['organization_id' => $this->org->id, 'name' => 'T2', 'status' => 'inactive']);

        $res = $this->getJson('/api/meeting-types?sort_by=status&sort_order=desc', ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $statuses = collect($res->json('data'))->pluck('status')->all();
        $sorted = $statuses;
        rsort($sorted);
        $this->assertSame($sorted, $statuses);
    }
}
