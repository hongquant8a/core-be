<?php

namespace Tests\Feature\Scheduling;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\NotificationGroup;
use App\Modules\Scheduling\Models\ScheduleReminder;
use App\Services\Notification\Events\SchedulePublished;
use App\Services\Notification\Events\ScheduleUpdated;
use App\Services\Notification\Events\ScheduleCancelled;
use App\Services\Notification\Jobs\SendScheduleReminderJob;
use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
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
    }

    public function test_draft_schedule_does_not_dispatch_notifications()
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/schedules', [
            'module_type' => 'EXECUTIVE',
            'event_date' => '2026-06-01',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'session' => 'S',
            'content' => 'Lịch thảo luận giao ban',
            'host_id' => $this->admin->id,
            'location' => 'Phòng họp 1',
            'nature' => 'HOST',
            'status' => 0, // DRAFT
            'recipients' => [
                ['user_id' => $this->recipientUser->id],
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
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_publishing_schedule_dispatches_published_notification_and_schedules_reminders()
    {
        Queue::fake([SendScheduleReminderJob::class]);

        Sanctum::actingAs($this->admin);

        // Store with status = 2 (Published)
        $res = $this->postJson('/api/schedules', [
            'module_type' => 'EXECUTIVE',
            'event_date' => '2026-06-01',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'session' => 'S',
            'content' => 'Lịch thảo luận giao ban',
            'host_id' => $this->admin->id,
            'location' => 'Phòng họp 1',
            'nature' => 'HOST',
            'status' => 2, // PUBLISHED
            'recipients' => [
                ['user_id' => $this->recipientUser->id],
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
        
        // Assert Notification model entry created in database
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->recipientUser->id,
            'event_key' => 'schedule_published',
        ]);

        // Assert NotificationDelivery rows created
        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'fcm',
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'mail',
        ]);

        // Assert reminder jobs pushed to queue
        Queue::assertPushed(SendScheduleReminderJob::class);
    }

    public function test_updating_published_schedule_dispatches_updated_notification()
    {
        Queue::fake([SendScheduleReminderJob::class]);

        Sanctum::actingAs($this->admin);

        // Create published schedule directly
        $schedule = Schedule::create([
            'organization_id' => $this->org->id,
            'module_type' => 'EXECUTIVE',
            'event_date' => '2026-06-01',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'session' => 'S',
            'content' => 'Họp ban chỉ đạo',
            'host_id' => $this->admin->id,
            'location' => 'Phòng họp A',
            'nature' => 'HOST',
            'status' => 2, // Published
            'created_by' => $this->admin->id,
            'week_number' => 23,
            'year' => 2026,
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
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->recipientUser->id,
            'event_key' => 'schedule_updated',
        ]);
    }

    public function test_unpublishing_or_cancelling_schedule_dispatches_cancelled_notification()
    {
        Sanctum::actingAs($this->admin);

        $schedule = Schedule::create([
            'organization_id' => $this->org->id,
            'module_type' => 'EXECUTIVE',
            'event_date' => '2026-06-01',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'session' => 'S',
            'content' => 'Họp ban chỉ đạo',
            'host_id' => $this->admin->id,
            'location' => 'Phòng họp A',
            'nature' => 'HOST',
            'status' => 2, // Published
            'created_by' => $this->admin->id,
            'week_number' => 23,
            'year' => 2026,
        ]);

        $schedule->recipients()->create(['user_id' => $this->recipientUser->id]);
        $reminder = $schedule->reminders()->create([
            'minutes_before' => 30,
            'channels' => ['fcm', 'inapp'],
            'source' => 'custom',
        ]);

        // Update status to Draft (0)
        $res = $this->putJson("/api/schedules/{$schedule->id}", [
            'status' => 0,
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();

        // Verify cancellation notification is generated
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->recipientUser->id,
            'event_key' => 'schedule_cancelled',
        ]);
    }
}
