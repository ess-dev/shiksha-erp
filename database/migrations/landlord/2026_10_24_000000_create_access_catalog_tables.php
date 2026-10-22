<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform-owned permission catalog and versioned role templates.
     * Schools copy templates into their own roles (tenant database).
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('module');
            $table->string('risk')->default('normal');
            $table->boolean('is_owner_only')->default(false);
            $table->string('description')->nullable();
            $table->timestampsTz();

            $table->index('module');
        });

        DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_risk_check CHECK (risk IN ('normal', 'sensitive', 'critical'))");

        Schema::create('role_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('current_version')->default(1);
            $table->timestampsTz();
        });

        Schema::create('role_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_template_id')->constrained('role_templates')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('changelog')->nullable();
            $table->boolean('is_owner_template')->default(false);
            $table->timestampsTz();

            $table->unique(['role_template_id', 'version']);
        });

        Schema::create('role_template_version_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_template_version_id')->constrained('role_template_versions')->restrictOnDelete();
            $table->string('permission_key');
            $table->foreign('permission_key')->references('key')->on('permissions')->restrictOnDelete();

            $table->unique(['role_template_version_id', 'permission_key'], 'role_template_version_permissions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_template_version_permissions');
        Schema::dropIfExists('role_template_versions');
        Schema::dropIfExists('role_templates');
        Schema::dropIfExists('permissions');
    }
};
