<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_visit_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            // Polymorphic subject: Beneficiary | BeneficiaryDependent | BeneficiaryHousehold
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->string('occasion', 50);
            $table->date('scheduled_date');
            $table->string('status', 20)->default('pending');
            $table->foreignId('assigned_to')->constrained('users');
            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'assigned_to', 'status'], 'beneficiary_visit_schedules_org_assignee_status_idx');
            $table->index(['subject_type', 'subject_id'], 'beneficiary_visit_schedules_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_visit_schedules');
    }
};
