<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round6_player_jackpots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_round_id')->constrained('session_rounds')->cascadeOnDelete();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->unsignedInteger('bonus_count')->default(0);
            $table->unsignedInteger('personal_jackpot')->default(1000);
            $table->unsignedInteger('departed_with')->nullable();
            $table->timestamps();

            $table->unique(['session_round_id', 'session_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round6_player_jackpots');
    }
};
