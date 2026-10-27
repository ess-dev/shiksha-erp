<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Work details kept by the school office (employee code, job title,
     * department, joined date) and the details a member of staff keeps about
     * themselves (contact, qualifications, emergency contact, photo,
     * notification channels). All nullable so existing staff rows stay valid.
     * Bank, PAN and UAN wait for payroll.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('employee_code', 20)->nullable()->unique();
            $table->string('job_title', 60)->nullable();
            $table->string('department', 60)->nullable();
            $table->date('joined_on')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('qualifications', 100)->nullable();
            $table->string('languages', 80)->nullable();
            $table->string('home_address', 200)->nullable();
            $table->string('about', 240)->nullable();
            $table->string('emergency_name', 60)->nullable();
            $table->string('emergency_relation', 20)->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->jsonb('notification_channels')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn([
                'employee_code', 'job_title', 'department', 'joined_on', 'personal_email', 'qualifications',
                'languages', 'home_address', 'about', 'emergency_name', 'emergency_relation', 'emergency_phone',
                'photo_path', 'notification_channels',
            ]);
        });
    }
};
