<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;

class DocumentIssued
{
    public function __construct(public TaskAssignmentDocument $document) {}
}
