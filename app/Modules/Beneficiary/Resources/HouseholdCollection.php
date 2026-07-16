<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class HouseholdCollection extends ResourceCollection
{
    public $collects = HouseholdResource::class;
}
