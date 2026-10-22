<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained('job_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // jobseeker

            $table->text('cover_letter')->nullable();
            $table->string('resume_path');

            $table->string('status')->default('Submitted');
            // Submitted | Under Review | Shortlisted | Interview Scheduled | Rejected | Hired

            $table->unsignedTinyInteger('match_percentage')->nullable();
            $table->dateTime('interview_at')->nullable();
            $table->text('employer_notes')->nullable();

            $table->timestamps();

            $table->unique(['job_listing_id', 'user_id']); // one application per job per jobseeker
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
