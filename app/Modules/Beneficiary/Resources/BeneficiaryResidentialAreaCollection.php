<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BeneficiaryResidentialAreaCollection extends ResourceCollection
{
    public $collects = BeneficiaryResidentialAreaResource::class;
}
