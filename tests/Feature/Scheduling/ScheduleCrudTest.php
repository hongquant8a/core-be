<?php

namespace Tests\Feature\Scheduling;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\OrgSchedulingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleCrudTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $admin;
    private User $driver;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->orgB = Organization::firstOrCreate(['slug' => 'test-b'], ['name' => 'Org B', 'status' => 'active']);

        // Create users
        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->driver = User::factory()->create(['name' => 'Driver User']);
        $this->staff = User::factory()->create(['name' => 'Staff User']);

        // Assign Roles under orgA context
        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Super Admin');
        $this->driver->assignRole('Lái xe');
        $this->staff->assignRole('Nhân viên');

        // Assign Roles under orgB context to test security scope
        setPermissionsTeamId($this->orgB->id);
        $this->admin->assignRole('Super Admin');
        $this->driver->assignRole('Lái xe');
        $this->staff->assignRole('Nhân viên');

        // Default to orgA context
        setPermissionsTeamId($this->orgA->id);
    }

    private function createSchedule(int $orgId, array $overrides = []): Schedule
    {
        return Schedule::create(array_merge([
            'organization_id' => $orgId,
            'module_type' => 'EXECUTIVE',
            'event_date' => '2026-06-01',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'session' => 'S',
            'content' => 'Họp giao ban định kỳ',
            'host_id' => $this->admin->id,
            'location' => 'Phòng họp A',
            'nature' => 'HOST',
            'status' => 0,
            'sort_order' => 1,
            'week_number' => 23,
            'year' => 2026,
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_schedules_index_requires_organization_header(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/schedules');
        $res->assertStatus(422);
    }

    public function test_schedules_index_returns_isolated_tenant_data(): void
    {
        Sanctum::actingAs($this->admin);

        $mine = $this->createSchedule($this->orgA->id, ['content' => 'Lịch Org A']);
        $other = $this->createSchedule($this->orgB->id, ['content' => 'Lịch Org B']);

        $res = $this->getJson('/api/schedules', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $contents = collect($res->json('data'))->pluck('content')->all();
        $this->assertContains('Lịch Org A', $contents);
        $this->assertNotContains('Lịch Org B', $contents);
    }

    public function test_store_creates_schedule_successfully(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/schedules', [
            'module_type' => 'EXECUTIVE',
            'event_date' => '2026-06-01',
            'start_time' => '09:30',
            'content' => 'Họp thông qua kế hoạch mới',
            'host_id' => $this->staff->id,
            'location' => 'Hội trường lớn',
            'nature' => 'HOST',
            'status' => 0,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('schedules', [
            'organization_id' => $this->orgA->id,
            'content' => 'Họp thông qua kế hoạch mới',
            'location' => 'Hội trường lớn',
            'status' => 0,
        ]);
    }

    public function test_driver_view_contains_restricted_payload_only(): void
    {
        // Set up a schedule with driver assigned
        $schedule = $this->createSchedule($this->orgA->id, [
            'driver_id' => $this->driver->id,
            'content' => 'Nội dung tuyệt mật quốc gia',
            'participants_text' => 'Bao gồm các lãnh đạo cấp cao',
        ]);

        // Access detail as driver
        Sanctum::actingAs($this->driver);
        $res = $this->getJson('/api/schedules/' . $schedule->id, ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        // Confirm driver sees time/location/host/driver/content
        $this->assertEquals($schedule->id, $data['id']);
        $this->assertEquals('Phòng họp A', $data['location']);
        $this->assertEquals($schedule->content, $data['content']);

        // Confirm driver does NOT see other sensitive fields
        $this->assertArrayNotHasKey('participants_text', $data);
        $this->assertArrayNotHasKey('attachments', $data);
    }

    public function test_admin_view_contains_full_payload(): void
    {
        $schedule = $this->createSchedule($this->orgA->id, [
            'content' => 'Nội dung chi tiết lịch công tác',
            'participants_text' => 'Tất cả nhân viên phòng TC-HC',
        ]);

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/schedules/' . $schedule->id, ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals('Nội dung chi tiết lịch công tác', $data['content']);
        $this->assertEquals('Tất cả nhân viên phòng TC-HC', $data['participants_text']);
    }

    public function test_reorder_updates_sort_orders(): void
    {
        Sanctum::actingAs($this->admin);

        $s1 = $this->createSchedule($this->orgA->id, ['sort_order' => 1]);
        $s2 = $this->createSchedule($this->orgA->id, ['sort_order' => 2]);

        $res = $this->postJson('/api/schedules/reorder', [
            'orders' => [
                ['id' => $s1->id, 'sort_order' => 10],
                ['id' => $s2->id, 'sort_order' => 20],
            ],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertEquals(10, $s1->fresh()->sort_order);
        $this->assertEquals(20, $s2->fresh()->sort_order);
    }

    public function test_duplicate_creates_multiple_records_on_new_dates(): void
    {
        Sanctum::actingAs($this->admin);

        $schedule = $this->createSchedule($this->orgA->id, [
            'event_date' => '2026-06-01',
            'content' => 'Lịch lặp lại hàng tuần',
        ]);

        $res = $this->postJson("/api/schedules/{$schedule->id}/duplicate", [
            'dates' => ['2026-06-08', '2026-06-15'],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseHas('schedules', [
            'organization_id' => $this->orgA->id,
            'event_date' => '2026-06-08',
            'content' => 'Lịch lặp lại hàng tuần',
        ]);
        $this->assertDatabaseHas('schedules', [
            'organization_id' => $this->orgA->id,
            'event_date' => '2026-06-15',
            'content' => 'Lịch lặp lại hàng tuần',
        ]);
    }

    public function test_org_scheduling_settings_crud(): void
    {
        Sanctum::actingAs($this->admin);

        // Fetch settings
        $res = $this->getJson('/api/scheduling-settings', ['X-Organization-Id' => $this->orgA->id]);
        $res->assertOk();

        // Update settings
        $resUpdate = $this->postJson('/api/scheduling-settings', [
            'executive_approval_required' => true,
            'office_approval_required' => false,
            'executive_approver_roles' => ['Lãnh đạo', 'Tổng hợp lịch'],
            'office_approver_roles' => ['Thư ký'],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $resUpdate->assertOk();
        $this->assertDatabaseHas('org_scheduling_settings', [
            'organization_id' => $this->orgA->id,
            'executive_approval_required' => true,
            'office_approval_required' => false,
        ]);
    }

    public function test_filter_presets_crud(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Create a filter preset
        $resStore = $this->postJson('/api/scheduling-filter-presets/filter-presets', [
            'name' => 'Bộ lọc tuần này',
            'filters' => ['view' => 'personal', 'status' => 2],
            'is_default' => true,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $resStore->assertStatus(201);
        $presetId = $resStore->json('data.id');

        $this->assertDatabaseHas('filter_presets', [
            'id' => $presetId,
            'user_id' => $this->admin->id,
            'name' => 'Bộ lọc tuần này',
            'is_default' => true,
        ]);

        // 2. Fetch the presets list
        $resIndex = $this->getJson('/api/scheduling-filter-presets/filter-presets', ['X-Organization-Id' => $this->orgA->id]);
        $resIndex->assertOk();
        $resIndex->assertJsonFragment(['name' => 'Bộ lọc tuần này']);

        // 3. Update the preset
        $resUpdate = $this->putJson("/api/scheduling-filter-presets/filter-presets/{$presetId}", [
            'name' => 'Bộ lọc tuần này đã sửa',
            'filters' => ['view' => 'all'],
            'is_default' => false,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $resUpdate->assertOk();
        $this->assertDatabaseHas('filter_presets', [
            'id' => $presetId,
            'name' => 'Bộ lọc tuần này đã sửa',
            'is_default' => false,
        ]);

        // 4. Delete the preset
        $resDelete = $this->deleteJson("/api/scheduling-filter-presets/filter-presets/{$presetId}", [], ['X-Organization-Id' => $this->orgA->id]);
        $resDelete->assertOk();
        $this->assertDatabaseMissing('filter_presets', ['id' => $presetId]);
    }

    public function test_stats_endpoint_returns_correct_statistics(): void
    {
        Sanctum::actingAs($this->admin);

        // Create test schedules with different statuses
        $this->createSchedule($this->orgA->id, ['status' => 0]); // Draft
        $this->createSchedule($this->orgA->id, ['status' => 0]); // Draft
        $this->createSchedule($this->orgA->id, ['status' => 1]); // Pending
        $this->createSchedule($this->orgA->id, ['status' => 2]); // Published
        $this->createSchedule($this->orgA->id, ['status' => 3]); // Cancelled

        $res = $this->getJson('/api/schedules/stats', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $res->assertJsonFragment([
            'total' => 5,
            'draft' => 2,
            'pending' => 1,
            'published' => 1,
            'cancelled' => 1,
        ]);
    }

    public function test_change_status_endpoint_updates_single_schedule(): void
    {
        Sanctum::actingAs($this->admin);

        $schedule = $this->createSchedule($this->orgA->id, ['status' => 0]);

        $res = $this->patchJson("/api/schedules/{$schedule->id}/status", [
            'status' => 2, // Publish
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'status' => 2,
        ]);
    }

    public function test_bulk_update_status_endpoint_updates_multiple_schedules(): void
    {
        Sanctum::actingAs($this->admin);

        $sch1 = $this->createSchedule($this->orgA->id, ['status' => 0]);
        $sch2 = $this->createSchedule($this->orgA->id, ['status' => 1]);

        $res = $this->patchJson('/api/schedules/bulk-status', [
            'ids' => [$sch1->id, $sch2->id],
            'status' => 2, // Publish
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseHas('schedules', [
            'id' => $sch1->id,
            'status' => 2,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $sch2->id,
            'status' => 2,
        ]);
    }

    public function test_bulk_destroy_endpoint_deletes_multiple_schedules(): void
    {
        Sanctum::actingAs($this->admin);

        $sch1 = $this->createSchedule($this->orgA->id);
        $sch2 = $this->createSchedule($this->orgA->id);

        $res = $this->deleteJson('/api/schedules/bulk-delete', [
            'ids' => [$sch1->id, $sch2->id],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        
        // Verify soft deletion
        $this->assertSoftDeleted('schedules', ['id' => $sch1->id]);
        $this->assertSoftDeleted('schedules', ['id' => $sch2->id]);
    }

    public function test_import_template_endpoint_returns_download(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/schedules/import-template', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('Content-Disposition') ?? '', 'import-schedules-template.xlsx')
        );
    }

    public function test_import_endpoint_performs_validation(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/schedules/import', [], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['file']);
    }
}
