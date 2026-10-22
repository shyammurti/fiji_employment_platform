<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->unsignedSmallInteger('vacancy_count')->default(1);
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->text('closure_reason')->nullable();
            $table->timestamp('recruitment_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn(['vacancy_count', 'document_path', 'document_name', 'closure_reason', 'recruitment_completed_at']);
        });
    }
};
