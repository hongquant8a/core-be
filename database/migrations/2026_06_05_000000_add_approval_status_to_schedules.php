<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('approval_status', 20)->nullable()->default(null)->after('status');
            $table->index(['organization_id', 'approval_status']);
        });

        // Migrate data cũ:
        // status=1 (PENDING)  → status=0 (DRAFT) + approval_status='pending'
        // status=2 (PUBLISHED)→ status=1 (PUBLISHED) + approval_status=null
        // status=3 (CANCELLED)→ status=0 (DRAFT) + approval_status=null
        DB::table('schedules')->where('status', 1)->update([
            'status'          => 0,
            'approval_status' => 'pending',
        ]);
        DB::table('schedules')->where('status', 2)->update([
            'status' => 1,
        ]);
        DB::table('schedules')->where('status', 3)->update([
            'status' => 0,
        ]);
    }

    public function down(): void
    {
        // Revert data: chỉ phục hồi giá trị status, approval_status sẽ bị xoá
        DB::table('schedules')->where('status', 0)->where('approval_status', 'pending')->update([
            'status' => 1,
        ]);
        DB::table('schedules')->where('status', 1)->update([
            'status' => 2,
        ]);
        // status=3 (CANCELLED) không thể khôi phục chính xác vì DRAFT và CANCELLED đều là 0

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'approval_status']);
            $table->dropColumn('approval_status');
        });
    }
};
