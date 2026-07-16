<?php

namespace Tests\Feature\Beneficiary;

use App\Models\Reminder;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\VisitSchedule;
use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Xác nhận VisitSchedule implement Remindable đúng và tái dùng ReminderScheduler chung
 * (không có Job/bảng reminder riêng của module) — xem app/Modules/Beneficiary/Observers/VisitScheduleObserver.php.
 */
class VisitScheduleReminderTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private User $admin;
    private Beneficiary $beneficiary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->admin = User::factory()->create(['name' => 'Admin User']);

        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Super Admin');

        $this->beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'NCC', 'gender' => 'male', 'status' => 'active',
        ]);

        // Bật event-config nhắc trước 3 ngày cho org test — mô phỏng cán bộ phường tự cấu hình qua UI.
        $config = NotificationEventConfig::create([
            'module_key' => NotificationModuleEnum::Beneficiary->value,
            'organization_id' => $this->orgA->id,
            'event_key' => NotificationEventEnum::BeneficiaryVisitReminderBefore->value,
            'enabled' => true,
        ]);
        NotificationSchedule::create([
            'notification_event_config_id' => $config->id,
            'moment' => 'before',
            'offset_minutes' => 4320,
            'channels' => ['zalo'],
            'label' => 'Nhắc trước 3 ngày',
            'sort_order' => 1,
        ]);
    }

    public function test_creating_visit_schedule_auto_creates_reminder_row(): void
    {
        Sanctum::actingAs($this->admin);

        $visit = VisitSchedule::create([
            'organization_id' => $this->orgA->id,
            'subject_type' => (new Beneficiary())->getMorphClass(),
            'subject_id' => $this->beneficiary->id,
            'occasion' => 'tet',
            'scheduled_date' => now()->addDays(10),
            'status' => 'pending',
            'assigned_to' => $this->admin->id,
        ]);

        $reminder = Reminder::where('remindable_id', $visit->id)
            ->where('remindable_type', $visit->getMorphClass())
            ->first();

        $this->assertNotNull($reminder, 'ReminderScheduler phải tự tạo reminder row qua VisitScheduleObserver::saved()');
        $this->assertEquals(
            $visit->scheduled_date->copy()->startOfDay()->subMinutes(4320)->format('Y-m-d H:i'),
            $reminder->remind_at->format('Y-m-d H:i'),
        );
    }

    public function test_marking_done_cancels_pending_reminder(): void
    {
        Sanctum::actingAs($this->admin);

        $visit = VisitSchedule::create([
            'organization_id' => $this->orgA->id,
            'subject_type' => (new Beneficiary())->getMorphClass(),
            'subject_id' => $this->beneficiary->id,
            'occasion' => 'tet',
            'scheduled_date' => now()->addDays(10),
            'status' => 'pending',
            'assigned_to' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('reminders', [
            'remindable_id' => $visit->id,
            'status' => 'pending',
        ]);

        $res = $this->patchJson("/api/beneficiary-visit-schedules/{$visit->id}/status", [
            'status' => 'done',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();

        $this->assertDatabaseMissing('reminders', [
            'remindable_id' => $visit->id,
            'status' => 'pending',
        ]);
    }
}
