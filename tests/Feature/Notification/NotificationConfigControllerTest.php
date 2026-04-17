<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class NotificationConfigControllerTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seedNotificationConfig();
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_modules_endpoint_returns_registry(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->getJson('/api/notifications/modules');

        $res->assertOk();
        $res->assertJsonPath('data.0.key', 'task_assignment');
        $res->assertJsonPath('data.0.label', 'Giao việc');
        $this->assertCount(6, $res->json('data.0.events'));
        $this->assertTrue($res->json('data.0.events.3.is_reminder'));
    }

    public function test_event_config_index_returns_schedules_eager(): void
    {
        $this->actingAsSuperAdmin();
        $this->enableEvent('document_issued', ['sms', 'mail']);

        $res = $this->getJson('/api/task-assignment/notification-config/event-configs');

        $res->assertOk();
        $this->assertCount(6, $res->json('data'));
        $documentIssued = collect($res->json('data'))->firstWhere('event_key', 'document_issued');
        $this->assertTrue($documentIssued['enabled']);
        $this->assertCount(1, $documentIssued['schedules']);
        $this->assertSame(['sms', 'mail'], $documentIssued['schedules'][0]['channels']);
    }

    public function test_event_config_update_toggles_enabled(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->putJson('/api/task-assignment/notification-config/event-configs/document_issued', [
            'enabled' => true,
        ]);

        $res->assertOk();
        $cfg = NotificationEventConfig::where('event_key', 'document_issued')->first();
        $this->assertTrue($cfg->enabled);
    }

    public function test_schedule_store_for_reminder_event(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->postJson('/api/task-assignment/notification-config/event-configs/reminder_before/schedules', [
            'moment' => 'before',
            'offset_minutes' => 180,
            'channels' => ['sms', 'fcm'],
            'label' => 'Trước 3 giờ',
            'sort_order' => 5,
        ]);

        $res->assertCreated();
        $schedule = NotificationSchedule::latest()->first();
        $this->assertSame('before', $schedule->moment);
        $this->assertSame(180, $schedule->offset_minutes);
        $this->assertSame(['sms', 'fcm'], $schedule->channels);
    }

    public function test_schedule_store_for_non_reminder_forces_null_moment(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->postJson('/api/task-assignment/notification-config/event-configs/document_issued/schedules', [
            'moment' => 'before', // should be reset
            'offset_minutes' => 60, // should be reset
            'channels' => ['mail'],
            'label' => 'Extra instant',
        ]);

        $res->assertCreated();
        $schedule = NotificationSchedule::latest()->first();
        $this->assertNull($schedule->moment);
        $this->assertNull($schedule->offset_minutes);
    }

    public function test_schedule_update_by_id(): void
    {
        $this->actingAsSuperAdmin();
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);

        $res = $this->putJson("/api/notifications/schedules/{$schedule->id}", [
            'channels' => ['sms', 'mail'],
        ]);

        $res->assertOk();
        $this->assertSame(['sms', 'mail'], $schedule->fresh()->channels);
    }

    public function test_schedule_delete_by_id(): void
    {
        $this->actingAsSuperAdmin();
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);

        $res = $this->deleteJson("/api/notifications/schedules/{$schedule->id}");

        $res->assertOk();
        $this->assertNull(NotificationSchedule::find($schedule->id));
    }

    public function test_unauthenticated_returns_401(): void
    {
        $res = $this->getJson('/api/task-assignment/notification-config/event-configs');
        $res->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = User::factory()->create(); // no role
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/task-assignment/notification-config/event-configs');
        $res->assertForbidden();
    }
}
