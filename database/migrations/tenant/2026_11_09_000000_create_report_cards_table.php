<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report cards a class teacher prepares for one student and one exam:
     * either generated from published marks (a JSONB snapshot of the card as
     * it was sent, so later changes never alter it) or a scan uploaded to the
     * tenant disk. One per student and exam; it is sent for approval
     * (`submitted`), sent back or withdrawn (`returned`, then prepared again)
     * or shown to parents (`published`, final).
     */
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('status', 20);
            $table->string('remark', 300)->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name', 200)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->foreignId('prepared_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('prepared_at');
            $table->foreignId('decided_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->string('return_note', 200)->nullable();
            $table->timestampsTz();

            $table->unique(['exam_id', 'student_id']);
            $table->index(['exam_id', 'section_id', 'status']);
            $table->index('student_id');
            $table->index('section_id');
            $table->index('prepared_by_staff_id');
            $table->index('decided_by_staff_id');
        });

        DB::statement("ALTER TABLE report_cards ADD CONSTRAINT report_cards_kind_check CHECK (kind IN ('generated', 'file'))");
        DB::statement("ALTER TABLE report_cards ADD CONSTRAINT report_cards_status_check CHECK (status IN ('submitted', 'returned', 'published'))");
        DB::statement("ALTER TABLE report_cards ADD CONSTRAINT report_cards_content_check CHECK ((kind = 'generated' AND snapshot IS NOT NULL) OR (kind = 'file' AND file_path IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};
