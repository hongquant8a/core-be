<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class DocumentIssued implements ShouldDispatchAfterCommit
{
    public function __construct(public TaskAssignmentDocument $document) {}
}
