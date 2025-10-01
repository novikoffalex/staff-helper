<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained('telegram_users')->onDelete('cascade');
            $table->bigInteger('telegram_message_id')->nullable();
            $table->enum('message_type', ['text', 'voice', 'command'])->default('text');
            $table->text('message_content');
            $table->text('ai_response')->nullable();
            $table->json('message_metadata')->nullable(); // Дополнительные данные (голос, файлы и т.д.)
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['telegram_user_id', 'created_at']);
            $table->index('message_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
