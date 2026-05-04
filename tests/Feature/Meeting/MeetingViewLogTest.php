<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Models\MeetingView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingViewLogTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::firstOrCreate(['slug' => 'view-org'], ['name' => 'View', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
    }

    public function test_public_meeting_show_logs_view_row(): void
    {
        $meeting = Meeting::create([
            'organization_id' => $this->org->id,
            'title' => 'M',
            'is_public' => true,
            'start_time' => now()->addDay(),
            'status' => 'published',
        ]);

        $this->getJson('/api/meetings/public/'.$meeting->id)->assertOk();

        $this->assertSame(1, MeetingView::where('meeting_id', $meeting->id)->count());
        $log = MeetingView::where('meeting_id', $meeting->id)->first();
        $this->assertNull($log->meeting_document_id);
        $this->assertNotNull($log->viewed_at);
    }

    public function test_public_document_show_logs_view_with_document_id(): void
    {
        $meeting = Meeting::create([
            'organization_id' => $this->org->id,
            'title' => 'M',
            'is_public' => true,
            'start_time' => now()->addDay(),
            'status' => 'published',
        ]);
        $doc = MeetingDocument::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $meeting->id,
            'title' => 'D',
            'is_public' => true,
            'status' => 'published',
        ]);

        $this->getJson('/api/meeting-documents/public/'.$doc->id)->assertOk();

        $this->assertSame(1, MeetingView::where('meeting_document_id', $doc->id)->count());
        $log = MeetingView::where('meeting_document_id', $doc->id)->first();
        $this->assertSame($meeting->id, $log->meeting_id);
    }
}
