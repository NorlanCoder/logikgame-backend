<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jackpot_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('session_player_id')->nullable()->constrained('session_players')->nullOnDelete();
            $table->foreignId('session_round_id')->nullable()->constrained('session_rounds')->nullOnDelete();
            $table->string('transaction_type');
            $table->integer('amount');
            $table->unsignedInteger('jackpot_before');
            $table->unsignedInteger('jackpot_after');
            $table->string('description', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jackpot_transactions');
    }
};
