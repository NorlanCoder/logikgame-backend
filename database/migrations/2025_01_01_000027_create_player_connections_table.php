<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->string('event');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('browser_fingerprint')->nullable();
            $table->dateTime('occurred_at', 3);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['session_player_id', 'occurred_at']);
            $table->index(['session_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_connections');
    }
};
