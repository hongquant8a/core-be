<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify in-meeting control APIs gate qua MeetingPolicy/MeetingVoteTopicPolicy:
 * - Chair only: endEarly, vote-topics open/close
 * - Chair + Operator: lock/unlock attendance, qr-token, highlight-agenda, highlight-discussion
 * - Super Admin bypass tất cả
 * - Outsider (đại biểu thường, user khác) → 403
 */
class MeetingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $chair;

    private User $operator;

    private User $participant;

    private User $outsider;

    private Meeting $meeting;

    private MeetingVoteTopic $topic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\MeetingPermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'policy-org'], ['name' => 'Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        // 4 user khác nhau cho 4 vai trò. Cần assign role để middleware SetPermissionsTeamId
        // cho phép truy cập org (Spatie layer 1 — gate entry vào org).
        $this->chair = User::factory()->create();
        $this->chair->assignRole('Đại biểu');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Đại biểu');
        $this->participant = User::factory()->create();
        $this->participant->assignRole('Đại biểu');
        $this->outsider = User::factory()->create();
        $this->outsider->assignRole('Đại biểu');

        // Tạo attendee + bind relation cho meeting.
        $chairAttendee = MeetingAttendee::create(['organization_id' => $this->org->id, 'user_id' => $this->chair->id, 'status' => 'active']);
        $opAttendee = MeetingAttendee::create(['organization_id' => $this->org->id, 'user_id' => $this->operator->id, 'status' => 'active']);
        $partAttendee = MeetingAttendee::create(['organization_id' => $this->org->id, 'user_id' => $this->participant->id, 'status' => 'active']);

        $this->meeting = Meeting::create([
            'organization_id' => $this->org->id,
            'title' => 'M',
            'is_public' => false,
            'start_time' => now()->subHour(),
            'status' => 'published',
            'chairperson_meeting_attendee_id' => $chairAttendee->id,
            'operator_meeting_attendee_id' => $opAttendee->id,
        ]);

        MeetingParticipant::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'meeting_attendee_id' => $partAttendee->id,
            'display_name' => $this->participant->name,
            'response_status' => 'pending',
        ]);

        $this->topic = MeetingVoteTopic::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'title' => 'T',
            'vote_type' => 'agree_disagree',
            'ballot_mode' => 'open',
        ]);
    }

    public function test_chair_and_operator_can_end_early(): void
    {
        $this->meeting->update(['end_time' => now()->addHour()]);

        Sanctum::actingAs($this->chair);
        $this->patchJson("/api/meetings/{$this->meeting->id}/end-early", [], ['X-Organization-Id' => $this->org->id])->assertOk();

        // Reset để test operator có thể end-early độc lập.
        \App\Modules\Meeting\Models\Meeting::where('id', $this->meeting->id)
            ->update(['end_time' => now()->addHour()]);
        Sanctum::actingAs($this->operator);
        $this->patchJson("/api/meetings/{$this->meeting->id}/end-early", [], ['X-Organization-Id' => $this->org->id])->assertOk();
    }

    public function test_participant_cannot_end_early(): void
    {
        Sanctum::actingAs($this->participant);
        $res = $this->patchJson("/api/meetings/{$this->meeting->id}/end-early", [], ['X-Organization-Id' => $this->org->id]);
        $res->assertForbidden();
    }

    public function test_outsider_cannot_end_early(): void
    {
        Sanctum::actingAs($this->outsider);
        $res = $this->patchJson("/api/meetings/{$this->meeting->id}/end-early", [], ['X-Organization-Id' => $this->org->id]);
        $res->assertForbidden();
    }

    public function test_chair_and_operator_can_lock_attendance(): void
    {
        Sanctum::actingAs($this->chair);
        $this->patchJson("/api/meetings/{$this->meeting->id}/lock-attendance", [], ['X-Organization-Id' => $this->org->id])->assertOk();

        Sanctum::actingAs($this->operator);
        $this->patchJson("/api/meetings/{$this->meeting->id}/unlock-attendance", [], ['X-Organization-Id' => $this->org->id])->assertOk();
    }

    public function test_participant_cannot_lock_attendance(): void
    {
        Sanctum::actingAs($this->participant);
        $res = $this->patchJson("/api/meetings/{$this->meeting->id}/lock-attendance", [], ['X-Organization-Id' => $this->org->id]);
        $res->assertForbidden();
    }

    public function test_chair_and_operator_can_open_vote_topic(): void
    {
        Sanctum::actingAs($this->chair);
        $this->patchJson("/api/meeting-vote-topics/{$this->topic->id}/open", [], ['X-Organization-Id' => $this->org->id])->assertOk();

        // Đóng lại rồi test operator.
        $this->topic->update(['opened_at' => null, 'closed_at' => null]);
        Sanctum::actingAs($this->operator);
        $this->patchJson("/api/meeting-vote-topics/{$this->topic->id}/open", [], ['X-Organization-Id' => $this->org->id])->assertOk();
    }

    public function test_participant_cannot_open_vote_topic(): void
    {
        Sanctum::actingAs($this->participant);
        $res = $this->patchJson("/api/meeting-vote-topics/{$this->topic->id}/open", [], ['X-Organization-Id' => $this->org->id]);
        $res->assertForbidden();
    }

    public function test_super_admin_bypasses_policy(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        // Admin không phải chair/operator/participant nhưng vẫn pass policy.
        $this->meeting->update(['end_time' => now()->addHour()]);
        $this->patchJson("/api/meetings/{$this->meeting->id}/end-early", [], ['X-Organization-Id' => $this->org->id])->assertOk();
        $this->patchJson("/api/meeting-vote-topics/{$this->topic->id}/open", [], ['X-Organization-Id' => $this->org->id])->assertOk();
    }
}
