<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_seat_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            // B3 (meeting_seat_layout_templates) là v2 — chưa tồn tại nên để nullable không constrained.
            $table->unsignedBigInteger('seat_layout_template_id')->nullable();
            $table->string('layout_type', 20);                 // theater|presidium|curved|ushape
            $table->json('config');                            // { rows, cols, aisles, head, side, curve, stage }
            $table->json('canvas')->nullable();                // { width, height }
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('meeting_id', 'meeting_seat_maps_meeting_unique');
            $table->index(['organization_id', 'meeting_id'], 'meeting_seat_maps_org_mtg_idx');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('meeting_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('seat_map_id')->constrained('meeting_seat_maps')->cascadeOnDelete();
            $table->foreignId('meeting_participant_id')->nullable()
                ->constrained('meeting_participants')->nullOnDelete();  // null = ghế trống
            $table->string('zone', 20)->default('audience');   // head | audience
            $table->boolean('is_vip')->default(false);         // ghế trưởng đoàn/VIP — thuộc tính của GHẾ, không phụ thuộc người ngồi
            $table->string('label', 20);                       // "A1", "CT1", "Đ1"...
            $table->integer('row_index')->nullable();
            $table->integer('col_index')->nullable();
            $table->integer('pos_x');                           // toạ độ px để render
            $table->integer('pos_y');
            $table->decimal('rotation', 6, 2)->nullable();      // rạp cong
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['seat_map_id', 'label'], 'meeting_seats_map_label_unique');
            $table->index(['organization_id', 'meeting_id'], 'meeting_seats_org_mtg_idx');
            $table->index('meeting_participant_id', 'meeting_seats_participant_idx');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_seats');
        Schema::dropIfExists('meeting_seat_maps');
    }
};
