<?php

namespace App\Modules\Scheduling\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SchedulingEmployeeCollection extends ResourceCollection
{
    public $collects = SchedulingEmployeeResource::class;
}
