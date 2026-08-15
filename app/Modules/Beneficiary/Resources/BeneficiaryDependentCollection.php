<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BeneficiaryDependentCollection extends ResourceCollection
{
    public $collects = BeneficiaryDependentResource::class;
}
