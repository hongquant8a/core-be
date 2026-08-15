<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\GenderEnum;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint gộp: bản chính + ba danh sách con trong một request.
 *
 * Kế thừa `SaveBeneficiaryRequest` để rule của bản chính chỉ tồn tại ở MỘT chỗ — sửa rule
 * `full_name` mà quên chỗ còn lại là kiểu lỗi im lặng nhất.
 */
class SaveFullBeneficiaryRequest extends SaveBeneficiaryRequest
{
    /**
     * Trần tổng tệp mỗi request. Phải THẤP HƠN `max_file_uploads` của php.ini (mặc định
     * 100) — bằng nhau thì phần dư bị PHP cắt im lặng trước khi validate nhìn thấy.
     */
    private const MAX_TOTAL_FILES = 90;

    /** Field gửi dưới dạng chuỗi JSON, decode ra mảng. */
    private const JSON_FIELDS = ['type_relations', 'dependents', 'documents'];

    /**
     * Vì sao KHÔNG gửi mảng lồng qua FormData:
     *
     *   1. `max_input_vars` (mặc định 1000) cắt phần ĐUÔI payload và không báo lỗi. Phần bị
     *      cắt có thể là vài phần tử cuối của `keep_media_ids[]` — số dòng vẫn khớp,
     *      validate vẫn pass, nhưng những media id bị cắt rơi vào danh sách xoá và BỊ XOÁ
     *      VĨNH VIỄN khỏi đĩa. Đếm số dòng không bắt được trường hợp này.
     *   2. JSON chiếm đúng 1 input var mỗi mảng — không còn gì để cắt.
     *   3. JSON phân biệt được `[]` (xoá hết) với vắng mặt (không quản lý) — không cần thêm
     *      cờ `sync_*` ở cấp danh sách.
     *
     * File KHÔNG đi qua JSON: gửi phẳng theo `type_relations_files[i][]`, khớp với dòng thứ
     * i của mảng đã decode.
     */
    protected function prepareForValidation(): void
    {
        foreach (self::JSON_FIELDS as $key) {
            if (! $this->has("{$key}_json")) {
                continue;
            }

            $rows = json_decode((string) $this->input("{$key}_json"), true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($rows)) {
                throw ValidationException::withMessages([
                    "{$key}_json" => "Dữ liệu {$key} không phải JSON hợp lệ.",
                ]);
            }

            $this->merge([$key => $rows]);
        }
    }

    public function rules(): array
    {
        $fileRule = ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'];

        return array_merge(parent::rules(), [
            // Bản chính khi cập nhật BẮT BUỘC có lock_version — form trọn gói là chỗ dễ ghi
            // đè nhau nhất, không có token thì optimistic lock vô hiệu.
            'lock_version' => $this->route('beneficiary')?->id ? ['required', 'string'] : ['nullable', 'string'],

            // --- Đối tượng: dạng D (n–n có thuộc tính, có tệp) ---------------
            'type_relations' => ['nullable', 'array', 'max:50'],
            'type_relations.*.id' => ['nullable', 'integer'],
            'type_relations.*.beneficiary_type_id' => [
                'required_with:type_relations', 'integer', $this->activeCatalogRule('beneficiary_types'),
            ],
            'type_relations.*.is_primary' => ['sometimes', 'boolean'],
            'type_relations.*.sync_attachments' => ['sometimes', 'boolean'],
            'type_relations.*.keep_media_ids' => ['sometimes', 'array'],
            'type_relations.*.keep_media_ids.*' => ['integer'],
            'type_relations_files' => ['sometimes', 'array'],
            'type_relations_files.*' => ['array', 'max:10'],
            'type_relations_files.*.*' => $fileRule,

            // --- Thân nhân: dạng B (1–n không tệp) --------------------------
            'dependents' => ['nullable', 'array', 'max:50'],
            'dependents.*.id' => ['nullable', 'integer'],
            'dependents.*.full_name' => ['required_with:dependents', 'string', 'max:255'],
            'dependents.*.birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'dependents.*.birth_year' => ['nullable', 'integer', 'digits:4', 'min:1900', 'max:'.now()->year],
            'dependents.*.gender' => ['nullable', GenderEnum::rule()],
            // KHÔNG unique: một người là thân nhân của hai hồ sơ thì đúng là hai dòng cùng
            // CCCD — hệ quả có chủ đích của việc chọn 1–n thay vì n–n.
            'dependents.*.id_number' => ['nullable', 'string', 'max:20'],
            'dependents.*.phone' => ['nullable', 'string', 'max:20'],
            'dependents.*.residential_area_id' => [
                'nullable', 'integer', $this->activeCatalogRule('beneficiary_residential_areas'),
            ],
            'dependents.*.address' => ['nullable', 'string', 'max:500'],
            'dependents.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'dependents.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'dependents.*.note' => ['nullable', 'string', 'max:5000'],
            'dependents.*.relationship_id' => [
                'nullable', 'integer', $this->activeCatalogRule('beneficiary_relationships'),
            ],
            'dependents.*.is_primary' => ['sometimes', 'boolean'],

            // --- Tài liệu: dạng A (1–n có tệp) ------------------------------
            'documents' => ['nullable', 'array', 'max:50'],
            'documents.*.id' => ['nullable', 'integer'],
            'documents.*.name' => ['required_with:documents', 'string', 'max:255'],
            'documents.*.note' => ['nullable', 'string', 'max:5000'],
            'documents.*.sync_attachments' => ['sometimes', 'boolean'],
            'documents.*.keep_media_ids' => ['sometimes', 'array'],
            'documents.*.keep_media_ids.*' => ['integer'],
            'documents_files' => ['sometimes', 'array'],
            'documents_files.*' => ['array', 'max:10'],
            'documents_files.*.*' => $fileRule,
        ]);
    }

    public function withValidator($validator): void
    {
        parent::withValidator($validator);

        $validator->after(function ($validator) {
            // UNIQUE(beneficiary_id, beneficiary_type_id): trùng loại đối tượng trong cùng
            // payload sẽ ném SQLSTATE 23000 giữa transaction. Bắt ở đây để trả 422 có nghĩa.
            $typeIds = array_filter(array_column($this->input('type_relations', []), 'beneficiary_type_id'));

            if (count($typeIds) !== count(array_unique($typeIds))) {
                $validator->errors()->add('type_relations', 'Danh sách loại đối tượng bị trùng.');
            }

            // Trần tổng tệp: rule cho phép 50 dòng × 10 tệp = 500, vượt xa max_file_uploads.
            // Không chặn ở đây thì PHP cắt im lặng phần dư.
            $total = collect($this->allFiles())->flatten(2)->count();

            if ($total > self::MAX_TOTAL_FILES) {
                $validator->errors()->add(
                    'files',
                    'Tổng số tệp trong một lần lưu không được vượt quá '.self::MAX_TOTAL_FILES.'.'
                );
            }
        });
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'lock_version.required' => 'Thiếu phiên bản bản ghi. Vui lòng tải lại trang.',
            'type_relations.max' => 'Không được vượt quá :max loại đối tượng.',
            'type_relations.*.beneficiary_type_id.required_with' => 'Vui lòng chọn loại đối tượng.',
            'type_relations.*.beneficiary_type_id.exists' => 'Loại đối tượng không tồn tại hoặc đã ngừng sử dụng.',
            'dependents.max' => 'Không được vượt quá :max thân nhân.',
            'dependents.*.full_name.required_with' => 'Vui lòng nhập họ và tên thân nhân.',
            'dependents.*.relationship_id.exists' => 'Mối quan hệ không tồn tại hoặc đã ngừng sử dụng.',
            'dependents.*.residential_area_id.exists' => 'Tổ dân phố/Thôn không tồn tại hoặc đã ngừng sử dụng.',
            'documents.max' => 'Không được vượt quá :max tài liệu.',
            'documents.*.name.required_with' => 'Vui lòng nhập tên tài liệu.',
            'type_relations_files.*.max' => 'Mỗi loại đối tượng chỉ đính kèm tối đa :max tệp.',
            'type_relations_files.*.*.mimes' => 'Tệp đính kèm phải thuộc định dạng: pdf, jpg, jpeg, png, webp, doc, docx, xls, xlsx.',
            'type_relations_files.*.*.max' => 'Mỗi tệp đính kèm không được vượt quá 10MB.',
            'documents_files.*.max' => 'Mỗi tài liệu chỉ đính kèm tối đa :max tệp.',
            'documents_files.*.*.mimes' => 'Tệp đính kèm phải thuộc định dạng: pdf, jpg, jpeg, png, webp, doc, docx, xls, xlsx.',
            'documents_files.*.*.max' => 'Mỗi tệp đính kèm không được vượt quá 10MB.',
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'type_relations' => 'danh sách đối tượng',
            'dependents' => 'danh sách thân nhân',
            'documents' => 'danh sách tài liệu',
        ]);
    }

    public function bodyParameters(): array
    {
        return array_merge(parent::bodyParameters(), [
            'type_relations_json' => [
                'description' => 'Danh sách đối tượng dưới dạng chuỗi JSON. Mảng rỗng "[]" = xoá hết; vắng mặt = không quản lý.',
                'example' => '[{"id":null,"beneficiary_type_id":3,"is_primary":true,"sync_attachments":true,"keep_media_ids":[]}]',
            ],
            'dependents_json' => [
                'description' => 'Danh sách thân nhân dưới dạng chuỗi JSON.',
                'example' => '[{"id":null,"full_name":"Trần Thị B","relationship_id":2,"is_primary":true}]',
            ],
            'documents_json' => [
                'description' => 'Danh sách tài liệu dưới dạng chuỗi JSON.',
                'example' => '[{"id":null,"name":"Quyết định trợ cấp","sync_attachments":true,"keep_media_ids":[]}]',
            ],
        ]);
    }
}
