<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core (examined), elective / language (examined) or co-scholastic (graded
     * on activity, no marks). Existing subjects stay core, so the previous app
     * version keeps working while this rolls out.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('type', 20)->default('core');
        });

        DB::statement("ALTER TABLE subjects ADD CONSTRAINT subjects_type_check CHECK (type IN ('core', 'elective', 'co_scholastic'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subjects DROP CONSTRAINT subjects_type_check');

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
