<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DepartmentCollection extends ResourceCollection
{
    public $collects = DepartmentResource::class;
}
