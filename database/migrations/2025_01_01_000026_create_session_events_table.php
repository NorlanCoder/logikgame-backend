<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreignId('session_round_id')->nullable()->constrained('session_rounds')->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->dateTime('occurred_at', 3);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['session_id', 'event_type']);
            $table->index(['session_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_events');
    }
};
