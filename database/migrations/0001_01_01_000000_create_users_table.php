<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Role & workflow
            $table->string('role')->default('jobseeker'); // jobseeker | employer | admin | mentor
            $table->string('employer_approval_status')->default('approved'); // pending | approved | rejected

            // Jobseeker profile fields
            $table->text('skills')->nullable(); // JSON array of skill tags
            $table->string('degree_certificate_path')->nullable();
            $table->boolean('is_verified')->default(false);

            // Location / experience (used by employer candidate search, FR2.3)
            $table->string('region')->nullable();
            $table->string('qualification_level')->nullable(); // Certificate | Diploma | Bachelor | Postgraduate
            $table->unsignedSmallInteger('years_experience')->nullable();

            // Employer-specific fields
            $table->string('company_name')->nullable();
            $table->string('industry_sector')->nullable();

            // Mentor-specific fields
            $table->string('mentor_expertise')->nullable();
            $table->text('mentor_bio')->nullable();

            // Admin governance
            $table->text('admin_notes')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
