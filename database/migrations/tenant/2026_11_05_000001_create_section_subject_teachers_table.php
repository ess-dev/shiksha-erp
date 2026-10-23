<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who teaches a subject in one section. A row exists only when someone
     * was chosen; without one the section's class teacher is the automatic
     * subject teacher.
     */
    public function up(): void
    {
        Schema::create('section_subject_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['section_id', 'subject_id']);
            $table->index('subject_id');
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_subject_teachers');
    }
};
