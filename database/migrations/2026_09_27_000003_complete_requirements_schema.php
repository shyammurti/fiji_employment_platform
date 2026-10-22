<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('credential_status')->default('not_submitted');
            $table->text('credential_notes')->nullable();
            $table->boolean('mentor_available')->default(true);
        });
        DB::table('users')->whereNotNull('degree_certificate_path')->update(['credential_status' => 'pending']);
        DB::table('users')->where('is_verified', true)->update(['credential_status' => 'verified']);
        foreach (['job_applications' => ['user_id'], 'mentorship_sessions' => ['mentor_id', 'mentee_id']] as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->dropForeign([$column]);
                    $table->foreign($column)->references('id')->on('users')->restrictOnDelete();
                }
            });
        }
        Schema::create('role_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('user_id');
            $table->string('previous_role');
            $table->string('new_role');
            $table->text('reason');
            $table->timestamps();
        });
        Schema::create('job_match_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'job_listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_match_alerts');
        Schema::dropIfExists('role_changes');
        foreach (['job_applications' => ['user_id'], 'mentorship_sessions' => ['mentor_id', 'mentee_id']] as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->dropForeign([$column]);
                    $table->foreign($column)->references('id')->on('users')->cascadeOnDelete();
                }
            });
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['credential_status', 'credential_notes', 'mentor_available']));
    }
};
