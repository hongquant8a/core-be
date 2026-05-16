<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingPersonalNote;

/**
 * Policy item-level cho ghi chú cá nhân.
 *
 * Owner-only: chair/op KHÔNG xem được note của người khác — note là dữ liệu cá nhân.
 * Check meeting-level (list/create) qua MeetingPolicy `can:viewParticipant,meeting` /
 * `can:participate,meeting`. Service đã filter ownership theo user_id.
 */
class MeetingPersonalNotePolicy
{
    public function view(User $user, MeetingPersonalNote $note): bool
    {
        return $this->isOwner($user, $note);
    }

    public function update(User $user, MeetingPersonalNote $note): bool
    {
        return $this->isOwner($user, $note);
    }

    public function delete(User $user, MeetingPersonalNote $note): bool
    {
        return $this->isOwner($user, $note);
    }

    private function isOwner(User $user, MeetingPersonalNote $note): bool
    {
        return (int) $note->user_id === (int) $user->id;
    }
}
