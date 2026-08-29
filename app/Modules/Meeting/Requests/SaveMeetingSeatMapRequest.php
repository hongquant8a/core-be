<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingSeatLayoutTypeEnum;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class SaveMeetingSeatMapRequest extends FormRequest
{
    /** Tham số config bắt buộc theo từng layout_type — khớp 4 kiểu sơ đồ ở generators.js. */
    private const REQUIRED_CONFIG_KEYS = [
        'theater' => ['rows', 'cols'],
        'presidium' => ['head', 'rows', 'cols'],
        'curved' => ['rows', 'cols'],
        'ushape' => ['head', 'side'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'layout_type' => ['required', MeetingSeatLayoutTypeEnum::rule()],
            'config' => ['required', 'array'],
            'config.rows' => ['nullable', 'integer', 'min:1', 'max:50'],
            'config.cols' => ['nullable', 'integer', 'min:1', 'max:50'],
            'config.head' => ['nullable', 'integer', 'min:1', 'max:50'],
            'config.side' => ['nullable', 'integer', 'min:1', 'max:50'],
            'canvas' => ['nullable', 'array'],
            'canvas.width' => ['nullable', 'integer', 'min:1'],
            'canvas.height' => ['nullable', 'integer', 'min:1'],
            'keep_assignments' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $layoutType = $this->input('layout_type');
            $required = self::REQUIRED_CONFIG_KEYS[$layoutType] ?? [];
            $config = (array) $this->input('config', []);

            foreach ($required as $key) {
                if (! isset($config[$key])) {
                    $validator->errors()->add("config.{$key}", "Trường config.{$key} là bắt buộc với kiểu sơ đồ \"{$layoutType}\".");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'numeric' => ':attribute phải là số.',
            'boolean' => ':attribute phải là giá trị đúng/sai.',
            'array' => ':attribute phải là mảng.',
            'max' => ':attribute không được vượt quá :max.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'in' => ':attribute không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'layout_type' => 'Kiểu sơ đồ',
            'config' => 'Tham số sơ đồ',
            'config.rows' => 'Số hàng',
            'config.cols' => 'Ghế mỗi hàng',
            'config.head' => 'Ghế chủ tọa/đầu bàn',
            'config.side' => 'Ghế mỗi cạnh',
            'canvas' => 'Kích thước canvas',
            'canvas.width' => 'Chiều rộng canvas',
            'canvas.height' => 'Chiều cao canvas',
            'keep_assignments' => 'Giữ chỗ đã xếp',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'layout_type' => ['description' => 'Kiểu sơ đồ: theater | presidium | curved | ushape.', 'example' => 'theater'],
            'config' => ['description' => 'Tham số sinh ghế theo kiểu sơ đồ (rows, cols, head, side — tối đa 50).', 'example' => ['rows' => 5, 'cols' => 8]],
            'canvas' => ['description' => 'Kích thước canvas tùy chỉnh — bỏ trống để BE tự tính.', 'example' => ['width' => 980, 'height' => 620]],
            'keep_assignments' => ['description' => 'Giữ đại biểu/cờ VIP đã gán ở ghế còn tồn tại sau khi sinh lại. Mặc định true.', 'example' => true],
        ];
    }
}
