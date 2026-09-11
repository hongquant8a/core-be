<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Văn bản thô mà người dùng dán vào ô "Phân tích bằng AI" trước đây chỉ nằm
     * trong bộ nhớ trình duyệt: gửi sang DeepSeek, lấy về tiêu đề/tóm tắt/đầu
     * việc rồi biến mất. Mở lại văn bản để sửa là ô trống trơn, muốn phân tích
     * lại phải đi tìm bản gốc dán lần nữa, và cũng không đối chiếu được tóm tắt
     * AI sinh ra với nguyên văn.
     *
     * LONGTEXT chứ không TEXT: TEXT chỉ chứa ~65535 BYTE, mà AnalyzeDocumentRequest
     * cho phép 50.000 KÝ TỰ — tiếng Việt có dấu tốn tới 3 byte/ký tự nên một văn
     * bản hợp lệ vẫn có thể chạm 150KB và bị MySQL strict mode chặn.
     *
     * Cột nằm sau `summary` vì cùng nhóm nội dung, và để trống được: văn bản nhập
     * tay không đi qua AI thì không có nguyên văn nào để lưu.
     */
    public function up(): void
    {
        Schema::table('task_assignment_documents', function (Blueprint $table) {
            $table->longText('ai_source_content')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_documents', function (Blueprint $table) {
            $table->dropColumn('ai_source_content');
        });
    }
};
