<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round6_turn_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_round_id')->constrained('session_rounds')->cascadeOnDelete();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->unsignedTinyInteger('turn_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['session_round_id', 'session_player_id']);
            $table->unique(['session_round_id', 'turn_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round6_turn_order');
    }
};
