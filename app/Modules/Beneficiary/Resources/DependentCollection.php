<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DependentCollection extends ResourceCollection
{
    public $collects = DependentResource::class;
}
