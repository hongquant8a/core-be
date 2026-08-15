<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Đối tượng của người có công — dạng D.
 */
class SaveBeneficiaryTypeRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('typeRelation') !== null;

        return [
            'beneficiary_type_id' => [
                $isUpdate ? 'sometimes' : 'required', 'integer',
                Rule::exists('beneficiary_types', 'id')
                    ->where('organization_id', getPermissionsTeamId())
                    ->where('status', 'active')
                    ->whereNull('deleted_at'),
            ],
            'is_primary' => ['sometimes', 'boolean'],

            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:10240'],

            // Vắng mặt = request không quản lý tệp → giữ nguyên toàn bộ tệp cũ.
            'sync_attachments' => ['sometimes', 'boolean'],
            'keep_media_ids' => ['sometimes', 'array'],
            'keep_media_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_type_id.required' => 'Vui lòng chọn loại đối tượng.',
            'beneficiary_type_id.integer' => 'Loại đối tượng không hợp lệ.',
            'beneficiary_type_id.exists' => 'Loại đối tượng không tồn tại hoặc đã ngừng sử dụng.',
            'is_primary.boolean' => 'Đối tượng chính phải là true hoặc false.',
            'attachments.array' => 'Tệp đính kèm phải là mảng.',
            'attachments.max' => 'Chỉ được đính kèm tối đa :max tệp.',
            'attachments.*.file' => 'Tệp đính kèm không hợp lệ.',
            'attachments.*.mimes' => 'Tệp đính kèm phải thuộc định dạng: pdf, jpg, jpeg, png, webp, doc, docx.',
            'attachments.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'sync_attachments.boolean' => 'Cờ đồng bộ tệp phải là true hoặc false.',
            'keep_media_ids.array' => 'Danh sách tệp giữ lại phải là mảng.',
            'keep_media_ids.*.integer' => 'ID tệp giữ lại phải là số nguyên.',
        ];
    }

    public function attributes(): array
    {
        return [
            'beneficiary_type_id' => 'loại đối tượng',
            'is_primary' => 'đối tượng chính',
            'attachments' => 'tệp đính kèm',
            'sync_attachments' => 'cờ đồng bộ tệp',
            'keep_media_ids' => 'danh sách tệp giữ lại',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'beneficiary_type_id' => ['description' => 'ID loại đối tượng (phải đang sử dụng).', 'example' => 3],
            'is_primary' => ['description' => 'Đánh dấu là đối tượng chính. Nhiều nhất một dòng trên mỗi hồ sơ.', 'example' => true],
            'attachments' => ['description' => 'Tệp đính kèm mới.', 'example' => []],
            'sync_attachments' => ['description' => 'Bật thì tệp không nằm trong keep_media_ids sẽ bị xoá.', 'example' => true],
            'keep_media_ids' => ['description' => 'ID các tệp cũ cần giữ lại.', 'example' => [7, 8]],
        ];
    }
}
