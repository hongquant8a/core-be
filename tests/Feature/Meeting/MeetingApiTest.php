<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->orgB = Organization::firstOrCreate(['slug' => 'test-b'], ['name' => 'Org B', 'status' => 'active']);

        setPermissionsTeamId($this->orgA->id);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        // Cấp quyền cho cả 2 org để test cross-org isolation.
        setPermissionsTeamId($this->orgB->id);
        $this->admin->assignRole('Super Admin');
        setPermissionsTeamId($this->orgA->id);

        Sanctum::actingAs($this->admin);
    }

    private function makeMeeting(int $orgId, array $overrides = []): Meeting
    {
        return Meeting::create(array_merge([
            'organization_id' => $orgId,
            'title' => 'Họp test',
            'is_public' => false,
            'start_time' => now()->addDay(),
            'status' => 'draft',
        ], $overrides));
    }

    public function test_index_requires_organization_header(): void
    {
        $res = $this->getJson('/api/meetings');

        $res->assertStatus(422);
    }

    public function test_index_returns_only_meetings_in_active_organization(): void
    {
        $mineA = $this->makeMeeting($this->orgA->id, ['title' => 'Mine A']);
        $otherB = $this->makeMeeting($this->orgB->id, ['title' => 'Other B']);

        $res = $this->getJson('/api/meetings', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $ids = collect($res->json('data.items'))->pluck('id')->all();
        $this->assertContains($mineA->id, $ids);
        $this->assertNotContains($otherB->id, $ids);
    }

    public function test_show_cross_org_meeting_returns_404(): void
    {
        $other = $this->makeMeeting($this->orgB->id);

        $res = $this->getJson('/api/meetings/'.$other->id, ['X-Organization-Id' => $this->orgA->id]);

        $res->assertStatus(404);
    }

    public function test_store_creates_meeting_in_active_organization(): void
    {
        $res = $this->postJson('/api/meetings', [
            'title' => 'Họp giao ban',
            'is_public' => false,
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'status' => 'draft',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('meetings', [
            'title' => 'Họp giao ban',
            'organization_id' => $this->orgA->id,
            'status' => 'draft',
        ]);
    }

    public function test_change_status_updates_meeting(): void
    {
        $meeting = $this->makeMeeting($this->orgA->id, ['status' => 'draft']);

        $res = $this->patchJson("/api/meetings/{$meeting->id}/status",
            ['status' => 'published'],
            ['X-Organization-Id' => $this->orgA->id]
        );

        $res->assertOk();
        $this->assertSame('published', $meeting->fresh()->status);
    }

    public function test_public_index_returns_only_published_public_meetings(): void
    {
        // Public endpoint không cần auth nhưng vẫn dùng cùng app instance — clear acting user.
        app('auth')->forgetGuards();

        $public = $this->makeMeeting($this->orgA->id, ['is_public' => true, 'status' => 'published', 'title' => 'Public OK']);
        $draft = $this->makeMeeting($this->orgA->id, ['is_public' => true, 'status' => 'draft', 'title' => 'Draft hidden']);
        $private = $this->makeMeeting($this->orgA->id, ['is_public' => false, 'status' => 'published', 'title' => 'Private hidden']);

        $res = $this->getJson('/api/public/meetings');

        $res->assertOk();
        $titles = collect($res->json('data.items'))->pluck('title')->all();
        $this->assertContains('Public OK', $titles);
        $this->assertNotContains('Draft hidden', $titles);
        $this->assertNotContains('Private hidden', $titles);
    }

    public function test_public_show_increments_view_count_and_blocks_non_public(): void
    {
        app('auth')->forgetGuards();

        $public = $this->makeMeeting($this->orgA->id, ['is_public' => true, 'status' => 'published', 'view_count' => 0]);
        $private = $this->makeMeeting($this->orgA->id, ['is_public' => false, 'status' => 'published']);

        $this->getJson('/api/public/meetings/'.$public->id)->assertOk();
        $this->assertSame(1, (int) $public->fresh()->view_count);

        $this->getJson('/api/public/meetings/'.$private->id)->assertStatus(404);
    }
}
