<?php

namespace Tests\Feature\Meeting;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class MeetingCatalogExportImportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'cat-org'], ['name' => 'Cat Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);
    }

    public function test_meeting_types_import_template_returns_xlsx(): void
    {
        $res = $this->get('/api/meeting-types/import-template', ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('Content-Type'));
    }

    public function test_meeting_types_export_returns_xlsx(): void
    {
        MeetingType::create(['organization_id' => $this->org->id, 'name' => 'Họp giao ban', 'status' => 'active']);

        $res = $this->get('/api/meeting-types/export', ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $contentType = $res->headers->get('Content-Type');
        $this->assertStringContainsString('spreadsheetml', $contentType);
    }

    public function test_meeting_types_import_creates_rows(): void
    {
        $rows = [
            ['name' => 'Họp giao ban', 'status' => 'active'],
            ['name' => 'Họp chuyên đề', 'status' => 'inactive'],
        ];
        $file = $this->makeXlsxUpload($rows);

        $res = $this->post(
            '/api/meeting-types/import',
            ['file' => $file],
            ['X-Organization-Id' => $this->org->id]
        );

        $res->assertOk();
        $this->assertDatabaseHas('meeting_types', ['name' => 'Họp giao ban', 'status' => 'active', 'organization_id' => $this->org->id]);
        $this->assertDatabaseHas('meeting_types', ['name' => 'Họp chuyên đề', 'status' => 'inactive']);
    }

    public function test_meeting_attendees_import_creates_rows(): void
    {
        $rows = [
            ['name' => 'Nguyễn Văn A', 'email' => 'a@example.com', 'phone' => '', 'status' => 'active'],
            ['name' => 'Trần Thị B', 'email' => '', 'phone' => '0901234567', 'status' => 'active'],
        ];
        $file = $this->makeXlsxUpload($rows);

        $res = $this->post(
            '/api/meeting-attendees/import',
            ['file' => $file],
            ['X-Organization-Id' => $this->org->id]
        );

        $res->assertOk();
        $this->assertSame(2, MeetingAttendee::where('organization_id', $this->org->id)->count());
        $this->assertDatabaseHas('meeting_attendees', ['name' => 'Nguyễn Văn A', 'email' => 'a@example.com']);
    }

    /** Build an xlsx in-memory file with header from first row keys + value rows. */
    private function makeXlsxUpload(array $rows): UploadedFile
    {
        $headers = array_keys($rows[0]);
        $data = [$headers];
        foreach ($rows as $row) {
            $data[] = array_values($row);
        }
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        Excel::store(new class($data) implements \Maatwebsite\Excel\Concerns\FromArray
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        }, basename($path), 'local');

        // Convert storage path to absolute for UploadedFile.
        $stored = storage_path('app/private/'.basename($path));
        if (! file_exists($stored)) {
            $stored = storage_path('app/'.basename($path));
        }

        return new UploadedFile($stored, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
