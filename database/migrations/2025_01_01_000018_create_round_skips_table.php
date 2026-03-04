<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->foreignId('session_round_id')->constrained('session_rounds')->cascadeOnDelete();
            $table->unsignedInteger('capital_lost')->default(1000);
            $table->dateTime('skipped_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['session_player_id', 'session_round_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_skips');
    }
};
