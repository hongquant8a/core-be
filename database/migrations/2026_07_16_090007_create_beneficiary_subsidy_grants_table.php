<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_subsidy_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            // Polymorphic subject: Beneficiary | BeneficiaryDependent
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->foreignId('beneficiary_subsidy_policy_id')->constrained('beneficiary_subsidy_policies');
            $table->decimal('amount', 15, 2);
            $table->date('granted_from');
            $table->date('granted_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('termination_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status'], 'beneficiary_subsidy_grants_subject_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_subsidy_grants');
    }
};
