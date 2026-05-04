<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class MeetingNotificationConfigTest extends TestCase
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

    public function test_event_config_index_returns_only_meeting_events(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->getJson('/api/meeting/notification-config/event-configs');

        $res->assertOk();
        $events = collect($res->json('data'))->pluck('event_key')->all();
        sort($events);
        $this->assertSame([
            'meeting_published',
            'meeting_reminder_after',
            'meeting_reminder_before',
            'meeting_reminder_on',
        ], $events);
    }

    public function test_modules_endpoint_returns_meeting_module(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->getJson('/api/notifications/modules');

        $res->assertOk();
        $modules = collect($res->json('data'))->pluck('key')->all();
        $this->assertContains('meeting', $modules);
        $meeting = collect($res->json('data'))->firstWhere('key', 'meeting');
        $eventKeys = collect($meeting['events'])->pluck('key')->all();
        $this->assertContains('meeting_published', $eventKeys);
        $this->assertContains('meeting_reminder_before', $eventKeys);
        $this->assertSame(true, collect($meeting['events'])->firstWhere('key', 'meeting_reminder_before')['is_reminder']);
        $this->assertSame(false, collect($meeting['events'])->firstWhere('key', 'meeting_published')['is_reminder']);
    }

    public function test_schedule_store_for_meeting_reminder_event(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->postJson('/api/meeting/notification-config/event-configs/meeting_reminder_before/schedules', [
            'moment' => 'before',
            'offset_minutes' => 60,
            'channels' => ['fcm', 'mail'],
            'label' => 'Trước 1 giờ',
            'sort_order' => 0,
        ]);

        $res->assertCreated();
        $schedule = NotificationSchedule::latest()->first();
        $this->assertSame('before', $schedule->moment);
        $this->assertSame(60, $schedule->offset_minutes);
        $this->assertSame(['fcm', 'mail'], $schedule->channels);
    }

    public function test_meeting_save_schedules_pending_reminders_when_config_enabled(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $org = $this->resolveTestOrganization();

        // Enable + add schedule
        $config = $this->enableEvent('meeting_reminder_before', ['fcm']);
        NotificationSchedule::create([
            'notification_event_config_id' => $config->id,
            'moment' => 'before',
            'offset_minutes' => 30,
            'channels' => ['fcm'],
            'label' => 'Before 30m',
            'sort_order' => 0,
        ]);

        $start = now()->addHours(2);
        $meeting = Meeting::create([
            'organization_id' => $org->id,
            'title' => 'M',
            'is_public' => false,
            'start_time' => $start,
            'status' => 'draft',
        ]);

        $reminders = MeetingReminder::where('meeting_id', $meeting->id)->get();
        $this->assertSame(1, $reminders->count());
        $this->assertSame('before', $reminders->first()->moment);
        $this->assertSame('pending', $reminders->first()->status);
        $this->assertEqualsWithDelta(
            $start->copy()->subMinutes(30)->timestamp,
            $reminders->first()->scheduled_at->timestamp,
            5
        );
    }

    public function test_cancelled_meeting_cancels_pending_reminders(): void
    {
        $this->actingAsSuperAdmin();
        $org = $this->resolveTestOrganization();

        $config = $this->enableEvent('meeting_reminder_on', ['fcm']);
        NotificationSchedule::create([
            'notification_event_config_id' => $config->id,
            'moment' => 'on',
            'offset_minutes' => null,
            'channels' => ['fcm'],
            'label' => 'On',
            'sort_order' => 0,
        ]);

        $meeting = Meeting::create([
            'organization_id' => $org->id,
            'title' => 'M',
            'is_public' => false,
            'start_time' => now()->addHour(),
            'status' => 'draft',
        ]);
        $this->assertSame(1, MeetingReminder::where('meeting_id', $meeting->id)->where('status', 'pending')->count());

        $meeting->update(['status' => 'cancelled']);

        $this->assertSame(0, MeetingReminder::where('meeting_id', $meeting->id)->where('status', 'pending')->count());
        $this->assertSame(1, MeetingReminder::where('meeting_id', $meeting->id)->where('status', 'cancelled')->count());
    }
}
