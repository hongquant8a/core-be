<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BeneficiaryRelationshipCollection extends ResourceCollection
{
    public $collects = BeneficiaryRelationshipResource::class;
}
