<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-attachment cho thảo luận/chất vấn — pattern giống meeting_personal_note_attachments.
 *
 * Migrate data cũ: với mỗi meeting_discussion_registrations.media_id != null, tạo 1 row
 * trong table mới với file_name lấy từ media.file_name (filename gốc upload).
 * Field media_id trong bảng cha GIỮ NGUYÊN để FE/code cũ vẫn đọc được — sẽ drop ở
 * migration riêng sau khi FE migrate sang dùng attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_discussion_registration_attachments', function (Blueprint $table) {
            // Identifier MySQL max 64 ký tự — auto-name FK quá dài, phải dùng tên ngắn explicit.
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('meeting_discussion_registration_id');
            $table->unsignedBigInteger('media_id');
            $table->foreign('organization_id', 'meeting_dra_org_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('meeting_discussion_registration_id', 'meeting_dra_reg_fk')
                ->references('id')->on('meeting_discussion_registrations')->cascadeOnDelete();
            $table->foreign('media_id', 'meeting_dra_media_fk')
                ->references('id')->on('media')->cascadeOnDelete();
            // file_name: user-defined display name (vd "Slide trình chiếu"); default = media original filename.
            $table->string('file_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'meeting_discussion_registration_id', 'sort_order'], 'meeting_dra_org_reg_sort_idx');
        });

        // Copy data cũ: registrations có media_id → tạo attachment row tương ứng.
        $rows = DB::table('meeting_discussion_registrations as r')
            ->join('media as m', 'm.id', '=', 'r.media_id')
            ->whereNotNull('r.media_id')
            ->select('r.id as registration_id', 'r.organization_id', 'r.media_id', 'm.file_name', 'r.created_at', 'r.updated_at')
            ->get();

        foreach ($rows as $row) {
            DB::table('meeting_discussion_registration_attachments')->insert([
                'organization_id' => $row->organization_id,
                'meeting_discussion_registration_id' => $row->registration_id,
                'media_id' => $row->media_id,
                'file_name' => $row->file_name,
                'sort_order' => 0,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_discussion_registration_attachments');
    }
};
