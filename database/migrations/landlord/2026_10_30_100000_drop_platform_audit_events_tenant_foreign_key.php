<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Platform audit rows are append-only, so they must be able to outlive a
     * deleted school. The column stays as a plain historical reference.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE platform_audit_events DROP CONSTRAINT IF EXISTS platform_audit_events_tenant_id_foreign');
    }

    public function down(): void
    {
        // Not restored: rows for deleted schools would violate the constraint.
    }
};
