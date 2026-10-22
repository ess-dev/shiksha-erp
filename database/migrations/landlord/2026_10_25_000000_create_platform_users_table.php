<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Super Admin accounts: our own platform team, separate from school
     * identities. Two-step is always required, so the secret columns are part
     * of the table from the start.
     */
    public function up(): void
    {
        Schema::create('platform_users', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->string('status')->default('active');
            $table->timestampTz('last_signed_in_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX platform_users_email_unique ON platform_users (lower(email))');
        DB::statement("ALTER TABLE platform_users ADD CONSTRAINT platform_users_status_check CHECK (status IN ('active', 'disabled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_users');
    }
};
