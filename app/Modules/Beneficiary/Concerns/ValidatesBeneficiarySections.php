<?php

namespace App\Modules\Beneficiary\Concerns;

use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

/**
 * Validate 3 mảng con của hồ sơ người có công — dùng chung cho Store/Update.
 *
 * Chạy Validator lồng cho TỪNG phần tử thay vì rule dot-notation `xxx.*.field`: rule wildcard
 * trên mảng object làm `scribe:generate` vỡ khi build ví dụ curl cho TOÀN BỘ project
 * ("stdClass could not be converted to string"), không riêng endpoint này.
 *
 * Không nhận `id` trong phần tử: mảng gửi lên thay thế toàn bộ quan hệ (xóa hết rồi tạo lại),
 * nên `id` cũ không còn ý nghĩa — nhận im lặng rồi bỏ qua sẽ khiến FE tưởng đang cập nhật đúng dòng.
 */
trait ValidatesBeneficiarySections
{
    protected function validateSections(Validator $validator): void
    {
        $this->validateSection($validator, 'classifications', [
            'type' => ['required', BeneficiaryTypeEnum::rule()],
            'decision_no' => 'nullable|string|max:255',
            'decision_date' => 'nullable|date',
            'issued_by' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
        ], [
            'type.required' => 'Loại đối tượng không được để trống.',
            'type.in' => 'Loại đối tượng không hợp lệ.',
            'decision_no.max' => 'Số quyết định không được vượt quá 255 ký tự.',
            'decision_date.date' => 'Ngày quyết định không hợp lệ.',
            'issued_by.max' => 'Cơ quan ban hành không được vượt quá 255 ký tự.',
            'is_primary.boolean' => 'Loại chính phải là true hoặc false.',
        ], [
            'type' => 'Loại đối tượng',
            'decision_no' => 'Số quyết định',
            'decision_date' => 'Ngày quyết định',
            'issued_by' => 'Cơ quan ban hành',
            'is_primary' => 'Loại chính',
        ]);

        $this->validateSection($validator, 'dependents', [
            'dependent_id' => 'required|integer|exists:beneficiary_dependents,id',
            'relationship_type' => ['required', DependentRelationshipEnum::rule()],
            'note' => 'nullable|string',
        ], [
            'dependent_id.required' => 'Thân nhân không được để trống.',
            'dependent_id.exists' => 'Thân nhân không tồn tại.',
            'relationship_type.required' => 'Quan hệ với người có công không được để trống.',
            'relationship_type.in' => 'Quan hệ không hợp lệ.',
        ], [
            'dependent_id' => 'Thân nhân',
            'relationship_type' => 'Quan hệ',
            'note' => 'Ghi chú',
        ]);

        $this->validateSection($validator, 'documents', [
            'name' => 'required|string|max:255',
            'note' => 'nullable|string',
        ], [
            'name.required' => 'Tên tài liệu không được để trống.',
            'name.max' => 'Tên tài liệu không được vượt quá 255 ký tự.',
        ], [
            'name' => 'Tên tài liệu',
            'note' => 'Ghi chú',
        ]);

        $this->validateSinglePrimary($validator);
    }

    private function validateSection(Validator $validator, string $key, array $rules, array $messages, array $attributes): void
    {
        $rows = $this->input($key, []);

        if (! is_array($rows)) {
            return;
        }

        $rules = [...$rules, 'id' => 'prohibited'];
        $messages = [...$messages, 'id.prohibited' => 'Mảng này thay thế toàn bộ danh sách nên không nhận `id`.'];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $validator->errors()->add("{$key}.{$index}", 'Dữ liệu không hợp lệ.');

                continue;
            }

            $nested = ValidatorFacade::make($row, $rules, $messages, $attributes);

            foreach ($nested->errors()->messages() as $field => $fieldMessages) {
                foreach ($fieldMessages as $message) {
                    $validator->errors()->add("{$key}.{$index}.{$field}", $message);
                }
            }
        }
    }

    /** Mảng gửi lên là toàn bộ danh sách nên soát ở đây là đủ, Service không cần enforce lại. */
    private function validateSinglePrimary(Validator $validator): void
    {
        $rows = $this->input('classifications', []);

        if (! is_array($rows)) {
            return;
        }

        $primaryCount = collect($rows)
            ->filter(fn ($row) => is_array($row) && (bool) ($row['is_primary'] ?? false))
            ->count();

        if ($primaryCount > 1) {
            $validator->errors()->add('classifications', 'Chỉ được chọn tối đa 1 loại đối tượng là loại chính (is_primary = true).');
        }
    }
}
