<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BeneficiaryTypeCollection extends ResourceCollection
{
    public $collects = BeneficiaryTypeResource::class;
}
