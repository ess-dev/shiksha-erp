<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campuses, academic years, classes, sections and subjects of one school.
     * Lifecycle uses status fields, not deletion. Academic-year bounds are
     * calendar dates (`date`), never converted through UTC.
     */
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE campuses ADD CONSTRAINT campuses_status_check CHECK (status IN ('active', 'inactive'))");

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE academic_years ADD CONSTRAINT academic_years_dates_check CHECK (ends_on > starts_on)');
        DB::statement('CREATE UNIQUE INDEX academic_years_one_current ON academic_years (is_current) WHERE is_current');

        // A class is a grade definition shared by all campuses ("Class 5"); its
        // level orders classes and is what a wing's class range refers to.
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name')->unique();
            $table->smallInteger('level')->unique();
            $table->timestampsTz();
        });

        // illustrative — verify the range: nursery classes below 1, up to Class 12 and beyond.
        DB::statement('ALTER TABLE school_classes ADD CONSTRAINT school_classes_level_check CHECK (level BETWEEN -5 AND 20)');

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->string('name');
            $table->smallInteger('capacity')->nullable();
            $table->string('status')->default('active');
            $table->timestampsTz();

            $table->unique(['academic_year_id', 'campus_id', 'school_class_id', 'name']);
            $table->index('campus_id');
            $table->index('school_class_id');
        });

        DB::statement('ALTER TABLE sections ADD CONSTRAINT sections_capacity_check CHECK (capacity IS NULL OR capacity > 0)');
        DB::statement("ALTER TABLE sections ADD CONSTRAINT sections_status_check CHECK (status IN ('active', 'inactive'))");

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE subjects ADD CONSTRAINT subjects_status_check CHECK (status IN ('active', 'inactive'))");

        Schema::create('school_class_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();

            $table->unique(['school_class_id', 'subject_id']);
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_class_subject');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('campuses');
    }
};
