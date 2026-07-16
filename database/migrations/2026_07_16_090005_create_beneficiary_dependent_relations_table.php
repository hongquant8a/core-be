<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_dependent_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->cascadeOnDelete();
            $table->foreignId('dependent_id')->constrained('beneficiary_dependents')->cascadeOnDelete();

            $table->string('relationship_type', 50);
            $table->date('eligible_from');
            $table->date('eligible_until')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['beneficiary_id', 'dependent_id'], 'beneficiary_dependent_relations_pair_unique');
            $table->index(['dependent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_dependent_relations');
    }
};
