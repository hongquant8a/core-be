<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * meeting_invitations giờ phục vụ 3 loại recipient (mutually exclusive 1 trong 3):
 *  - meeting_participant_id : đại biểu (đã có)
 *  - meeting_attendee_id    : chair/operator gửi trực tiếp qua attendee (đã có)
 *  - meeting_guest_id       : khách mời (MỚI)
 *
 * Khi gửi thư mời, listener resolve contact theo loại:
 *  - participant/attendee → resolve user_id → dispatch qua channels của user
 *  - guest                → KHÔNG có user, dispatch raw email + SMS theo info guest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_invitations', 'meeting_guest_id')) {
                $table->foreignId('meeting_guest_id')
                    ->nullable()
                    ->after('meeting_attendee_id')
                    ->constrained('meeting_guests')
                    ->cascadeOnDelete();
                $table->index(['meeting_id', 'meeting_guest_id'], 'meeting_invitations_mtg_guest_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meeting_invitations', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_invitations', 'meeting_guest_id')) {
                // Drop FK trước (vì FK đang dùng index) → sau đó drop index → sau đó drop column.
                $table->dropForeign(['meeting_guest_id']);
                $table->dropIndex('meeting_invitations_mtg_guest_idx');
                $table->dropColumn('meeting_guest_id');
            }
        });
    }
};
