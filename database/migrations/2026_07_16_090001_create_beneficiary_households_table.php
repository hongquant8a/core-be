<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_households', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('residential_area_id')->nullable()
                ->constrained('beneficiary_residential_areas')->nullOnDelete();

            $table->string('head_name');
            $table->string('head_id_number')->nullable();
            $table->string('address');
            $table->string('phone')->nullable();
            $table->unsignedInteger('member_count')->default(0);
            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'residential_area_id'], 'beneficiary_households_org_area_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_households');
    }
};
