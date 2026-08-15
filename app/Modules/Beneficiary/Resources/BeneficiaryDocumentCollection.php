<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BeneficiaryDocumentCollection extends ResourceCollection
{
    public $collects = BeneficiaryDocumentResource::class;
}
