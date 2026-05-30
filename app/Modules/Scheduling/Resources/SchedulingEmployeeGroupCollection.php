<?php

namespace App\Modules\Scheduling\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SchedulingEmployeeGroupCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($item) use ($request) {
            return (new SchedulingEmployeeGroupResource($item))->toArray($request);
        })->all();
    }
}
