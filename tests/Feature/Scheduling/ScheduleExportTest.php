<?php

namespace Tests\Feature\Scheduling;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;
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
        return Schedule::create(array_merge([
            'organization_id' => $this->orgA->id,
            'module_type' => 'EXECUTIVE',
            'event_date' => '2026-06-01',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'session' => 'S',
            'content' => 'Họp giao ban định kỳ',
            'host_id' => $this->admin->id,
            'location' => 'Phòng họp A',
            'nature' => 'HOST',
            'status' => 2, // Published
            'sort_order' => 1,
            'week_number' => 23,
            'year' => 2026,
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_driver_can_view_own_assigned_published_schedules(): void
    {
        $schedule1 = $this->createSchedule([
            'driver_id' => $this->driver->id,
            'content' => 'Lịch đi sân bay đón Lãnh đạo',
            'status' => 2,
        ]);

        $schedule2 = $this->createSchedule([
            'driver_id' => $this->otherDriver->id,
            'content' => 'Lịch đi họp của Lái xe khác',
            'status' => 2,
        ]);

        $scheduleDraft = $this->createSchedule([
            'driver_id' => $this->driver->id,
            'content' => 'Lịch nháp của driver',
            'status' => 0, // Draft
        ]);

        Sanctum::actingAs($this->driver);

        $res = $this->getJson('/api/schedules/driver/my-schedules?from=2026-06-01&to=2026-06-07', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($schedule1->id, $data[0]['id']);
        $this->assertEquals('Lịch đi sân bay đón Lãnh đạo', $data[0]['content']);
    }

    public function test_driver_can_view_own_assigned_published_schedule_detail(): void
    {
        $schedule = $this->createSchedule([
            'driver_id' => $this->driver->id,
            'content' => 'Nội dung chi tiết chuyến đi',
            'status' => 2,
        ]);

        Sanctum::actingAs($this->driver);

        $res = $this->getJson('/api/schedules/driver/my-schedules/' . $schedule->id, [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals($schedule->id, $data['id']);
        $this->assertEquals('Nội dung chi tiết chuyến đi', $data['content']);
    }

    public function test_driver_cannot_view_other_driver_assigned_schedule_detail(): void
    {
        $schedule = $this->createSchedule([
            'driver_id' => $this->otherDriver->id,
            'content' => 'Nội dung chi tiết của driver khác',
            'status' => 2,
        ]);

        Sanctum::actingAs($this->driver);

        $res = $this->getJson('/api/schedules/driver/my-schedules/' . $schedule->id, [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertStatus(403);
    }

    public function test_driver_cannot_view_unpublished_schedule_detail(): void
    {
        $schedule = $this->createSchedule([
            'driver_id' => $this->driver->id,
            'content' => 'Nội dung chi tiết nháp',
            'status' => 0, // Draft
        ]);

        Sanctum::actingAs($this->driver);

        $res = $this->getJson('/api/schedules/driver/my-schedules/' . $schedule->id, [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertStatus(404);
    }

    public function test_staff_without_role_cannot_access_driver_view(): void
    {
        Sanctum::actingAs($this->staff);

        $res = $this->getJson('/api/schedules/driver/my-schedules', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertStatus(403);
    }

    public function test_admin_can_export_excel(): void
    {
        $this->createSchedule(['week_number' => 23, 'year' => 2026]);

        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/schedules/export/excel?week_number=23&year=2026', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertOk();
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition'), 'attachment')
        );
        $this->assertTrue(
            str_contains($res->headers->get('content-disposition'), 'lich-cong-tac-tuan')
        );
    }

    public function test_admin_can_export_pdf(): void
    {
        $this->createSchedule(['week_number' => 23, 'year' => 2026]);

        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/schedules/export/pdf?week_number=23&year=2026', [
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
        $this->createSchedule(['week_number' => 23, 'year' => 2026]);

        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/schedules/export/word?week_number=23&year=2026', [
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

        $res = $this->getJson('/api/schedules/export/excel?week_number=23&year=2026', [
            'X-Organization-Id' => $this->orgA->id
        ]);

        $res->assertStatus(403);
    }
}
