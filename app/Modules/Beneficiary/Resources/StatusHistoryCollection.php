<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class StatusHistoryCollection extends ResourceCollection
{
    public $collects = StatusHistoryResource::class;
}
