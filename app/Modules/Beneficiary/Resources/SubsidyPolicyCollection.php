<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SubsidyPolicyCollection extends ResourceCollection
{
    public $collects = SubsidyPolicyResource::class;
}
