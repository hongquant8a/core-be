<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đơn thư chuyển sang xoá mềm.
     *
     * Trước đây xoá là xoá thật: một đơn kiến nghị của người dân bấm nhầm là mất
     * hẳn, không tra lại được, không biết ai xoá lúc nào. Đơn đã hoàn thành nay
     * đã được khoá không cho xoá, nhưng đơn đang xử lý thì vẫn xoá nhầm được —
     * và đó mới là những đơn đang có người chờ trả lời.
     *
     * Cùng tinh thần với công việc bị huỷ: "bản ghi vẫn giữ để tra cứu, không
     * biến mất khỏi thống kê".
     *
     * Không đụng tới `task_assignment_petition_attachments`: khoá ngoại của nó
     * là CASCADE trên bản ghi thật, mà xoá mềm không chạm bản ghi thật nên tệp
     * đính kèm vẫn còn nguyên — đúng điều mong muốn khi khôi phục đơn.
     */
    public function up(): void
    {
        Schema::table('task_assignment_petitions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_petitions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
