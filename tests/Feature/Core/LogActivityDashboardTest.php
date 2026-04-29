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

    public function test_dashboard_top_users_includes_admin_other_and_anonymous_guest(): void
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
        // Anonymous → grouped into 1 "Khách" row
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

        // Anonymous gathered as a single "Khách" row
        $guest = $topUsers->firstWhere('user_id', null);
        $this->assertNotNull($guest);
        $this->assertSame('Khách', $guest['name']);
        $this->assertSame(2, $guest['total']);

        // Sum of top_users matches stats.total — invariant (no filter loses rows)
        $this->assertSame(10, $res->json('data.stats.total'));
        $this->assertSame(10, $topUsers->sum('total'));
    }

    public function test_dashboard_top_organizations_includes_unknown_row_for_null_org(): void
    {
        LogActivity::factory()->count(4)->create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
        ]);
        LogActivity::factory()->count(3)->create([
            'user_id' => $this->admin->id,
            'organization_id' => null,
        ]);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/log-activities/stats/dashboard?top_organizations_limit=10');

        $res->assertOk();
        $topOrgs = collect($res->json('data.top_organizations'));
        $this->assertSame(4, $topOrgs->firstWhere('organization_id', $this->org->id)['total']);

        $unknown = $topOrgs->firstWhere('organization_id', null);
        $this->assertNotNull($unknown);
        $this->assertSame('Không xác định', $unknown['name']);
        $this->assertSame(3, $unknown['total']);

        $this->assertSame(7, $topOrgs->sum('total'));
    }

    public function test_index_respects_user_id_filter(): void
    {
        $other = User::factory()->create();
        LogActivity::factory()->count(3)->create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
        ]);
        LogActivity::factory()->count(2)->create([
            'user_id' => $other->id,
            'organization_id' => $this->org->id,
        ]);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/log-activities?user_id='.$other->id);

        $res->assertOk();
        $items = collect($res->json('data'));
        $this->assertCount(2, $items);
        $this->assertTrue($items->every(fn ($r) => $r['user_id'] === $other->id));
    }

    public function test_timeline_respects_date_range_and_user_id(): void
    {
        // 5 logs for admin in 2026-04, 3 for admin in 2026-07, 2 for other in 2026-04
        $other = User::factory()->create();
        LogActivity::factory()->count(5)->create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
            'created_at' => '2026-04-15 10:00:00',
        ]);
        LogActivity::factory()->count(3)->create([
            'user_id' => $this->admin->id,
            'organization_id' => $this->org->id,
            'created_at' => '2026-07-20 10:00:00',
        ]);
        LogActivity::factory()->count(2)->create([
            'user_id' => $other->id,
            'organization_id' => $this->org->id,
            'created_at' => '2026-04-15 10:00:00',
        ]);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/log-activities/stats/timeline'
                .'?from_date=2026-01-01&to_date=2026-12-31'
                .'&granularity=month'
                ."&user_id={$this->admin->id}");

        $res->assertOk();
        $data = $res->json('data.data');
        $this->assertCount(12, $data, '12 buckets T1..T12');

        $april = collect($data)->firstWhere('period', '2026-04');
        $july = collect($data)->firstWhere('period', '2026-07');
        $may = collect($data)->firstWhere('period', '2026-05');

        $this->assertSame(5, $april['total'], 'admin có 5 log tháng 4');
        $this->assertSame(3, $july['total'], 'admin có 3 log tháng 7');
        $this->assertSame(0, $may['total'], 'tháng không có activity → 0 (padded)');
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
