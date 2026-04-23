<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TimelineCollection extends ResourceCollection
{
    public $collects = TimelineResource::class;
}
