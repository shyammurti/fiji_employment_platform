<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentorship_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentee_id')->constrained('users')->cascadeOnDelete();

            $table->string('topic');
            $table->dateTime('scheduled_at');
            $table->string('status')->default('pending'); // pending | confirmed | completed | cancelled
            $table->boolean('escalated_from_chatbot')->default(false);

            $table->unsignedTinyInteger('rating')->nullable(); // 1-5
            $table->text('feedback')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_sessions');
    }
};
