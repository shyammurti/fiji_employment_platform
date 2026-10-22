<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropForeign(['job_listing_id']);
            $table->foreign('job_listing_id')->references('id')->on('job_listings')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropForeign(['job_listing_id']);
            $table->foreign('job_listing_id')->references('id')->on('job_listings')->cascadeOnDelete();
        });
    }
};
