<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_subsidy_policies', function (Blueprint $table) {
            $table->id();
            // NULL = catalog áp dụng toàn TP/quốc gia (không scope theo 1 phường/xã)
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            $table->string('beneficiary_type', 50)->nullable();
            $table->string('relationship_type', 50)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('unit', 50)->default('VND/tháng');
            $table->string('legal_basis');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'beneficiary_type', 'effective_from'], 'beneficiary_subsidy_policies_org_type_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_subsidy_policies');
    }
};
