<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ResidentialAreaCollection extends ResourceCollection
{
    public $collects = ResidentialAreaResource::class;
}
