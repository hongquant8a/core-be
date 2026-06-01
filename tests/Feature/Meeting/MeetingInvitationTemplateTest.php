<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingGuest;
use App\Modules\Meeting\Models\MeetingInvitationTemplate;
use App\Modules\Meeting\Services\MeetingInvitationGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingInvitationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $admin;
    private MeetingAttendee $attendeeA;
    private MeetingInvitationGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        
        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->orgB = Organization::firstOrCreate(['slug' => 'test-b'], ['name' => 'Org B', 'status' => 'active']);

        setPermissionsTeamId($this->orgA->id);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        
        $this->attendeeA = MeetingAttendee::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->admin->id,
            'status' => 'active',
        ]);
        
        setPermissionsTeamId($this->orgB->id);
        $this->admin->assignRole('Super Admin');
        
        setPermissionsTeamId($this->orgA->id);
        Sanctum::actingAs($this->admin);
        Storage::fake('public');
        
        $this->generator = app(MeetingInvitationGenerator::class);
    }

    private function makeMeeting(int $orgId, array $overrides = []): Meeting
    {
        return Meeting::create(array_merge([
            'organization_id' => $orgId,
            'title' => 'Họp test',
            'is_public' => false,
            'start_time' => now()->addDay(),
            'status' => 'draft',
            'chairperson_meeting_attendee_id' => $this->attendeeA->id,
        ], $overrides));
    }

    private function getRealDocxUploadedFile(): UploadedFile
    {
        $path = $this->generator->generateSample();
        return new UploadedFile(
            $path,
            'template.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true // test mode to allow local files
        );
    }

    public function test_can_list_invitation_templates(): void
    {
        MeetingInvitationTemplate::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Template A',
            'is_default' => true,
            'status' => 'active',
        ]);

        MeetingInvitationTemplate::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Template B',
            'is_default' => true,
            'status' => 'active',
        ]);

        $res = $this->getJson('/api/meeting-invitation-templates', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $items = $res->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame('Template A', $items[0]['name']);
    }

    public function test_can_list_invitation_templates_for_export_without_spatie_permission(): void
    {
        MeetingInvitationTemplate::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Template A',
            'is_default' => true,
            'status' => 'active',
        ]);

        // Create a basic user with NO Spatie permission, but has access to org A
        $user = User::factory()->create();
        setPermissionsTeamId($this->orgA->id);
        $user->assignRole('Nhân viên');

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/meeting-invitation-templates/list', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $items = $res->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame('Template A', $items[0]['name']);
    }

    public function test_can_store_invitation_template(): void
    {
        $file = $this->getRealDocxUploadedFile();

        $res = $this->postJson('/api/meeting-invitation-templates', [
            'name' => 'New Template',
            'description' => 'A nice template',
            'is_default' => true,
            'status' => 'active',
            'file' => $file,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('meeting_invitation_templates', [
            'name' => 'New Template',
            'organization_id' => $this->orgA->id,
            'is_default' => true,
        ]);
        
        if (is_file($file->getPathname())) {
            unlink($file->getPathname());
        }
    }

    public function test_auto_unsets_previous_default_templates_on_same_org(): void
    {
        $fileA = $this->getRealDocxUploadedFile();
        $fileB = $this->getRealDocxUploadedFile();

        $res1 = $this->postJson('/api/meeting-invitation-templates', [
            'name' => 'Template 1',
            'is_default' => true,
            'file' => $fileA,
        ], ['X-Organization-Id' => $this->orgA->id]);
        $id1 = $res1->json('data.id');

        $res2 = $this->postJson('/api/meeting-invitation-templates', [
            'name' => 'Template 2',
            'is_default' => true,
            'file' => $fileB,
        ], ['X-Organization-Id' => $this->orgA->id]);
        $id2 = $res2->json('data.id');

        $this->assertFalse((bool) MeetingInvitationTemplate::find($id1)->is_default);
        $this->assertTrue((bool) MeetingInvitationTemplate::find($id2)->is_default);
        
        if (is_file($fileA->getPathname())) unlink($fileA->getPathname());
        if (is_file($fileB->getPathname())) unlink($fileB->getPathname());
    }

    public function test_cannot_export_invitation_without_guests(): void
    {
        $meeting = $this->makeMeeting($this->orgA->id);
        
        $file = $this->getRealDocxUploadedFile();
        $template = MeetingInvitationTemplate::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Tpl X',
            'is_default' => true,
            'status' => 'active',
        ]);
        $template->addMedia($file)->toMediaCollection('meeting-invitation-template-file');
        $template->update(['media_id' => $template->getFirstMedia('meeting-invitation-template-file')->id]);

        $res = $this->postJson("/api/meeting-invitation-templates/export", [
            'meeting_id' => $meeting->id,
            'template_id' => $template->id,
        ], ['X-Organization-Id' => $this->orgA->id]);

        // Expect 400 because meeting has no guests.
        $res->assertStatus(400);
        
        if (is_file($file->getPathname())) {
            unlink($file->getPathname());
        }
    }

    public function test_can_export_batch_invitations(): void
    {
        $meeting = $this->makeMeeting($this->orgA->id);
        $guest = MeetingGuest::create([
            'meeting_id' => $meeting->id,
            'name' => 'Nguyen Van A',
            'phone' => '0987654321',
            'email' => 'a@test.com',
            'position' => 'Truong phong',
            'organization' => 'Phong IT',
        ]);

        $file = $this->getRealDocxUploadedFile();
        $template = MeetingInvitationTemplate::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Tpl X',
            'is_default' => true,
            'status' => 'active',
        ]);
        $template->addMedia($file)->toMediaCollection('meeting-invitation-template-file');
        $template->update(['media_id' => $template->getFirstMedia('meeting-invitation-template-file')->id]);

        $res = $this->postJson("/api/meeting-invitation-templates/export", [
            'meeting_id' => $meeting->id,
            'template_id' => $template->id,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $res->headers->get('content-type'));
        
        if (is_file($file->getPathname())) {
            unlink($file->getPathname());
        }
    }

    public function test_can_export_single_guest_invitation(): void
    {
        $meeting = $this->makeMeeting($this->orgA->id);
        $guest = MeetingGuest::create([
            'meeting_id' => $meeting->id,
            'name' => 'Nguyen Van A',
            'phone' => '0987654321',
            'email' => 'a@test.com',
            'position' => 'Truong phong',
            'organization' => 'Phong IT',
        ]);

        $file = $this->getRealDocxUploadedFile();
        $template = MeetingInvitationTemplate::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Tpl X',
            'is_default' => true,
            'status' => 'active',
        ]);
        $template->addMedia($file)->toMediaCollection('meeting-invitation-template-file');
        $template->update(['media_id' => $template->getFirstMedia('meeting-invitation-template-file')->id]);

        $res = $this->postJson("/api/meeting-invitation-templates/export", [
            'meeting_id' => $meeting->id,
            'template_id' => $template->id,
            'guest_id' => $guest->id,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $res->headers->get('content-type'));
        
        if (is_file($file->getPathname())) {
            unlink($file->getPathname());
        }
    }
}
