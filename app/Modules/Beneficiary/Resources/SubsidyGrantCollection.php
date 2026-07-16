<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SubsidyGrantCollection extends ResourceCollection
{
    public $collects = SubsidyGrantResource::class;
}
