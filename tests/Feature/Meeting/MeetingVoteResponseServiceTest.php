<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use App\Modules\Meeting\Services\MeetingVoteResponseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingVoteResponseServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private MeetingVoteResponseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::firstOrCreate(['slug' => 'vote-org'], ['name' => 'Vote Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        Sanctum::actingAs(User::factory()->create());
        $this->service = app(MeetingVoteResponseService::class);
    }

    private function makeMeeting(): Meeting
    {
        return Meeting::create([
            'organization_id' => $this->org->id,
            'title' => 'M',
            'is_public' => false,
            'start_time' => now()->addDay(),
            'status' => 'draft',
        ]);
    }

    private function makeParticipant(Meeting $meeting, ?User $user = null): MeetingParticipant
    {
        $user ??= User::factory()->create();
        $attendee = MeetingAttendee::create([
            'organization_id' => $this->org->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $participant = MeetingParticipant::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $meeting->id,
            'meeting_attendee_id' => $attendee->id,
            'display_name' => $user->name,
            'response_status' => 'pending',
        ]);

        // Service derive participant từ auth user → expose user qua relation cho test gọi actingAs.
        $participant->setRelation('attendee', $attendee->setRelation('user', $user));

        return $participant;
    }

    private function makeTopic(Meeting $meeting, string $phase = 'opened'): MeetingVoteTopic
    {
        // Phase derived từ opened_at + closed_at (cột status đã drop ở migration 2026_05_07_230000):
        //   opened_at NULL                  → 'draft'
        //   opened_at NOT NULL + closed_at NULL → 'opened'
        //   closed_at NOT NULL              → 'closed'
        $timestamps = match ($phase) {
            'draft' => ['opened_at' => null, 'closed_at' => null],
            'opened' => ['opened_at' => now(), 'closed_at' => null],
            'closed' => ['opened_at' => now()->subMinute(), 'closed_at' => now()],
        };

        return MeetingVoteTopic::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $meeting->id,
            'title' => 'T',
            'vote_type' => 'agree_disagree',
            'ballot_mode' => 'open',
            ...$timestamps,
        ]);
    }

    public function test_store_upserts_one_response_per_participant_per_topic(): void
    {
        $meeting = $this->makeMeeting();
        $participant = $this->makeParticipant($meeting);
        Sanctum::actingAs($participant->attendee->user);
        $topic = $this->makeTopic($meeting);

        $first = $this->service->store([
            'meeting_vote_topic_id' => $topic->id,
            'meeting_participant_id' => $participant->id,
            'option' => 'agree',
        ]);
        $second = $this->service->store([
            'meeting_vote_topic_id' => $topic->id,
            'meeting_participant_id' => $participant->id,
            'option' => 'disagree',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('disagree', $second->fresh()->option);
        $this->assertSame(1, MeetingVoteResponse::query()
            ->where('meeting_vote_topic_id', $topic->id)
            ->where('meeting_participant_id', $participant->id)
            ->count());
    }

    public function test_store_rejects_participant_from_different_meeting(): void
    {
        $meetingX = $this->makeMeeting();
        $meetingY = $this->makeMeeting();
        $topicX = $this->makeTopic($meetingX);
        $participantY = $this->makeParticipant($meetingY);
        // Acting as user của meeting Y → vote cho topic ở meeting X → không tìm được participant trong X.
        Sanctum::actingAs($participantY->attendee->user);

        $this->expectException(ModelNotFoundException::class);

        $this->service->store([
            'meeting_vote_topic_id' => $topicX->id,
            'option' => 'agree',
        ]);
    }

    public function test_store_rejects_when_topic_is_draft(): void
    {
        $meeting = $this->makeMeeting();
        $participant = $this->makeParticipant($meeting);
        $topic = $this->makeTopic($meeting, 'draft');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->store([
            'meeting_vote_topic_id' => $topic->id,
            'meeting_participant_id' => $participant->id,
            'option' => 'agree',
        ]);
    }

    public function test_store_rejects_when_topic_is_closed(): void
    {
        $meeting = $this->makeMeeting();
        $participant = $this->makeParticipant($meeting);
        $topic = $this->makeTopic($meeting, 'closed');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->store([
            'meeting_vote_topic_id' => $topic->id,
            'meeting_participant_id' => $participant->id,
            'option' => 'agree',
        ]);
    }

    public function test_update_rejects_when_topic_is_closed(): void
    {
        $meeting = $this->makeMeeting();
        $participant = $this->makeParticipant($meeting);
        Sanctum::actingAs($participant->attendee->user);
        $topic = $this->makeTopic($meeting, 'opened');

        $response = $this->service->store([
            'meeting_vote_topic_id' => $topic->id,
            'option' => 'agree',
        ]);

        $topic->update(['closed_at' => now()]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->update($response, ['option' => 'disagree']);
    }
}
