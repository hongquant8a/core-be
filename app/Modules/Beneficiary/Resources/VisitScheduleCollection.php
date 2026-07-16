<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class VisitScheduleCollection extends ResourceCollection
{
    public $collects = VisitScheduleResource::class;
}
