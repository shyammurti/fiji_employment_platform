<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SAD data dictionary: JOBSEEKERS.about_me and MENTORS.industry_background (FR-14).
        Schema::table('users', function (Blueprint $table) {
            $table->text('about_me')->nullable();
            $table->string('mentor_industry')->nullable();
        });

        // FR4.5 / SAD FR-36: keep every status step so jobseekers can see their progress.
        Schema::create('application_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->dateTime('interview_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['job_application_id', 'created_at']);
        });

        // Backfill a starting point for applications created before history existed.
        foreach (DB::table('job_applications')->get() as $application) {
            DB::table('application_status_changes')->insert([
                'job_application_id' => $application->id,
                'from_status' => null,
                'to_status' => 'Submitted',
                'created_at' => $application->created_at,
                'updated_at' => $application->created_at,
            ]);
            if ($application->status !== 'Submitted') {
                DB::table('application_status_changes')->insert([
                    'job_application_id' => $application->id,
                    'from_status' => 'Submitted',
                    'to_status' => $application->status,
                    'interview_at' => $application->interview_at,
                    'created_at' => $application->updated_at,
                    'updated_at' => $application->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_changes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['about_me', 'mentor_industry']));
    }
};
