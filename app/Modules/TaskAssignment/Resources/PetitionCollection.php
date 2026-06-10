<?php

namespace App\Modules\TaskAssignment\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PetitionCollection extends ResourceCollection
{
    public $collects = PetitionResource::class;
}
