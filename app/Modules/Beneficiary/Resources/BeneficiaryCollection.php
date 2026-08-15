<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BeneficiaryCollection extends ResourceCollection
{
    public $collects = BeneficiaryResource::class;
}
