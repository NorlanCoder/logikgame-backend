<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finale_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->string('choice');
            $table->dateTime('chosen_at', 3);
            $table->boolean('revealed')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['session_id', 'session_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finale_choices');
    }
};
