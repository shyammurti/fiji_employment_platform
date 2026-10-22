<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // employer

            $table->string('title');
            $table->string('company');
            $table->string('location');
            $table->string('region')->nullable(); // Division/Region for filtering (FR2.3)
            $table->string('industry_sector')->nullable();
            $table->string('type'); // Full-time | Part-time | Contract | Remote
            $table->string('salary_range')->nullable();
            $table->text('description');
            $table->string('required_degree')->nullable();
            $table->text('required_skills')->nullable(); // JSON array of skill tags

            $table->string('approval_status')->default('pending_approval'); // pending_approval | approved | rejected
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index(['approval_status', 'is_active', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
};
