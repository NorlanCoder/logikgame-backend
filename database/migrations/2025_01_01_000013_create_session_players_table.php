<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->string('access_token')->unique();
            $table->string('status')->default('waiting');
            $table->integer('capital')->default(1000);
            $table->unsignedInteger('personal_jackpot')->default(0);
            $table->unsignedInteger('final_gain')->nullable();
            $table->string('browser_fingerprint')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->dateTime('last_connected_at')->nullable();
            $table->dateTime('eliminated_at')->nullable();
            $table->string('elimination_reason')->nullable();
            $table->foreignId('eliminated_in_round_id')->nullable()->constrained('session_rounds')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'player_id']);
            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_players');
    }
};
