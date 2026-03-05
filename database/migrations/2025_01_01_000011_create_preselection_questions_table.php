<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preselection_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->text('text');
            $table->string('media_type')->default('none');
            $table->string('media_url', 500)->nullable();
            $table->string('answer_type');
            $table->string('correct_answer', 500);
            $table->boolean('number_is_decimal')->default(false);
            $table->unsignedInteger('duration')->default(30);
            $table->unsignedInteger('display_order');
            $table->timestamps();

            $table->index(['session_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preselection_questions');
    }
};
