<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_scheduling_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->boolean('executive_approval_required')->default(false);
            $table->boolean('office_approval_required')->default(false);
            $table->json('executive_approver_roles')->nullable(); // list of roles that can approve
            $table->json('office_approver_roles')->nullable(); // list of roles that can approve
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_scheduling_settings');
    }
};
