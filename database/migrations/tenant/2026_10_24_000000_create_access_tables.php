<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school's staff records, roles and scoped role assignments. Roles and
     * scopes reference the staff record, never the identity; the staff record
     * references the identity by ulid. Only the sign-in related fields of a
     * staff record exist so far; HR details are added with that module.
     */
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('identity_ulid')->unique();
            $table->string('full_name');
            $table->string('status')->default('active');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE staff ADD CONSTRAINT staff_status_check CHECK (status IN ('active', 'inactive'))");

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name')->unique();
            $table->string('source_template_key')->nullable();
            $table->unsignedInteger('source_template_version')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->timestampsTz();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permission_key');

            $table->unique(['role_id', 'permission_key']);
        });

        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->string('scope_type');
            $table->jsonb('scope')->nullable();
            $table->string('source')->default('manual');
            $table->timestampsTz();

            $table->index('staff_id');
        });

        DB::statement("ALTER TABLE role_assignments ADD CONSTRAINT role_assignments_scope_type_check CHECK (scope_type IN ('school', 'campus', 'wing', 'sections'))");
        DB::statement("ALTER TABLE role_assignments ADD CONSTRAINT role_assignments_source_check CHECK (source IN ('manual', 'auto'))");
        DB::statement("CREATE UNIQUE INDEX role_assignments_unique ON role_assignments (role_id, staff_id, scope_type, (coalesce(scope::text, '')))");
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('staff');
    }
};
