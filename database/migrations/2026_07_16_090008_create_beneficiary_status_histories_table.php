<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            // Polymorphic subject: Beneficiary | BeneficiaryDependent
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at');

            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'changed_at'], 'beneficiary_status_histories_subject_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_status_histories');
    }
};
