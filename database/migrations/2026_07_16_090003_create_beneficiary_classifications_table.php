<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->cascadeOnDelete();

            $table->string('type', 50);
            $table->string('decision_no');
            $table->date('decision_date');
            $table->string('issued_by');
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['beneficiary_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_classifications');
    }
};
