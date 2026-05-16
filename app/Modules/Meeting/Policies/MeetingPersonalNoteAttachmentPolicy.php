<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingPersonalNoteAttachment;

/**
 * Policy item-level cho attachment ghi chú cá nhân.
 *
 * Owner = user của note cha. Chair/op KHÔNG xem/sửa attachment người khác (private data).
 */
class MeetingPersonalNoteAttachmentPolicy
{
    public function update(User $user, MeetingPersonalNoteAttachment $att): bool
    {
        return $this->isOwner($user, $att);
    }

    public function delete(User $user, MeetingPersonalNoteAttachment $att): bool
    {
        return $this->isOwner($user, $att);
    }

    private function isOwner(User $user, MeetingPersonalNoteAttachment $att): bool
    {
        $note = $att->note;

        return $note && (int) $note->user_id === (int) $user->id;
    }
}
