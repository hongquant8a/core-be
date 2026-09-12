<?php

namespace App\Modules\TaskAssignment\Requests;

class StoreExtensionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Hạn xin CỐ Ý không ràng buộc gì ngoài "là ngày hợp lệ" — không đòi
            // lớn hơn hạn cũ, không đòi lớn hơn hiện tại. Chốt ngày 12/09/2026:
            // người xin tự biết mình cần hạn nào, và mọi yêu cầu đều phải qua
            // người duyệt. Thêm luật ở form chỉ chặn nhầm trường hợp hợp lệ —
            // việc đã trễ, hoặc việc cần dời gấp trong vài ngày. Bảng lịch sử
            // chụp cả `current_end_at` lẫn `requested_end_at` nên hạn có bị rút
            // ngắn thì người duyệt vẫn nhìn ra.
            'requested_end_at' => 'required|date',
            'reason' => 'required|string|min:10|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'requested_end_at.required' => 'Vui lòng chọn thời hạn muốn dời tới.',
            'requested_end_at.date' => 'Thời hạn muốn dời tới không hợp lệ.',
            'reason.required' => 'Vui lòng nhập lý do xin gia hạn.',
            'reason.string' => 'Lý do phải là chuỗi ký tự.',
            'reason.min' => 'Lý do cần ít nhất :min ký tự để người duyệt hiểu được.',
            'reason.max' => 'Lý do không được vượt quá :max ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'requested_end_at' => 'Thời hạn muốn dời tới',
            'reason' => 'Lý do xin gia hạn',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'requested_end_at' => [
                'description' => 'Thời hạn muốn dời tới (Y-m-d H:i:s). Không ràng buộc phải sau hạn hiện tại.',
                'example' => '2026-10-15 17:00:00',
            ],
            'reason' => [
                'description' => 'Lý do xin gia hạn (10-2000 ký tự).',
                'example' => 'Đơn vị phối hợp chưa cung cấp số liệu quý III, cần thêm một tuần để tổng hợp.',
            ],
        ];
    }
}
