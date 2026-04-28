<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\LogActivity;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogActivityDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        Sanctum::actingAs($this->admin);
    }

    public function test_dashboard_returns_all_four_sections(): void
    {
        // Seed 10 logs by admin in this org, GET method
        LogActivity::factory()->count(10)->create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
            'method_type' => 'GET',
        ]);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/log-activities/stats/dashboard?granularity=month');

        $res->assertOk();
        $res->assertJsonStructure([
            'data' => [
                'stats' => ['total', 'views', 'creates', 'updates', 'deletes'],
                'timeline' => ['granularity', 'data'],
                'top_users',
                'top_organizations',
            ],
        ]);
        $this->assertSame('month', $res->json('data.timeline.granularity'));
    }

    public function test_dashboard_stats_and_timeline_count_same_dataset(): void
    {
        // Without aggregation: stats and timeline would diverge by 1 (timeline runs
        // after stats logs itself). The new endpoint runs both within a single
        // controller invocation → identical totals.
        LogActivity::factory()->count(20)->create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
            'method_type' => 'GET',
        ]);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/log-activities/stats/dashboard');

        $res->assertOk();
        $statsTotal = $res->json('data.stats.total');
        $timelineTotal = collect($res->json('data.timeline.data'))->sum('total');
        $this->assertSame($statsTotal, $timelineTotal,
            'stats.total must equal sum of timeline buckets — both query the same snapshot');
    }

    public function test_dashboard_top_users_subset_matches_admin_count(): void
    {
        $other = User::factory()->create();
        LogActivity::factory()->count(5)->create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
        ]);
        LogActivity::factory()->count(3)->create([
            'user_id' => $other->id,
            'organization_id' => $this->org->id,
        ]);
        // Anonymous (excluded from top_users by whereNotNull)
        LogActivity::factory()->count(2)->create([
            'user_id' => null,
            'organization_id' => $this->org->id,
        ]);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/log-activities/stats/dashboard?top_users_limit=10');

        $res->assertOk();
        $topUsers = collect($res->json('data.top_users'));
        $this->assertSame(5, $topUsers->firstWhere('user_id', $this->admin->id)['total']);
        $this->assertSame(3, $topUsers->firstWhere('user_id', $other->id)['total']);
        // Anonymous excluded
        $this->assertNull($topUsers->firstWhere('user_id', null));

        // Total includes all 10 (5 + 3 + 2 anonymous)
        $this->assertSame(10, $res->json('data.stats.total'));
    }

    public function test_dashboard_respects_filter_params(): void
    {
        // 7 GETs and 3 POSTs
        LogActivity::factory()->count(7)->create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
            'method_type' => 'GET',
        ]);
        LogActivity::factory()->count(3)->create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
            'method_type' => 'POST',
        ]);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/log-activities/stats/dashboard?method_type=GET');

        $res->assertOk();
        $this->assertSame(7, $res->json('data.stats.total'));
        $this->assertSame(7, $res->json('data.stats.views'));
        $this->assertSame(7, collect($res->json('data.top_users'))->firstWhere('user_id', $this->admin->id)['total']);
    }
}
