<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->boolean('is_second_chance')->default(false);
            $table->foreignId('second_chance_question_id')->nullable()->constrained('second_chance_questions')->nullOnDelete();
            $table->string('answer_value', 500)->nullable();
            $table->foreignId('selected_choice_id')->nullable()->constrained('question_choices')->nullOnDelete();
            $table->unsignedBigInteger('selected_sc_choice_id')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->boolean('hint_used')->default(false);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->dateTime('submitted_at', 3)->nullable();
            $table->boolean('is_timeout')->default(false);
            $table->timestamps();

            $table->foreign('selected_sc_choice_id')->references('id')->on('second_chance_question_choices')->nullOnDelete();
            $table->unique(['session_player_id', 'question_id', 'is_second_chance'], 'pa_splayer_question_second_unique');
            $table->index(['question_id', 'is_correct', 'response_time_ms']);
            $table->index('session_player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_answers');
    }
};
