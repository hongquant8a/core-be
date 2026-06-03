<?php

namespace Tests\Feature\Scheduling;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ScheduleReminder;
use App\Modules\Scheduling\Enums\{ScheduleStatusEnum, ScheduleSessionEnum, ScheduleModuleTypeEnum};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $recipientUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->org = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->recipientUser = User::factory()->create(['name' => 'Recipient User']);

        setPermissionsTeamId($this->org->id);
        $this->admin->assignRole('Super Admin');
        $this->recipientUser->assignRole('Nhân viên');

        \App\Modules\Scheduling\Models\OrgSchedulingSettings::create([
            'organization_id' => $this->org->id,
            'executive_requires_approval' => false,
            'office_requires_approval' => false,
        ]);
    }

    public function test_draft_schedule_does_not_dispatch_notifications()
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/schedules', [
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'date' => '2026-06-01',
            'session' => ScheduleSessionEnum::Morning->value,
            'title' => 'Lịch thảo luận giao ban',
            'host_user_id' => $this->admin->id,
            'location' => 'Phòng họp 1',
            'status' => ScheduleStatusEnum::Draft->value,
            'participants' => [
                ['user_id' => $this->recipientUser->id, 'role' => 'PARTICIPANT'],
            ],
            'reminders' => [
                [
                    'minutes_before' => 30,
                    'channels' => ['fcm', 'inapp'],
                    'source' => 'custom',
                ]
            ],
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertCreated();
        $this->assertDatabaseCount('schedule_notifications', 0);
    }

    public function test_publishing_schedule_dispatches_published_notification_and_schedules_reminders()
    {
        Sanctum::actingAs($this->admin);

        // Store with status = APPROVED (Published)
        $res = $this->postJson('/api/schedules', [
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'date' => '2026-06-01',
            'session' => ScheduleSessionEnum::Morning->value,
            'title' => 'Lịch thảo luận giao ban',
            'host_user_id' => $this->admin->id,
            'location' => 'Phòng họp 1',
            'status' => ScheduleStatusEnum::Approved->value,
            'participants' => [
                ['user_id' => $this->recipientUser->id, 'role' => 'PARTICIPANT'],
            ],
            'reminders' => [
                [
                    'minutes_before' => 30,
                    'channels' => ['fcm', 'inapp'],
                    'source' => 'custom',
                ]
            ],
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertCreated();
        
        // Assert Notification model entry created in database for recipient
        $this->assertDatabaseHas('schedule_notifications', [
            'user_id' => $this->recipientUser->id,
            'channel' => 'APP',
        ]);
    }

    public function test_updating_published_schedule_dispatches_updated_notification()
    {
        Sanctum::actingAs($this->admin);

        // Create published schedule directly
        $schedule = Schedule::create([
            'organization_id' => $this->org->id,
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'date' => '2026-06-01',
            'session' => ScheduleSessionEnum::Morning->value,
            'title' => 'Họp ban chỉ đạo',
            'host_user_id' => $this->admin->id,
            'location' => 'Phòng họp A',
            'status' => ScheduleStatusEnum::Approved->value,
            'created_by' => $this->admin->id,
        ]);

        $schedule->recipients()->create(['user_id' => $this->recipientUser->id]);
        $reminder = $schedule->reminders()->create([
            'minutes_before' => 30,
            'channels' => ['fcm', 'inapp'],
            'source' => 'custom',
        ]);

        // Now, update location through API
        $res = $this->putJson("/api/schedules/{$schedule->id}", [
            'location' => 'Phòng họp B',
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();

        // Verify update notification is generated
        $this->assertDatabaseHas('schedule_notifications', [
            'user_id' => $this->recipientUser->id,
            'channel' => 'APP',
        ]);
    }

    public function test_cancelling_schedule_dispatches_cancelled_notification()
    {
        Sanctum::actingAs($this->admin);

        $schedule = Schedule::create([
            'organization_id' => $this->org->id,
            'module_type' => ScheduleModuleTypeEnum::Executive->value,
            'date' => '2026-06-01',
            'session' => ScheduleSessionEnum::Morning->value,
            'title' => 'Họp ban chỉ đạo',
            'host_user_id' => $this->admin->id,
            'location' => 'Phòng họp A',
            'status' => ScheduleStatusEnum::Approved->value,
            'created_by' => $this->admin->id,
        ]);

        $schedule->recipients()->create(['user_id' => $this->recipientUser->id]);
        $reminder = $schedule->reminders()->create([
            'minutes_before' => 30,
            'channels' => ['fcm', 'inapp'],
            'source' => 'custom',
        ]);

        // Update status to Cancelled
        $res = $this->patchJson("/api/schedules/{$schedule->id}/status", [
            'status' => ScheduleStatusEnum::Cancelled->value,
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();

        // Verify cancellation notification is generated
        $this->assertDatabaseHas('schedule_notifications', [
            'user_id' => $this->recipientUser->id,
            'channel' => 'APP',
        ]);
    }
}
