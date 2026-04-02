<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ItemCollection extends ResourceCollection
{
    public $collects = ItemResource::class;
}
