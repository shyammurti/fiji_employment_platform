<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Career Hub: each "New chat" is its own conversation with its own history.
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('New chat');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'last_message_at']);
        });

        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->foreignId('chat_conversation_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        // Move any earlier messages into one conversation per user.
        foreach (DB::table('chatbot_messages')->select('user_id')->distinct()->pluck('user_id') as $userId) {
            $last = DB::table('chatbot_messages')->where('user_id', $userId)->max('created_at');
            $id = DB::table('chat_conversations')->insertGetId([
                'user_id' => $userId, 'title' => 'Earlier conversation', 'last_message_at' => $last, 'created_at' => $last, 'updated_at' => $last,
            ]);
            DB::table('chatbot_messages')->where('user_id', $userId)->update(['chat_conversation_id' => $id]);
        }

        // Employer verification documents (business registration) for admin review.
        Schema::table('users', function (Blueprint $table) {
            $table->string('business_registration_number', 100)->nullable();
            $table->string('company_address')->nullable();
            $table->string('company_website')->nullable();
            $table->text('company_description')->nullable();
            $table->string('business_document_path')->nullable();
            $table->string('business_document_name')->nullable();
        });

        // Vacancy documentation required for admin review.
        Schema::table('job_listings', function (Blueprint $table) {
            $table->text('responsibilities')->nullable();
            $table->date('closing_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', fn (Blueprint $table) => $table->dropColumn(['responsibilities', 'closing_date']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['business_registration_number', 'company_address', 'company_website', 'company_description', 'business_document_path', 'business_document_name']));
        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chat_conversation_id');
        });
        Schema::dropIfExists('chat_conversations');
    }
};
