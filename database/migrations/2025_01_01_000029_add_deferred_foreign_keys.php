<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->foreign('current_round_id')->references('id')->on('session_rounds')->nullOnDelete();
            $table->foreign('current_question_id')->references('id')->on('questions')->nullOnDelete();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('assigned_player_id')->references('id')->on('session_players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropForeign(['current_round_id']);
            $table->dropForeign(['current_question_id']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['assigned_player_id']);
        });
    }
};
