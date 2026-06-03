<?php

namespace Tests\Feature\Scheduling;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Enums\{ScheduleStatusEnum, ScheduleSessionEnum, ScheduleModuleTypeEnum};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleExportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private User $admin;
    private User $driver;
    private User $otherDriver;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->driver = User::factory()->create(['name' => 'Driver User']);
        $this->otherDriver = User::factory()->create(['name' => 'Other Driver']);
        $this->staff = User::factory()->create(['name' => 'Staff User']);

        // Assign Roles under orgA context
        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Super Admin');
        $this->driver->assignRole('Lái xe');
        $this->otherDriver->assignRole('Lái xe');
        $this->staff->assignRole('Nhân viên');
    }

    private function createSchedule(array $overrides = []): Schedule
    {
        $defaults = [
            'organization_id' => $this->orgA->id,
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'date' => '2026-06-01',
            'session' => ScheduleSessionEnum::Morning->value,
            'title' => 'Họp giao ban định kỳ',
            'content' => 'Nội dung chi tiết',
            'host_user_id' => $this->admin->id,
            'location' => 'Phòng họp A',
            'status' => ScheduleStatusEnum::Approved->value,
            'sort_order' => 1,
            'created_by' => $this->admin->id,
        ];

        if (array_key_exists('title', $overrides)) {
            unset($defaults['content']);
        }
        if (array_key_exists('content', $overrides)) {
            unset($defaults['title']);
        }

        return Schedule::create(array_merge($defaults, $overrides));
    }

    public function test_driver_can_view_own_assigned_published_schedules(): void
    {
        $schedule1 = $this->createSchedule([
            'driver_user_id' => $this->driver->id,
            'title' => 'Lịch đi sân bay đón Lãnh đạo',
            'status' => ScheduleStatusEnum::Approved->value,
        ]);

        $schedule2 = $this->createSchedule([
            'driver_user_id' => $this->otherDriver->id,
            'title' => 'Lịch đi họp của Lái xe khác',
            'status' => ScheduleStatusEnum::Approved->value,
        ]);

        $scheduleDraft = $this->createSchedule([
            'driver_user_id' => $this->driver->id,
            'title' => 'Lịch nháp của driver',
            'status' => ScheduleStatusEnum::Draft->value,
        ]);

        Sanctum::actingAs($this->driver);

        $res = $this->getJson('/api/schedules/driver-view?from=2026-06-01&to=2026-06-07', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($schedule1->id, $data[0]['id']);
        $this->assertEquals('Lịch đi sân bay đón Lãnh đạo', $data[0]['title']);
    }

    public function test_driver_can_view_own_assigned_published_schedule_detail(): void
    {
        $schedule = $this->createSchedule([
            'driver_user_id' => $this->driver->id,
            'title' => 'Nội dung chi tiết chuyến đi',
            'status' => ScheduleStatusEnum::Approved->value,
        ]);

        Sanctum::actingAs($this->driver);

        $res = $this->getJson('/api/schedules/driver-view/' . $schedule->id, [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals($schedule->id, $data['id']);
        $this->assertEquals('Nội dung chi tiết chuyến đi', $data['title']);
    }

    public function test_driver_cannot_view_other_driver_assigned_schedule_detail(): void
    {
        $schedule = $this->createSchedule([
            'driver_user_id' => $this->otherDriver->id,
            'title' => 'Nội dung chi tiết của driver khác',
            'status' => ScheduleStatusEnum::Approved->value,
        ]);

        Sanctum::actingAs($this->driver);

        $res = $this->getJson('/api/schedules/driver-view/' . $schedule->id, [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertStatus(403);
    }

    public function test_driver_cannot_view_unpublished_schedule_detail(): void
    {
        $schedule = $this->createSchedule([
            'driver_user_id' => $this->driver->id,
            'title' => 'Nội dung chi tiết nháp',
            'status' => ScheduleStatusEnum::Draft->value,
        ]);

        Sanctum::actingAs($this->driver);

        $res = $this->getJson('/api/schedules/driver-view/' . $schedule->id, [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertStatus(403);
    }

    public function test_staff_without_role_cannot_access_driver_view(): void
    {
        Sanctum::actingAs($this->staff);

        $res = $this->getJson('/api/schedules/driver-view', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertStatus(403);
    }

    public function test_admin_can_export_excel(): void
    {
        $this->createSchedule(['date' => '2026-06-01']);

        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/schedules/export?week_number=23&year=2026', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition'), 'attachment')
        );
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition'), 'lich-cong-tac')
        );
    }

    public function test_admin_can_export_pdf(): void
    {
        $this->createSchedule(['date' => '2026-06-01']);

        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/schedules/export-pdf?week_number=23&year=2026', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition'), 'attachment')
        );
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition'), '.pdf')
        );
    }

    public function test_admin_can_export_word(): void
    {
        $this->createSchedule(['date' => '2026-06-01']);

        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/schedules/export-word?week_number=23&year=2026', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition'), 'attachment')
        );
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition'), '.docx')
        );
        
        $templatePath = storage_path('app/scheduling/templates/weekly-schedule.docx');
        $this->assertFileExists($templatePath);
    }

    public function test_staff_without_permission_cannot_export(): void
    {
        Sanctum::actingAs($this->staff);

        $res = $this->getJson('/api/schedules/export?week_number=23&year=2026', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertStatus(403);
    }
}
