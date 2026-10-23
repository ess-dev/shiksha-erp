<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The room a section sits in ("Room 215"). Nullable and unused by older
     * code, so the previous app version keeps working while this rolls out.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('room', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('room');
        });
    }
};
