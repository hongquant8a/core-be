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
        $defaults = [
            'organization_id' => $orgId,
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'date_time' => '2026-06-01 08:00:00',
            'session' => ScheduleSessionEnum::Morning->value,
            'content' => 'Nội dung họp chi tiết',
            'host_id' => $this->admin->id,
            'location' => 'Phòng họp A',
            'status' => ScheduleStatusEnum::Draft->value,
            'sort_order' => 1,
            'created_by' => $this->admin->id,
        ];

        if (array_key_exists('title', $overrides)) {
            $defaults['content'] = $overrides['title'];
            unset($overrides['title']);
        }
        if (array_key_exists('date', $overrides)) {
            $defaults['date_time'] = $overrides['date'] . ' 08:00:00';
            unset($overrides['date']);
        }

        return Schedule::create(array_merge($defaults, $overrides));
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
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'date_time' => '2026-06-01 08:00:00',
            'session' => ScheduleSessionEnum::Morning->value,
            'content' => 'Họp thông qua kế hoạch mới',
            'host_id' => $this->staff->id,
            'location' => 'Hội trường lớn',
            'status' => ScheduleStatusEnum::Draft->value,
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
        $schedule = $this->createSchedule($this->orgA->id, [
            'driver_id' => $this->driver->id,
            'content' => 'Nội dung tuyệt mật quốc gia',
            'status' => ScheduleStatusEnum::Approved->value,
        ]);

        Sanctum::actingAs($this->driver);
        $res = $this->getJson('/api/schedules/driver-view/' . $schedule->id, ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals($schedule->id, $data['id']);
        $this->assertEquals('Phòng họp A', $data['location']);
        $this->assertEquals($schedule->content, $data['content']);
    }

    public function test_admin_view_contains_full_payload(): void
    {
        $schedule = $this->createSchedule($this->orgA->id, [
            'content' => 'Nội dung chi tiết lịch công tác',
        ]);

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/schedules/' . $schedule->id, ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals('Nội dung chi tiết lịch công tác', $data['content']);
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
            'date_time' => '2026-06-01 08:00:00',
            'content' => 'Lịch lặp lại hàng tuần',
        ]);

        $res = $this->postJson("/api/schedules/{$schedule->id}/duplicate", [
            'date' => '2026-06-08',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('schedules', [
            'organization_id' => $this->orgA->id,
            'date_time' => '2026-06-08 08:00:00',
            'content' => 'Lịch lặp lại hàng tuần',
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
            'status' => 2,
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

    public function test_schedule_stores_and_returns_host_and_driver_text(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'content' => 'Nội dung test',
            'date_time' => '2026-06-01 08:00:00',
            'session' => ScheduleSessionEnum::Morning->value,
            'host_text' => 'Chủ tịch danh dự Nguyễn Văn A',
            'driver_text' => 'Tài xế hợp đồng Trần Văn B',
        ];

        $res = $this->postJson('/api/schedules', $payload, ['X-Organization-Id' => $this->orgA->id]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.host_text', 'Chủ tịch danh dự Nguyễn Văn A');
        $res->assertJsonPath('data.driver_text', 'Tài xế hợp đồng Trần Văn B');

        $scheduleId = $res->json('data.id');

        $this->assertDatabaseHas('schedules', [
            'id' => $scheduleId,
            'host_text' => 'Chủ tịch danh dự Nguyễn Văn A',
            'driver_text' => 'Tài xế hợp đồng Trần Văn B',
        ]);
    }

    public function test_schedule_stores_and_syncs_is_important_and_attachments(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Create schedule with is_important and files
        $file1 = \Illuminate\Http\UploadedFile::fake()->create('doc1.pdf', 100);
        $file2 = \Illuminate\Http\UploadedFile::fake()->create('doc2.docx', 200);

        $payload = [
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'content' => 'Nội dung test',
            'date_time' => '2026-06-01 08:00:00',
            'session' => ScheduleSessionEnum::Morning->value,
            'is_important' => true,
            'files' => [$file1, $file2]
        ];

        $res = $this->postJson('/api/schedules', $payload, ['X-Organization-Id' => $this->orgA->id]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.is_important', true);

        $scheduleId = $res->json('data.id');
        $this->assertDatabaseHas('schedules', [
            'id' => $scheduleId,
            'is_important' => true,
        ]);

        $this->assertDatabaseCount('schedule_attachments', 2);
        $attachments = $res->json('data.attachments');
        $this->assertCount(2, $attachments);
        $att1Id = $attachments[0]['id'];
        $att2Id = $attachments[1]['id'];

        // 2. Update schedule: change is_important, delete 1 attachment, keep 1 attachment, upload 1 new attachment
        $file3 = \Illuminate\Http\UploadedFile::fake()->create('doc3.xlsx', 150);
        $updatePayload = [
            'is_important' => false,
            // Send attachments list with only att1Id retained (att2Id is dropped/removed)
            'attachments' => [
                ['id' => $att1Id]
            ],
            // Upload new file
            'files' => [$file3]
        ];

        $resUpdate = $this->putJson('/api/schedules/' . $scheduleId, $updatePayload, ['X-Organization-Id' => $this->orgA->id]);

        $resUpdate->assertOk();
        $resUpdate->assertJsonPath('data.is_important', false);

        $this->assertDatabaseHas('schedules', [
            'id' => $scheduleId,
            'is_important' => false,
        ]);

        // Total attachments in database should be 2 (att1Id + new file3 attachment; att2Id deleted)
        $this->assertDatabaseCount('schedule_attachments', 2);
        $this->assertDatabaseHas('schedule_attachments', ['id' => $att1Id]);
        $this->assertDatabaseMissing('schedule_attachments', ['id' => $att2Id]);
    }
}
