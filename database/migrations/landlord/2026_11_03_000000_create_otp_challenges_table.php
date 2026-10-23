<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time codes for parent (guardian) sign-in. The code is stored only as
     * a keyed hash, a challenge is single-use and short-lived, and it belongs to
     * one school and one phone. Sign-in data only: no names or children.
     */
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('phone_e164', 16);
            $table->string('code_hash', 64);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'phone_e164']);
        });

        DB::statement('ALTER TABLE otp_challenges ADD CONSTRAINT otp_challenges_attempts_check CHECK (attempts >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
