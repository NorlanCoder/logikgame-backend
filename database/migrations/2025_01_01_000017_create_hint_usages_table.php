<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hint_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->foreignId('session_round_id')->constrained('session_rounds')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('question_hint_id')->constrained('question_hints')->cascadeOnDelete();
            $table->unsignedInteger('time_remaining_before')->nullable();
            $table->unsignedInteger('time_remaining_after')->nullable();
            $table->dateTime('activated_at', 3);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['session_player_id', 'session_round_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hint_usages');
    }
};
