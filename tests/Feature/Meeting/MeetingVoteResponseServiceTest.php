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

    private function makeParticipant(Meeting $meeting): MeetingParticipant
    {
        $attendee = MeetingAttendee::create([
            'organization_id' => $this->org->id,
            'name' => 'A',
        ]);

        return MeetingParticipant::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $meeting->id,
            'meeting_attendee_id' => $attendee->id,
            'display_name' => $attendee->name,
            'response_status' => 'pending',
        ]);
    }

    private function makeTopic(Meeting $meeting): MeetingVoteTopic
    {
        return MeetingVoteTopic::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $meeting->id,
            'title' => 'T',
            'vote_type' => 'agree_disagree',
            'ballot_mode' => 'open',
            'status' => 'draft',
        ]);
    }

    public function test_store_upserts_one_response_per_participant_per_topic(): void
    {
        $meeting = $this->makeMeeting();
        $participant = $this->makeParticipant($meeting);
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

        $this->expectException(ModelNotFoundException::class);

        $this->service->store([
            'meeting_vote_topic_id' => $topicX->id,
            'meeting_participant_id' => $participantY->id,
            'option' => 'agree',
        ]);
    }
}
