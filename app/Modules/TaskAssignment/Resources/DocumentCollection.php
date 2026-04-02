<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DocumentCollection extends ResourceCollection
{
    public $collects = DocumentResource::class;
}
