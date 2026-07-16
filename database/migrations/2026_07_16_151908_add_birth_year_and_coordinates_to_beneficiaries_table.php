<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            // Text tự do — nhiều người có công (thời chiến) không có đủ thông tin ngày/tháng sinh,
            // chỉ nhớ được năm hoặc năm ước lượng (VD: "1950", "khoảng 1948"). date_of_birth vẫn
            // giữ nguyên cho trường hợp biết đầy đủ ngày tháng năm.
            $table->string('birth_year', 20)->nullable()->after('date_of_birth');

            // Phục vụ tra cứu bản đồ — độc lập với địa chỉ hộ (Beneficiary có thể ở địa chỉ khác hộ).
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn(['birth_year', 'latitude', 'longitude']);
        });
    }
};
