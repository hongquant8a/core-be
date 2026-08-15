<?php

namespace App\Modules\Beneficiary\Requests\Catalog;

class SaveResidentialAreaRequest extends SaveCatalogRequest
{
    protected function table(): string
    {
        return 'beneficiary_residential_areas';
    }

    protected function routeParam(): string
    {
        return 'residentialArea';
    }

    public function bodyParameters(): array
    {
        return array_merge(parent::bodyParameters(), [
            'name' => ['description' => 'Tên tổ dân phố/thôn. Duy nhất trong tổ chức.', 'example' => 'Tổ dân phố 5'],
        ]);
    }
}
