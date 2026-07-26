<?php

namespace App\Modules\Beneficiary\Concerns;

/**
 * `dependents`/`documents` ghi vào bảng có permission RIÊNG (`beneficiary-dependents.*`,
 * `beneficiary-documents.*`), trong khi route `beneficiaries.store/update` chỉ kiểm quyền của
 * chính hồ sơ. Không soát thêm ở đây thì payload lồng thành đường vòng qua hệ phân quyền: cán bộ
 * chỉ có quyền sửa hồ sơ vẫn tạo/xóa được tài liệu và quan hệ thân nhân.
 *
 * Gửi khóa nào = THAY THẾ toàn bộ mảng đó → cần quyền tạo (nếu mảng khác rỗng) và quyền xóa
 * (nếu hồ sơ đang có dòng cũ sẽ bị xóa).
 *
 * `classifications` KHÔNG cần soát — nó không có route/permission riêng, là phần thân của hồ sơ.
 *
 * Chạy ở `authorize()` nên phải chịu được input chưa qua validate (phần tử không phải mảng…).
 */
trait AuthorizesBeneficiarySections
{
    protected function authorizeSections(): bool
    {
        return $this->sectionAllowed('documents', 'documents', 'beneficiary-documents.store', 'beneficiary-documents.destroy')
            && $this->sectionAllowed('dependents', 'dependentRelations', 'beneficiary-dependents.storeRelation', 'beneficiary-dependents.destroyRelation');
    }

    private function sectionAllowed(string $key, string $relation, string $createAbility, string $deleteAbility): bool
    {
        if (! $this->has($key)) {
            return true;
        }

        $rows = $this->input($key);
        $user = $this->user();

        if (is_array($rows) && $rows !== [] && ! $user?->can($createAbility)) {
            return false;
        }

        // Chỉ luồng update mới có dòng cũ để xóa.
        $beneficiary = $this->route('beneficiary');

        if ($beneficiary && $beneficiary->{$relation}()->exists() && ! $user?->can($deleteAbility)) {
            return false;
        }

        return true;
    }
}
