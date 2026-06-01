<?php

namespace Tests\Feature\Scheduling;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\SchedulingSetting;
use App\Modules\Scheduling\Enums\{ScheduleStatusEnum, ScheduleSessionEnum, ScheduleModuleTypeEnum};
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
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'date' => '2026-06-01',
            'session' => ScheduleSessionEnum::Morning->value,
            'title' => 'Họp giao ban định kỳ',
            'content' => 'Nội dung họp chi tiết',
            'host_user_id' => $this->admin->id,
            'location' => 'Phòng họp A',
            'status' => ScheduleStatusEnum::Draft->value,
            'sort_order' => 1,
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

        $mine = $this->createSchedule($this->orgA->id, ['title' => 'Lịch Org A']);
        $other = $this->createSchedule($this->orgB->id, ['title' => 'Lịch Org B']);

        $res = $this->getJson('/api/schedules', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $titles = collect($res->json('data'))->pluck('title')->all();
        $this->assertContains('Lịch Org A', $titles);
        $this->assertNotContains('Lịch Org B', $titles);
    }

    public function test_store_creates_schedule_successfully(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/schedules', [
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'date' => '2026-06-01',
            'session' => ScheduleSessionEnum::Morning->value,
            'title' => 'Họp thông qua kế hoạch mới',
            'host_user_id' => $this->staff->id,
            'location' => 'Hội trường lớn',
            'status' => ScheduleStatusEnum::Draft->value,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('schedules', [
            'organization_id' => $this->orgA->id,
            'title' => 'Họp thông qua kế hoạch mới',
            'location' => 'Hội trường lớn',
            'status' => ScheduleStatusEnum::Draft->value,
        ]);
    }

    public function test_driver_view_contains_restricted_payload_only(): void
    {
        $schedule = $this->createSchedule($this->orgA->id, [
            'driver_user_id' => $this->driver->id,
            'title' => 'Nội dung tuyệt mật quốc gia',
            'status' => ScheduleStatusEnum::Approved->value,
        ]);

        Sanctum::actingAs($this->driver);
        $res = $this->getJson('/api/schedules/driver-view/' . $schedule->id, ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals($schedule->id, $data['id']);
        $this->assertEquals('Phòng họp A', $data['location']);
        $this->assertEquals($schedule->title, $data['title']);
    }

    public function test_admin_view_contains_full_payload(): void
    {
        $schedule = $this->createSchedule($this->orgA->id, [
            'title' => 'Nội dung chi tiết lịch công tác',
        ]);

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/schedules/' . $schedule->id, ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals('Nội dung chi tiết lịch công tác', $data['title']);
    }

    public function test_reorder_updates_sort_orders(): void
    {
        Sanctum::actingAs($this->admin);

        $s1 = $this->createSchedule($this->orgA->id, ['sort_order' => 1]);
        $s2 = $this->createSchedule($this->orgA->id, ['sort_order' => 2]);

        $res = $this->patchJson('/api/schedules/reorder', [
            'ordered_ids' => [$s2->id, $s1->id],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertEquals(2, $s1->fresh()->sort_order);
        $this->assertEquals(1, $s2->fresh()->sort_order);
    }

    public function test_duplicate_creates_multiple_records_on_new_dates(): void
    {
        Sanctum::actingAs($this->admin);

        $schedule = $this->createSchedule($this->orgA->id, [
            'date' => '2026-06-01',
            'title' => 'Lịch lặp lại hàng tuần',
        ]);

        $res = $this->postJson("/api/schedules/{$schedule->id}/duplicate", [
            'date' => '2026-06-08',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('schedules', [
            'organization_id' => $this->orgA->id,
            'date' => '2026-06-08',
            'title' => 'Lịch lặp lại hàng tuần',
        ]);
    }

    public function test_scheduling_settings_crud(): void
    {
        Sanctum::actingAs($this->admin);

        // Fetch settings
        $res = $this->getJson('/api/scheduling-settings', ['X-Organization-Id' => $this->orgA->id]);
        $res->assertOk();

        // Update settings
        $resUpdate = $this->postJson('/api/scheduling-settings', [
            'approval_enabled' => true,
            'approval_module_types' => [ScheduleModuleTypeEnum::Executive->value],
            'default_channels' => ['fcm', 'inapp'],
            'working_sessions' => [
                'MORNING' => ['start' => '08:00', 'end' => '12:00'],
                'AFTERNOON' => ['start' => '13:30', 'end' => '17:30'],
                'EVENING' => ['start' => '18:00', 'end' => '21:00'],
            ],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $resUpdate->assertOk();
        $this->assertDatabaseHas('scheduling_settings', [
            'organization_id' => $this->orgA->id,
            'approval_enabled' => true,
        ]);
    }

    public function test_filter_presets_crud(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Create a filter preset
        $resStore = $this->postJson('/api/scheduling-filter-presets', [
            'name' => 'Bộ lọc tuần này',
            'filters' => ['view' => 'personal', 'status' => 'APPROVED'],
            'is_default' => true,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $resStore->assertStatus(201);
        $presetId = $resStore->json('data.id');

        $this->assertDatabaseHas('scheduling_filter_presets', [
            'id' => $presetId,
            'user_id' => $this->admin->id,
            'name' => 'Bộ lọc tuần này',
            'is_default' => true,
        ]);

        // 2. Fetch the presets list
        $resIndex = $this->getJson('/api/scheduling-filter-presets', ['X-Organization-Id' => $this->orgA->id]);
        $resIndex->assertOk();
        $resIndex->assertJsonFragment(['name' => 'Bộ lọc tuần này']);

        // 3. Update the preset
        $resUpdate = $this->putJson("/api/scheduling-filter-presets/{$presetId}", [
            'name' => 'Bộ lọc tuần này đã sửa',
            'filters' => ['view' => 'all'],
            'is_default' => false,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $resUpdate->assertOk();
        $this->assertDatabaseHas('scheduling_filter_presets', [
            'id' => $presetId,
            'name' => 'Bộ lọc tuần này đã sửa',
            'is_default' => false,
        ]);

        // 4. Delete the preset
        $resDelete = $this->deleteJson("/api/scheduling-filter-presets/{$presetId}", [], ['X-Organization-Id' => $this->orgA->id]);
        $resDelete->assertOk();
        $this->assertDatabaseMissing('scheduling_filter_presets', ['id' => $presetId]);
    }

    public function test_stats_endpoint_returns_correct_statistics(): void
    {
        Sanctum::actingAs($this->admin);

        // Create test schedules with different statuses
        $this->createSchedule($this->orgA->id, ['status' => ScheduleStatusEnum::Draft->value]);
        $this->createSchedule($this->orgA->id, ['status' => ScheduleStatusEnum::Draft->value]);
        $this->createSchedule($this->orgA->id, ['status' => ScheduleStatusEnum::Pending->value]);
        $this->createSchedule($this->orgA->id, ['status' => ScheduleStatusEnum::Approved->value]);
        $this->createSchedule($this->orgA->id, ['status' => ScheduleStatusEnum::Cancelled->value]);

        $res = $this->getJson('/api/schedules/stats', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $res->assertJsonFragment([
            'total' => 5,
            'draft' => 2,
            'pending' => 1,
            'approved' => 1,
            'cancelled' => 1,
        ]);
    }

    public function test_change_status_endpoint_updates_single_schedule(): void
    {
        Sanctum::actingAs($this->admin);

        $schedule = $this->createSchedule($this->orgA->id, ['status' => ScheduleStatusEnum::Draft->value]);

        $res = $this->patchJson("/api/schedules/{$schedule->id}/status", [
            'status' => ScheduleStatusEnum::Approved->value,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'status' => ScheduleStatusEnum::Approved->value,
        ]);
    }

    public function test_bulk_update_status_endpoint_updates_multiple_schedules(): void
    {
        Sanctum::actingAs($this->admin);

        $sch1 = $this->createSchedule($this->orgA->id, ['status' => ScheduleStatusEnum::Draft->value]);
        $sch2 = $this->createSchedule($this->orgA->id, ['status' => ScheduleStatusEnum::Pending->value]);

        $res = $this->patchJson('/api/schedules/bulk-status', [
            'ids' => [$sch1->id, $sch2->id],
            'status' => ScheduleStatusEnum::Approved->value,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseHas('schedules', [
            'id' => $sch1->id,
            'status' => ScheduleStatusEnum::Approved->value,
        ]);
        $this->assertDatabaseHas('schedules', [
            'id' => $sch2->id,
            'status' => ScheduleStatusEnum::Approved->value,
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

    public function test_weeks_endpoint_returns_available_weeks(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createSchedule($this->orgA->id, ['date' => '2026-06-01']); // Week 23, 2026
        $this->createSchedule($this->orgA->id, ['date' => '2026-06-08']); // Week 24, 2026

        $res = $this->getJson('/api/schedules/weeks', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertCount(2, $data);
        $this->assertEquals('2026-W23', $data[0]['week_id']);
        $this->assertEquals(23, $data[0]['week_number']);
        $this->assertEquals('2026-W24', $data[1]['week_id']);
        $this->assertEquals(24, $data[1]['week_number']);
    }

    public function test_week_matrix_returns_week_id(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createSchedule($this->orgA->id, ['date' => '2026-06-01']); // Week 23, 2026

        $res = $this->getJson('/api/schedules/week-matrix?week_number=23&year=2026&module_type=EXECUTIVE', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals('2026-W23', $data['week_id']);
        $this->assertEquals(23, $data['week_number']);
        $this->assertEquals(2026, $data['year']);
        $this->assertArrayHasKey('matrix', $data);
    }
}
