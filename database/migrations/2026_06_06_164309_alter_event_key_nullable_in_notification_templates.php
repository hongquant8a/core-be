<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->string('event_key')->nullable()->comment('để tham khảo, không dùng trong unique')->change();
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->string('event_key')->nullable(false)->change();
        });
    }
};
