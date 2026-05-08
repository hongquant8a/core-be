<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Token điểm danh QR — UUID unique mỗi cuộc họp. FE generate QR encode token này
 * trong URL, đại biểu scan → FE call BE submit điểm danh sau khi auth.
 *
 * Backfill tự động cho meetings hiện có ngay sau khi add column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'checkin_token')) {
                $table->uuid('checkin_token')->nullable()->after('attendance_locked');
            }
        });

        // Backfill UUID cho meeting hiện có (token NULL).
        DB::table('meetings')->whereNull('checkin_token')->orderBy('id')->each(function ($row) {
            DB::table('meetings')->where('id', $row->id)->update([
                'checkin_token' => (string) Str::uuid(),
            ]);
        });

        // Sau backfill, set NOT NULL + unique để đảm bảo invariant.
        Schema::table('meetings', function (Blueprint $table) {
            $table->uuid('checkin_token')->nullable(false)->change();
            $table->unique('checkin_token');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meetings') || ! Schema::hasColumn('meetings', 'checkin_token')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique(['checkin_token']);
            $table->dropColumn('checkin_token');
        });
    }
};
