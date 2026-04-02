<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class LookupCollection extends ResourceCollection
{
    public $collects = LookupResource::class;
}
