<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration tổng hợp: đồng bộ schema dev với các thay đổi local (không migrate:fresh).
 *
 * Tất cả thao tác đều defensive (kiểm tra tồn tại trước khi sửa) để chạy được trên mọi
 * state hiện tại của dev server, kể cả khi đã/chưa apply các commit refactor gần đây.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Bỏ bảng meeting_document_attachments (commit ngắn ngày — đã revert).
        Schema::dropIfExists('meeting_document_attachments');

        // 2) Đảm bảo meeting_documents.media_id tồn tại (commit revert khôi phục lại sau khi từng drop).
        if (Schema::hasTable('meeting_documents') && ! Schema::hasColumn('meeting_documents', 'media_id')) {
            Schema::table('meeting_documents', function (Blueprint $table) {
                $table->foreignId('media_id')
                    ->nullable()
                    ->after('summary')
                    ->constrained('media')
                    ->nullOnDelete();
            });
        }

        // 3) Bỏ cột role khỏi meeting_participants.
        if (Schema::hasTable('meeting_participants') && Schema::hasColumn('meeting_participants', 'role')) {
            Schema::table('meeting_participants', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }

        // 3b) Bỏ cột status khỏi meeting_documents (FE chỉ dùng is_public, không cần workflow draft/published).
        if (Schema::hasTable('meeting_documents') && Schema::hasColumn('meeting_documents', 'status')) {
            Schema::table('meeting_documents', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        // 4) Bỏ sort_order khỏi 4 bảng catalog.
        foreach (['meeting_types', 'meeting_locations', 'meeting_document_types', 'meeting_attendee_groups'] as $catalogTable) {
            if (Schema::hasTable($catalogTable) && Schema::hasColumn($catalogTable, 'sort_order')) {
                Schema::table($catalogTable, function (Blueprint $table) {
                    $table->dropColumn('sort_order');
                });
            }
        }

        // 5) Bỏ latitude / longitude khỏi meeting_locations.
        if (Schema::hasTable('meeting_locations')) {
            Schema::table('meeting_locations', function (Blueprint $table) {
                if (Schema::hasColumn('meeting_locations', 'latitude')) {
                    $table->dropColumn('latitude');
                }
                if (Schema::hasColumn('meeting_locations', 'longitude')) {
                    $table->dropColumn('longitude');
                }
            });
        }

        // 6) meeting_attendees: 1-1 với users (bỏ duplicate name/email/phone, user_id NOT NULL, unique).
        if (Schema::hasTable('meeting_attendees')) {
            // 6a) Drop cột duplicate (nếu còn).
            Schema::table('meeting_attendees', function (Blueprint $table) {
                foreach (['name', 'email', 'phone'] as $col) {
                    if (Schema::hasColumn('meeting_attendees', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });

            // 6b) Đảm bảo user_id NOT NULL (cần dữ liệu hợp lệ trước, dev seed đã có user_id).
            //     Dùng raw SQL vì Laravel ->change() yêu cầu doctrine/dbal.
            DB::statement('ALTER TABLE `meeting_attendees` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');

            // 6c) Thêm unique (organization_id, user_id) nếu chưa có.
            if (! $this->indexExists('meeting_attendees', 'meeting_attendees_org_user_unique')) {
                Schema::table('meeting_attendees', function (Blueprint $table) {
                    $table->unique(['organization_id', 'user_id'], 'meeting_attendees_org_user_unique');
                });
            }
        }

        // 6d) meeting_invitations: cho phép chair/operator nhận giấy mời trực tiếp qua attendee_id
        //     (không cần qua participants). meeting_participant_id → nullable, thêm meeting_attendee_id nullable.
        if (Schema::hasTable('meeting_invitations')) {
            // Drop FK cũ trên meeting_participant_id rồi MODIFY nullable, sau đó thêm lại FK.
            // Tên FK mặc định Laravel: meeting_invitations_meeting_participant_id_foreign.
            $fkName = 'meeting_invitations_meeting_participant_id_foreign';
            if ($this->foreignKeyExists('meeting_invitations', $fkName)) {
                Schema::table('meeting_invitations', function (Blueprint $table) {
                    $table->dropForeign('meeting_invitations_meeting_participant_id_foreign');
                });
            }

            DB::statement('ALTER TABLE `meeting_invitations` MODIFY `meeting_participant_id` BIGINT UNSIGNED NULL');

            if (! $this->foreignKeyExists('meeting_invitations', 'meeting_invitations_meeting_participant_id_foreign')) {
                Schema::table('meeting_invitations', function (Blueprint $table) {
                    $table->foreign('meeting_participant_id')
                        ->references('id')->on('meeting_participants')
                        ->cascadeOnDelete();
                });
            }

            if (! Schema::hasColumn('meeting_invitations', 'meeting_attendee_id')) {
                Schema::table('meeting_invitations', function (Blueprint $table) {
                    $table->foreignId('meeting_attendee_id')
                        ->nullable()
                        ->after('meeting_participant_id')
                        ->constrained('meeting_attendees')
                        ->cascadeOnDelete();
                });
            }
        }

        // 7) meetings: thêm chairperson + operator FK trỏ tới meeting_attendees.
        if (Schema::hasTable('meetings') && Schema::hasTable('meeting_attendees')) {
            Schema::table('meetings', function (Blueprint $table) {
                if (! Schema::hasColumn('meetings', 'chairperson_meeting_attendee_id')) {
                    $table->foreignId('chairperson_meeting_attendee_id')
                        ->nullable()
                        ->after('meeting_location_id')
                        ->constrained(table: 'meeting_attendees', indexName: 'meetings_chairperson_attendee_fk')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('meetings', 'operator_meeting_attendee_id')) {
                    $table->foreignId('operator_meeting_attendee_id')
                        ->nullable()
                        ->after('chairperson_meeting_attendee_id')
                        ->constrained(table: 'meeting_attendees', indexName: 'meetings_operator_attendee_fk')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        // Rollback minimal — chỉ undo các thao tác có thể đảo ngược an toàn.
        // (Không khôi phục dữ liệu name/email/phone/role đã mất.)

        if (Schema::hasTable('meetings')) {
            Schema::table('meetings', function (Blueprint $table) {
                if (Schema::hasColumn('meetings', 'operator_meeting_attendee_id')) {
                    $table->dropConstrainedForeignId('operator_meeting_attendee_id');
                }
                if (Schema::hasColumn('meetings', 'chairperson_meeting_attendee_id')) {
                    $table->dropConstrainedForeignId('chairperson_meeting_attendee_id');
                }
            });
        }

        if (Schema::hasTable('meeting_attendees')) {
            if ($this->indexExists('meeting_attendees', 'meeting_attendees_org_user_unique')) {
                Schema::table('meeting_attendees', function (Blueprint $table) {
                    $table->dropUnique('meeting_attendees_org_user_unique');
                });
            }
            DB::statement('ALTER TABLE `meeting_attendees` MODIFY `user_id` BIGINT UNSIGNED NULL');
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(1) AS cnt FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ($row->cnt ?? 0) > 0;
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(1) AS cnt FROM information_schema.table_constraints
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = "FOREIGN KEY"',
            [$database, $table, $constraintName]
        );

        return ($row->cnt ?? 0) > 0;
    }
};
