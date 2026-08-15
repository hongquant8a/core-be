<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BeneficiaryTypeRelationCollection extends ResourceCollection
{
    public $collects = BeneficiaryTypeRelationResource::class;
}
