<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-owned pricing: the sellable modules, the plans, and which
     * modules each plan includes. Prices are per student per year, in paise.
     * A school's plan is a nullable reference for now; subscriptions (trial,
     * renewal, billing) arrive with the Schools screens.
     */
    public function up(): void
    {
        Schema::create('platform_modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order');
            $table->timestampsTz();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('badge')->nullable();
            $table->unsignedBigInteger('price_per_student_year_paise');
            $table->unsignedInteger('min_students');
            $table->unsignedSmallInteger('sort_order');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_price_positive_check CHECK (price_per_student_year_paise > 0)');

        Schema::create('plan_modules', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('module_key');
            $table->foreign('module_key')->references('key')->on('platform_modules')->restrictOnDelete();
            $table->primary(['plan_id', 'module_key']);
            $table->index('module_key');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->constrained('plans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::dropIfExists('plan_modules');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('platform_modules');
    }
};
