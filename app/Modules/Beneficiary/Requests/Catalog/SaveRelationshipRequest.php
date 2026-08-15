<?php

namespace App\Modules\Beneficiary\Requests\Catalog;

class SaveRelationshipRequest extends SaveCatalogRequest
{
    protected function table(): string
    {
        return 'beneficiary_relationships';
    }

    protected function routeParam(): string
    {
        return 'relationship';
    }

    public function bodyParameters(): array
    {
        return array_merge(parent::bodyParameters(), [
            'name' => ['description' => 'Tên mối quan hệ. Duy nhất trong tổ chức.', 'example' => 'Con'],
        ]);
    }
}
