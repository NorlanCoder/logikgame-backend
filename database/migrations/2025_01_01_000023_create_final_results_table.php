<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->string('finale_scenario')->nullable();
            $table->unsignedInteger('final_gain')->default(0);
            $table->boolean('is_winner')->default(false);
            $table->unsignedTinyInteger('position')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['session_id', 'session_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_results');
    }
};
