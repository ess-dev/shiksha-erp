<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail of platform actions: Super Admin work, support
     * access to a school and provisioning. Same immutability rules as a school's
     * audit_events.
     */
    public function up(): void
    {
        Schema::create('platform_audit_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->timestampTz('occurred_at');
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->string('actor_type');
            $table->string('actor_identity_ulid')->nullable();
            $table->string('action');
            $table->string('subject_type');
            $table->string('subject_ulid')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->index(['subject_type', 'subject_ulid']);
            $table->index('action');
            $table->index('occurred_at');
        });

        DB::statement("ALTER TABLE platform_audit_events ADD CONSTRAINT platform_audit_events_actor_type_check CHECK (actor_type IN ('user', 'staff', 'guardian', 'platform', 'system'))");

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_audit_change() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Audit records are append-only (% on %)', TG_OP, TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement('CREATE TRIGGER platform_audit_events_no_change BEFORE UPDATE OR DELETE ON platform_audit_events FOR EACH ROW EXECUTE FUNCTION prevent_audit_change()');
        DB::statement('CREATE TRIGGER platform_audit_events_no_truncate BEFORE TRUNCATE ON platform_audit_events FOR EACH STATEMENT EXECUTE FUNCTION prevent_audit_change()');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_events');
        DB::statement('DROP FUNCTION IF EXISTS prevent_audit_change()');
    }
};
