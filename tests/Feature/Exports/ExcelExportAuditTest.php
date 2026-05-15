<?php

namespace Tests\Feature\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Core\Exports\LogActivitiesExport;
use App\Modules\Core\Exports\NotificationLogsExport;
use App\Modules\Core\Exports\OrganizationsExport;
use App\Modules\Core\Exports\PermissionsExport;
use App\Modules\Core\Exports\RolesExport;
use App\Modules\Core\Exports\UsersExport;
use App\Modules\Core\Models\Organization;
use App\Modules\Meeting\Exports\CatalogExport;
use App\Modules\Meeting\Exports\MeetingAttendanceExport;
use App\Modules\Meeting\Exports\MeetingAttendeeExport;
use App\Modules\Meeting\Exports\MeetingDiscussionRegistrationExport;
use App\Modules\Meeting\Exports\MeetingExport;
use App\Modules\Meeting\Exports\MeetingVoteResponseDetailExport;
use App\Modules\Meeting\Exports\MeetingVoteResponseSummaryExport;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendeeGroup;
use App\Modules\Meeting\Models\MeetingDocumentType;
use App\Modules\Meeting\Models\MeetingLocation;
use App\Modules\Meeting\Models\MeetingType;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use App\Modules\TaskAssignment\Exports\DepartmentExport;
use App\Modules\TaskAssignment\Exports\DocumentsExport;
use App\Modules\TaskAssignment\Exports\ItemsExport;
use App\Modules\TaskAssignment\Exports\LookupExport;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemType;
use App\Modules\TaskAssignment\Models\TaskAssignmentType;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Audit toàn bộ export Excel — kiểm tra:
 *  1. Heading có phải tiếng Việt (không snake_case English, không từ technical).
 *  2. Cell data đã human-readable (không raw enum, không FQN class name).
 *  3. AbstractExcelExport apply style chuẩn (font Times New Roman, header xanh, freeze A2).
 *
 * KHÔNG seed data — đọc trực tiếp DB dev (.env DB_DATABASE) để audit dữ liệu thực.
 * Nếu DB rỗng → export return collection trống → test pass trivial (không catch được gì).
 *
 * Chạy: php artisan test --filter=ExcelExportAuditTest
 */
class ExcelExportAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml override DB_DATABASE=testing — bypass để dùng DB dev thật từ .env.
        $devDb = $this->readEnvDbName();
        if ($devDb) {
            config(['database.connections.mysql.database' => $devDb]);
            DB::purge('mysql');
        }
    }

    public function test_all_export_headings_are_vietnamese(): void
    {
        $errors = [];

        foreach ($this->buildExports() as $name => $factory) {
            try {
                $export = $factory();
            } catch (\Throwable $e) {
                $errors[] = "[$name] Setup failed: {$e->getMessage()}";
                continue;
            }
            if ($export === null) {
                continue; // Skip — no required data in DB.
            }

            $headings = $export->headings();
            foreach ($headings as $h) {
                if (! is_string($h) || $h === '') {
                    $errors[] = "[$name] Empty heading detected.";
                    continue;
                }
                // snake_case English (vd: user_name, created_at)
                if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)+$/', $h)) {
                    $errors[] = "[$name] Heading '$h' là snake_case English.";

                    continue;
                }
                // Pure ASCII không có ký tự Việt + không thuộc whitelist viết tắt.
                if (! $this->hasVietnameseChar($h) && ! $this->isAsciiWhitelisted($h)) {
                    $errors[] = "[$name] Heading '$h' không có ký tự tiếng Việt và không nằm trong whitelist.";
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Heading audit có vấn đề:\n" . implode("\n", $errors)
        );
    }

    public function test_all_export_cells_are_human_readable(): void
    {
        // Blocklist: raw enum values, FQN class, raw channel/event keys.
        $blocklist = '/^('
            .'active|inactive|draft|published|pending|registered|completed|accepted|declined|cancelled'
            .'|in_progress|paused|todo|done|overdue'
            .'|agree|disagree|abstain|approve|reject'
            .'|email|sms|zalo|fcm'
            .'|high|medium|low|urgent|normal'
            .'|has_deadline|no_deadline|ongoing'
            .'|before|on|after'
            .'|meeting_[a-z_]+|task_[a-z_]+|document_[a-z_]+|reminder_[a-z_]+'
            .')$/i';
        $fqnRegex = '#^App\\\\Modules\\\\#';

        $errors = [];

        foreach ($this->buildExports() as $name => $factory) {
            try {
                $export = $factory();
            } catch (\Throwable $e) {
                $errors[] = "[$name] Setup failed: {$e->getMessage()}";
                continue;
            }
            if ($export === null) {
                continue;
            }

            $rows = $this->extractRows($export);
            if ($rows === null || $rows->isEmpty()) {
                // Không có data → không assert được. Skip im lặng.
                continue;
            }

            foreach ($rows as $rowIdx => $row) {
                foreach ((array) $row as $colKey => $cell) {
                    if (! is_string($cell) || $cell === '') {
                        continue;
                    }
                    if (preg_match($fqnRegex, $cell)) {
                        $errors[] = "[$name] Row $rowIdx col '$colKey' = '$cell' (FQN class, nên rút gọn).";
                    }
                    // Cell có thể là comma-joined ("email, sms") — split rồi check từng piece.
                    $pieces = array_filter(array_map('trim', preg_split('/[,;|]/', $cell)));
                    foreach ($pieces as $piece) {
                        if (preg_match($blocklist, $piece)) {
                            $errors[] = "[$name] Row $rowIdx col '$colKey' = '$cell' chứa raw value '$piece' (chưa dịch sang tiếng Việt).";
                        }
                    }
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Data audit có vấn đề:\n" . implode("\n", $errors)
        );
    }

    public function test_abstract_export_applies_standard_style(): void
    {
        // Dùng MeetingExport làm đại diện — extends AbstractExcelExport.
        // Render in-memory rồi ghi ra temp file để PhpSpreadsheet parse lại.
        $binary = Excel::raw(new MeetingExport(), \Maatwebsite\Excel\Excel::XLSX);

        $tmp = tempnam(sys_get_temp_dir(), 'excel_audit_') . '.xlsx';
        file_put_contents($tmp, $binary);

        $spreadsheet = IOFactory::load($tmp);
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Font Times New Roman cho A1 (header).
        $this->assertSame(
            'Times New Roman',
            $sheet->getStyle('A1')->getFont()->getName(),
            'Header A1 phải dùng font Times New Roman.'
        );

        // 2. Fill color #1F4E78 cho header.
        $fill = $sheet->getStyle('A1')->getFill();
        $this->assertSame(
            '1F4E78',
            strtoupper($fill->getStartColor()->getRGB()),
            'Header A1 phải có fill color #1F4E78.'
        );

        // 3. Bold + chữ trắng cho header.
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold(), 'Header phải bold.');
        $this->assertSame(
            'FFFFFF',
            strtoupper($sheet->getStyle('A1')->getFont()->getColor()->getRGB()),
            'Header chữ trắng.'
        );

        // 4. Freeze pane = A2 (header pinned).
        $this->assertSame('A2', $sheet->getFreezePane(), 'Phải freeze row 1 (freeze pane A2).');

        @unlink($tmp);
    }

    /**
     * @return array<string, \Closure>
     */
    private function buildExports(): array
    {
        $org = Organization::query()->first();
        $meeting = Meeting::query()->first();
        $topic = MeetingVoteTopic::query()->first();

        return [
            'LogActivitiesExport' => fn () => new LogActivitiesExport(),
            'NotificationLogsExport' => fn () => $org
                ? new NotificationLogsExport(['meeting_published', 'meeting_updated', 'task_assigned'], $org->id)
                : null,
            'OrganizationsExport' => fn () => new OrganizationsExport(),
            'PermissionsExport' => fn () => new PermissionsExport(),
            'RolesExport' => fn () => new RolesExport(),
            'UsersExport' => fn () => new UsersExport(),

            'CatalogExport[MeetingType]' => fn () => new CatalogExport(MeetingType::class),
            'CatalogExport[MeetingLocation]' => fn () => new CatalogExport(MeetingLocation::class),
            'CatalogExport[MeetingDocumentType]' => fn () => new CatalogExport(MeetingDocumentType::class),
            'CatalogExport[MeetingAttendeeGroup]' => fn () => new CatalogExport(MeetingAttendeeGroup::class),
            'MeetingExport' => fn () => new MeetingExport(),
            'MeetingAttendeeExport' => fn () => new MeetingAttendeeExport(),
            'MeetingAttendanceExport' => fn () => $meeting
                ? new MeetingAttendanceExport($meeting->id)
                : null,
            'MeetingDiscussionRegistrationExport[discussion]' => fn () => $meeting
                ? new MeetingDiscussionRegistrationExport($meeting->id, 'discussion')
                : null,
            'MeetingDiscussionRegistrationExport[question]' => fn () => $meeting
                ? new MeetingDiscussionRegistrationExport($meeting->id, 'question')
                : null,
            'MeetingVoteResponseDetailExport' => fn () => $topic
                ? new MeetingVoteResponseDetailExport($topic->id)
                : null,
            'MeetingVoteResponseSummaryExport' => fn () => $meeting
                ? new MeetingVoteResponseSummaryExport($meeting->id)
                : null,

            'DepartmentExport' => fn () => new DepartmentExport(),
            'DocumentsExport' => fn () => new DocumentsExport(),
            'ItemsExport' => fn () => new ItemsExport(),
            'LookupExport[TaskAssignmentType]' => fn () => new LookupExport(TaskAssignmentType::class),
            'LookupExport[TaskAssignmentItemType]' => fn () => new LookupExport(TaskAssignmentItemType::class),
        ];
    }

    private function extractRows(AbstractExcelExport $export): ?\Illuminate\Support\Collection
    {
        if ($export instanceof FromCollection) {
            $result = $export->collection();
            return $result instanceof \Illuminate\Support\Collection ? $result : collect($result);
        }
        if ($export instanceof FromArray) {
            return collect($export->array());
        }
        return null;
    }

    private function hasVietnameseChar(string $text): bool
    {
        return (bool) preg_match(
            '/[àáạảãâấầẩẫậăắằẳẵặèéẹẻẽêếềểễệìíịỉĩòóọỏõôốồổỗộơớờởỡợùúụủũưứừửữựỳýỵỷỹđÀÁẠẢÃÂẤẦẨẪẬĂẮẰẲẴẶÈÉẸẺẼÊẾỀỂỄỆÌÍỊỈĨÒÓỌỎÕÔỐỒỔỖỘƠỚỜỞỠỢÙÚỤỦŨƯỨỪỬỮỰỲÝỴỶỸĐ]/u',
            $text
        );
    }

    private function isAsciiWhitelisted(string $text): bool
    {
        $whitelist = ['STT', 'ID', 'URL', 'IP', 'Email', 'SMS', 'Google Maps URL'];

        return in_array($text, $whitelist, true);
    }

    private function readEnvDbName(): ?string
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return null;
        }
        foreach (file($envPath) as $line) {
            if (preg_match('/^DB_DATABASE\s*=\s*(.+)$/', trim($line), $m)) {
                return trim($m[1], "\"'");
            }
        }

        return null;
    }
}
