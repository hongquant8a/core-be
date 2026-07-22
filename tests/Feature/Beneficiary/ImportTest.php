<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Imports\BeneficiaryImport;
use App\Modules\Beneficiary\Imports\DependentImport;
use App\Modules\Beneficiary\Imports\HouseholdImport;
use App\Modules\Beneficiary\Imports\ResidentialAreaImport;
use App\Modules\Beneficiary\Imports\SubsidyPolicyImport;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\ResidentialArea;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Core\Exports\ImportTemplateExport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->admin = User::factory()->create(['name' => 'Admin User']);

        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Super Admin');
        // actingAs guard mặc định để auth()->id() trong Import::model() trả về admin.
        $this->actingAs($this->admin);
    }

    /** Tạo file .xlsx tạm từ [headings, rows] rồi trả về UploadedFile như controller nhận. */
    private function makeXlsx(array $headings, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headings, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'imp_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import.xlsx', null, null, true);
    }

    public function test_dependent_import_full_fields_with_required_marker_and_normalization(): void
    {
        $household = Household::create([
            'organization_id' => $this->orgA->id,
            'household_code' => 'HGD-IMP-1',
            'head_name' => 'Chủ hộ',
        ]);

        // Header có dấu * (cột bắt buộc) + nhãn tiếng Việt cho enum/boolean/ngày d/m/Y.
        $file = $this->makeXlsx(
            ['Họ tên *', 'Giới tính *', 'Ngày sinh', 'Mã hộ', 'Tình trạng sống', 'Ngày mất', 'Tình trạng điều kiện hưởng', 'Ghi chú'],
            [['Lê Thị C', 'Nữ', '01/03/2010', 'HGD-IMP-1', 'Đã mất', '10/05/2024', 'Đang đi học', 'ghi chú test']],
        );

        Excel::import(new DependentImport, $file);

        $d = Dependent::where('full_name', 'Lê Thị C')->firstOrFail();
        $this->assertSame('female', $d->gender);
        $this->assertSame('2010-03-01', $d->date_of_birth->format('Y-m-d'));
        $this->assertSame($household->id, $d->household_id);
        $this->assertFalse($d->is_alive);
        $this->assertSame('2024-05-10', $d->death_date->format('Y-m-d'));
        $this->assertSame('studying', $d->eligibility_status);
        $this->assertSame('ghi chú test', $d->note);
    }

    public function test_residential_area_import_includes_code_column(): void
    {
        $file = $this->makeXlsx(
            ['Tên tổ dân phố *', 'Mã'],
            [['Tổ 9', 'TDP-009']],
        );

        Excel::import(new ResidentialAreaImport, $file);

        $ra = ResidentialArea::where('name', 'Tổ 9')->firstOrFail();
        $this->assertSame('TDP-009', $ra->code);
    }

    public function test_subsidy_policy_import_full_fields_with_enum_label(): void
    {
        $file = $this->makeXlsx(
            ['Loại đối tượng', 'Quan hệ', 'Mức trợ cấp *', 'Đơn vị', 'Căn cứ pháp lý *', 'Ngày hiệu lực *', 'Ngày hết hiệu lực'],
            [['Thương binh, người hưởng chính sách như thương binh', '', '3500000', 'VND/tháng', 'Nghị định 75/2021/NĐ-CP', '01/07/2021', '31/12/2025']],
        );

        Excel::import(new SubsidyPolicyImport, $file);

        $p = SubsidyPolicy::where('legal_basis', 'Nghị định 75/2021/NĐ-CP')->firstOrFail();
        $this->assertSame('war_invalid', $p->beneficiary_type);
        $this->assertSame('3500000.00', (string) $p->amount);
        $this->assertSame('VND/tháng', $p->unit);
        $this->assertSame('2021-07-01', $p->effective_from->format('Y-m-d'));
        $this->assertSame('2025-12-31', $p->effective_to->format('Y-m-d'));
    }

    public function test_beneficiary_import_still_maps_fields_after_refactor(): void
    {
        Household::create([
            'organization_id' => $this->orgA->id,
            'household_code' => 'HGD-IMP-1',
            'head_name' => 'Chủ hộ',
        ]);

        $file = $this->makeXlsx(
            ['Họ tên *', 'Giới tính *', 'Ngày sinh', 'Mã hộ', 'Trạng thái'],
            [['Trần Văn X', 'Nam', '20/05/1950', 'HGD-IMP-1', 'Đang hưởng']],
        );

        Excel::import(new BeneficiaryImport, $file);

        $b = Beneficiary::where('full_name', 'Trần Văn X')->firstOrFail();
        $this->assertSame('male', $b->gender);
        $this->assertSame('1950-05-20', $b->date_of_birth->format('Y-m-d'));
        $this->assertNotNull($b->household_id);
        $this->assertSame('active', $b->status);
    }

    public function test_household_import_still_maps_fields_after_refactor(): void
    {
        $file = $this->makeXlsx(
            ['Chủ hộ *', 'Mã hộ', 'Địa chỉ', 'Số thành viên'],
            [['Nguyễn Văn H', 'HGD-NEW', '12 Trần Phú', '4']],
        );

        Excel::import(new HouseholdImport, $file);

        $h = Household::where('head_name', 'Nguyễn Văn H')->firstOrFail();
        $this->assertSame('HGD-NEW', $h->household_code);
        $this->assertSame('12 Trần Phú', $h->address);
        $this->assertSame(4, $h->member_count);
    }

    public function test_import_skips_invalid_row_and_returns_failures(): void
    {
        // 1 dòng hợp lệ + 1 dòng thiếu Giới tính (cột bắt buộc) → dòng lỗi bị bỏ qua, dòng còn lại vẫn import.
        $file = $this->makeXlsx(
            ['Họ tên *', 'Giới tính *'],
            [
                ['Người Hợp Lệ', 'Nam'],
                ['Người Thiếu Giới Tính', ''],
            ],
        );

        $failures = app(\App\Modules\Beneficiary\Services\BeneficiaryService::class)->import($file);

        // Dòng hợp lệ được tạo; dòng lỗi thì không.
        $this->assertNotNull(Beneficiary::where('full_name', 'Người Hợp Lệ')->first());
        $this->assertNull(Beneficiary::where('full_name', 'Người Thiếu Giới Tính')->first());

        // Trả về đúng 1 lỗi kèm số dòng Excel (3 = header 1 + hợp lệ 2 + lỗi 3), cột và thông báo.
        $this->assertCount(1, $failures);
        $failure = $failures->first();
        $this->assertSame(3, $failure->row());
        $this->assertNotEmpty($failure->attribute());
        $this->assertStringContainsString('Giới tính', implode(' ', $failure->errors()));
    }

    public function test_import_all_valid_returns_no_failures(): void
    {
        $file = $this->makeXlsx(
            ['Tên tổ dân phố *', 'Mã'],
            [['Tổ 1', 'TDP-001'], ['Tổ 2', 'TDP-002']],
        );

        $failures = app(\App\Modules\Beneficiary\Services\ResidentialAreaService::class)->import($file);

        $this->assertCount(0, $failures);
        $this->assertSame(2, ResidentialArea::whereIn('name', ['Tổ 1', 'Tổ 2'])->count());
    }

    /** @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet */
    private function renderTemplate(ImportTemplateExport $export)
    {
        $binary = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'tpl_').'.xlsx';
        file_put_contents($path, $binary);

        return IOFactory::load($path)->getActiveSheet();
    }

    public function test_dependent_template_has_required_marker_and_enum_dropdown(): void
    {
        $sheet = $this->renderTemplate(new ImportTemplateExport(
            DependentImport::TEMPLATE_LABELS,
            DependentImport::TEMPLATE_EXAMPLES,
            DependentImport::REQUIRED_KEYS,
            DependentImport::templateNotes(),
            DependentImport::templateOptions(),
        ));

        // Header cột bắt buộc có dấu *; cột không bắt buộc để trần.
        $this->assertSame('Họ tên *', $sheet->getCell('A1')->getValue());
        $this->assertSame('Giới tính *', $sheet->getCell('C1')->getValue());
        $this->assertSame('Ngày sinh', $sheet->getCell('B1')->getValue());

        // Cột Giới tính (C) có dropdown chọn nhanh + prompt liệt kê giá trị (male (Nam), ...).
        // Reader chuẩn hóa key range "C2:C1001" → "C2" khi đọc lại (file thật vẫn áp cả dải).
        $dv = $this->dataValidationAt($sheet, 'C');
        $this->assertNotNull($dv, 'Cột Giới tính phải có dropdown.');
        $this->assertStringContainsString('male', $dv->getFormula1());
        $this->assertStringContainsString('female', $dv->getFormula1());
        $this->assertStringContainsString('male (Nam)', $dv->getPrompt());

        // Cột dropdown vẫn có comment ở header (bổ trợ) liệt kê đầy đủ giá trị.
        $genderNote = (string) $sheet->getComment('C1')->getText();
        $this->assertStringContainsString('male (Nam)', $genderNote);
        $this->assertStringContainsString('other (Khác)', $genderNote);
    }

    public function test_subsidy_policy_template_has_enum_dropdowns(): void
    {
        $sheet = $this->renderTemplate(new ImportTemplateExport(
            SubsidyPolicyImport::TEMPLATE_LABELS,
            SubsidyPolicyImport::TEMPLATE_EXAMPLES,
            SubsidyPolicyImport::REQUIRED_KEYS,
            SubsidyPolicyImport::templateNotes(),
            SubsidyPolicyImport::templateOptions(),
        ));

        // Loại đối tượng (cột A) — 12 giá trị, CSV ≤ 255 nên vẫn dựng được dropdown.
        $typeDv = $this->dataValidationAt($sheet, 'A');
        $this->assertNotNull($typeDv, 'Cột Loại đối tượng phải có dropdown.');
        $this->assertStringContainsString('war_invalid', $typeDv->getFormula1());

        // Quan hệ (cột B) — có dropdown.
        $relDv = $this->dataValidationAt($sheet, 'B');
        $this->assertNotNull($relDv, 'Cột Quan hệ phải có dropdown.');
        $this->assertStringContainsString('spouse', $relDv->getFormula1());
    }

    /** Lấy DataValidation áp cho cột $col (dò cả key range lẫn key ô góc trên sau khi reader chuẩn hóa). */
    private function dataValidationAt($sheet, string $col)
    {
        foreach ($sheet->getDataValidationCollection() as $range => $dv) {
            if (str_starts_with($range, $col.'2')) {
                return $dv;
            }
        }

        return null;
    }
}
