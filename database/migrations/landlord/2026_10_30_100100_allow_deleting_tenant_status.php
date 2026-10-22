<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_status_check');
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_status_check CHECK (status IN ('trial', 'active', 'suspended', 'expired', 'deleting'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_status_check');
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_status_check CHECK (status IN ('trial', 'active', 'suspended', 'expired'))");
    }
};
