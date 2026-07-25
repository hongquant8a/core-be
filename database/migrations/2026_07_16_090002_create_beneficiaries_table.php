<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('household_id')->nullable()
                ->constrained('beneficiary_households')->nullOnDelete();

            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20);
            $table->string('id_number')->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('death_date')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'household_id']);
            $table->unique(['organization_id', 'id_number'], 'beneficiaries_org_id_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
