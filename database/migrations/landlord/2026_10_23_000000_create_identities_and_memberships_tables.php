<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-in data only (G1): no names, addresses or children. Personal data
     * lives in each school's own staff and guardian records, which reference an
     * identity by ulid. Memberships link an identity to a school with a kind.
     *
     * Replaces the starter kit's `users` and `passkeys` tables.
     */
    public function up(): void
    {
        Schema::create('identities', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('phone_e164', 16)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->string('status')->default('active');
            $table->rememberToken();
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX identities_email_unique ON identities (lower(email)) WHERE email IS NOT NULL');
        DB::statement("ALTER TABLE identities ADD CONSTRAINT identities_status_check CHECK (status IN ('active', 'disabled'))");
        DB::statement('ALTER TABLE identities ADD CONSTRAINT identities_contact_check CHECK (phone_e164 IS NOT NULL OR email IS NOT NULL)');

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('identity_id')->constrained('identities')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('kind');
            $table->string('status')->default('active');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['identity_id', 'tenant_id', 'kind']);
            $table->index('tenant_id');
        });

        DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_kind_check CHECK (kind IN ('staff', 'guardian'))");
        DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ('active', 'revoked'))");

        Schema::dropIfExists('passkeys');
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('identities');
    }
};
