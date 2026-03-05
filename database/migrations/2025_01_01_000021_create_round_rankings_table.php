<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_round_id')->constrained('session_rounds')->cascadeOnDelete();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->unsignedInteger('correct_answers_count')->default(0);
            $table->unsignedBigInteger('total_response_time_ms')->default(0);
            $table->unsignedInteger('rank');
            $table->boolean('is_qualified')->default(false);
            $table->timestamps();

            $table->unique(['session_round_id', 'session_player_id']);
            $table->index(['session_round_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_rankings');
    }
};
