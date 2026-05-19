<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class NotificationLogCollection extends ResourceCollection
{
    public $collects = NotificationLogResource::class;

    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
