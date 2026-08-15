<?php

namespace App\Modules\Beneficiary\Requests\Catalog;

class SaveBeneficiaryTypeRequest extends SaveCatalogRequest
{
    protected function table(): string
    {
        return 'beneficiary_types';
    }

    protected function routeParam(): string
    {
        return 'beneficiaryType';
    }

    public function bodyParameters(): array
    {
        return array_merge(parent::bodyParameters(), [
            'name' => ['description' => 'Tên loại đối tượng. Duy nhất trong tổ chức.', 'example' => 'Thương binh'],
        ]);
    }
}
