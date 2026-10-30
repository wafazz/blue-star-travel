<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI assistant conversation log (enhancement REQ-AI-016, REQ-AI-022, REQ-AI-030; ADR-AI-009).
 * Visitors are identified by a salted session hash, never a raw IP or session ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_hash', 64)->index();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('body');
            $table->string('route', 20)->default('assistant'); // assistant | handoff
            $table->json('package_ids')->nullable();
            $table->string('provider', 20)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('status', 20)->default('ok'); // ok | blocked | unavailable | invalid
            $table->timestamp('created_at')->useCurrent();
            $table->index(['created_at', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
