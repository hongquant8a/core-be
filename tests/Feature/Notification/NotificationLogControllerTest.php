<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        // Set X-Organization-Id header (required by SetPermissionsTeamId middleware)
        $defaultOrg = Organization::where('slug', 'default')->first();
        if ($defaultOrg) {
            $this->withHeader('X-Organization-Id', (string) $defaultOrg->id);
            setPermissionsTeamId($defaultOrg->id);
        }
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_index_returns_notifications_paginated(): void
    {
        $this->actingAsSuperAdmin();
        Notification::factory()->count(3)->create();

        $res = $this->getJson('/api/notifications/logs');

        $res->assertOk();
        $this->assertSame(3, $res->json('data.total'));
    }

    public function test_index_filters_by_user_id(): void
    {
        $this->actingAsSuperAdmin();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        Notification::factory()->count(2)->create(['user_id' => $u1->id]);
        Notification::factory()->count(3)->create(['user_id' => $u2->id]);

        $res = $this->getJson("/api/notifications/logs?user_id={$u1->id}");

        $res->assertOk();
        $this->assertSame(2, $res->json('data.total'));
    }

    public function test_index_filters_by_event_key_and_date_range(): void
    {
        $this->actingAsSuperAdmin();
        Notification::factory()->create(['event_key' => 'document_issued', 'created_at' => now()->subDays(5)]);
        Notification::factory()->create(['event_key' => 'task_completed', 'created_at' => now()]);
        Notification::factory()->create(['event_key' => 'document_issued', 'created_at' => now()]);

        $res = $this->getJson('/api/notifications/logs?event_key=document_issued&from_date='.now()->subDays(1)->toDateString());

        $res->assertOk();
        $this->assertSame(1, $res->json('data.total'));
    }

    public function test_index_filters_by_delivery_channel_and_status(): void
    {
        $this->actingAsSuperAdmin();
        $n1 = Notification::factory()->create();
        NotificationDelivery::factory()->create(['notification_id' => $n1->id, 'channel' => 'sms', 'status' => 'sent']);
        $n2 = Notification::factory()->create();
        NotificationDelivery::factory()->create(['notification_id' => $n2->id, 'channel' => 'mail', 'status' => 'failed']);

        $res = $this->getJson('/api/notifications/logs?channel=sms&delivery_status=sent');

        $res->assertOk();
        $this->assertSame(1, $res->json('data.total'));
        $this->assertSame($n1->id, $res->json('data.data.0.id'));
    }

    public function test_show_returns_notification_with_deliveries(): void
    {
        $this->actingAsSuperAdmin();
        $n = Notification::factory()->create();
        NotificationDelivery::factory()->count(2)->create(['notification_id' => $n->id]);

        $res = $this->getJson("/api/notifications/logs/{$n->id}");

        $res->assertOk();
        $this->assertSame($n->id, $res->json('data.id'));
        $this->assertCount(2, $res->json('data.deliveries'));
    }

    public function test_stats_returns_aggregates(): void
    {
        $this->actingAsSuperAdmin();
        $n1 = Notification::factory()->create(['event_key' => 'document_issued']);
        $n2 = Notification::factory()->create(['event_key' => 'task_completed']);
        NotificationDelivery::factory()->create(['notification_id' => $n1->id, 'channel' => 'sms', 'status' => 'sent']);
        NotificationDelivery::factory()->create(['notification_id' => $n1->id, 'channel' => 'mail', 'status' => 'failed']);
        NotificationDelivery::factory()->create(['notification_id' => $n2->id, 'channel' => 'sms', 'status' => 'sent']);

        $res = $this->getJson('/api/notifications/logs/stats');

        $res->assertOk();
        $this->assertSame(2, $res->json('data.total'));
        $this->assertSame(1, $res->json('data.by_event.document_issued'));
        $this->assertSame(1, $res->json('data.by_event.task_completed'));
        $this->assertSame(2, $res->json('data.by_channel.sms'));
        $this->assertSame(1, $res->json('data.by_channel.mail'));
        $this->assertSame(2, $res->json('data.by_status.sent'));
        $this->assertSame(1, $res->json('data.by_status.failed'));
    }
}
