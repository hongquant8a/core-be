<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Insert profile row cho user chưa có (kèm copy phone)
        DB::statement("
            INSERT INTO user_profiles (user_id, phone, created_at, updated_at)
            SELECT id, phone, NOW(), NOW() FROM users
            WHERE id NOT IN (SELECT user_id FROM user_profiles)
        ");

        // 2. Drop column phone khỏi users
        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 20)->nullable()->after('user_name');
            });
        }
        DB::statement('UPDATE users u JOIN user_profiles p ON p.user_id=u.id SET u.phone=p.phone');
    }
};
