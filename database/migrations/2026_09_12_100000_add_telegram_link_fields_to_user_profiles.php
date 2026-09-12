<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Index: webhook Telegram chỉ gửi chat_id, phải tra ngược ra user ở mỗi update.
            $table->index('telegram_chat_id', 'user_profiles_telegram_chat_id_idx');
            // Token dùng một lần cho deep link t.me/<bot>?start=<token>.
            // Payload start của Telegram tối đa 64 ký tự nên token phải ngắn hơn giới hạn đó.
            $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_chat_id');
            $table->timestamp('telegram_token_expires_at')->nullable()->after('telegram_link_token');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropIndex('user_profiles_telegram_chat_id_idx');
            $table->dropColumn(['telegram_link_token', 'telegram_token_expires_at', 'telegram_linked_at']);
        });
    }
};
