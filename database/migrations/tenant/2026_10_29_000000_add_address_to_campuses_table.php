<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive and nullable, so it is safe on every school's existing data.
     */
    public function up(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->string('address')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
