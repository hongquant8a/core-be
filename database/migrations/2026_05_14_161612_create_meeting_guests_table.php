<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh bạ khách mời PER ORG — reusable contact (giống meeting_attendees).
 * Khách mời KHÔNG có user account — chỉ để gửi thư mời (email/SMS) khi meeting publish.
 * Quyền hạn xem meeting = giống guest công khai (chỉ meeting is_public + status=published).
 *
 * Khi tạo/sửa meeting, admin gửi list `guest_ids: int[]` → BE tạo
 * meeting_invitations entries với meeting_guest_id = từng id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_guests');
    }
};
