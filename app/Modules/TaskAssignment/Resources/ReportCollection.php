<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ReportCollection extends ResourceCollection
{
    public $collects = ReportResource::class;
}
