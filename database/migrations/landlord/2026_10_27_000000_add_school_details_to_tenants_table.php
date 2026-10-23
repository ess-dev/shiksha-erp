<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('board')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            // Landlord copies of figures that live in the school's own database.
            $table->unsignedInteger('campus_count')->default(1);
            $table->unsignedInteger('student_count')->default(0);
            $table->date('since_on')->nullable();
            $table->date('trial_ends_on')->nullable();
            $table->date('renews_on')->nullable();
        });

        DB::statement('ALTER TABLE tenants ADD CONSTRAINT tenants_counts_check CHECK (campus_count >= 0 AND student_count >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_counts_check');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'city', 'state', 'board', 'contact_name', 'contact_email',
                'campus_count', 'student_count', 'since_on', 'trial_ends_on', 'renews_on',
            ]);
        });
    }
};
