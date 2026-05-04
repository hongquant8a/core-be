<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingInvitation;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Services\MeetingService;
use App\Services\Notification\Events\MeetingPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingPublishFlowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private MeetingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::firstOrCreate(['slug' => 'pub-org'], ['name' => 'Pub', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        Sanctum::actingAs(User::factory()->create());
        $this->service = app(MeetingService::class);
    }

    private function makeMeeting(array $overrides = []): Meeting
    {
        return Meeting::create(array_merge([
            'organization_id' => $this->org->id,
            'title' => 'M',
            'is_public' => false,
            'start_time' => now()->addDay(),
            'status' => 'draft',
        ], $overrides));
    }

    private function makeParticipant(Meeting $meeting): MeetingParticipant
    {
        $user = User::factory()->create();
        $attendee = MeetingAttendee::create([
            'organization_id' => $this->org->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return MeetingParticipant::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $meeting->id,
            'meeting_attendee_id' => $attendee->id,
            'display_name' => $user->name,
            'response_status' => 'pending',
        ]);
    }

    public function test_publish_creates_invitation_per_participant_and_dispatches_event(): void
    {
        Event::fake([MeetingPublished::class]);

        $meeting = $this->makeMeeting();
        $p1 = $this->makeParticipant($meeting);
        $p2 = $this->makeParticipant($meeting);

        $this->service->changeStatus($meeting, 'published');

        $this->assertSame(2, MeetingInvitation::where('meeting_id', $meeting->id)->count());
        $this->assertDatabaseHas('meeting_invitations', [
            'meeting_id' => $meeting->id,
            'meeting_participant_id' => $p1->id,
            'status' => 'pending',
            'send_type' => 'now',
        ]);
        $this->assertDatabaseHas('meeting_invitations', [
            'meeting_id' => $meeting->id,
            'meeting_participant_id' => $p2->id,
        ]);
        Event::assertDispatched(MeetingPublished::class, fn ($e) => $e->meeting->id === $meeting->id);
    }

    public function test_republish_does_not_duplicate_invitations(): void
    {
        Event::fake([MeetingPublished::class]);

        $meeting = $this->makeMeeting();
        $this->makeParticipant($meeting);

        $this->service->changeStatus($meeting, 'published');
        $this->service->changeStatus($meeting->fresh(), 'draft');
        $this->service->changeStatus($meeting->fresh(), 'published');

        $this->assertSame(1, MeetingInvitation::where('meeting_id', $meeting->id)->count());
    }

    public function test_non_published_status_change_does_not_create_invitations(): void
    {
        Event::fake([MeetingPublished::class]);

        $meeting = $this->makeMeeting();
        $this->makeParticipant($meeting);

        $this->service->changeStatus($meeting, 'cancelled');

        $this->assertSame(0, MeetingInvitation::where('meeting_id', $meeting->id)->count());
        Event::assertNotDispatched(MeetingPublished::class);
    }
}
