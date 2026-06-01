<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_invitation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active'); // active | inactive
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('media_id');
            $table->index(['organization_id', 'status'], 'meeting_invitation_tpl_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_invitation_templates');
    }
};
