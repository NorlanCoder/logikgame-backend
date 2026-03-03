<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_round_id')->constrained('session_rounds')->cascadeOnDelete();
            $table->text('text');
            $table->string('media_type')->default('none');
            $table->string('media_url', 500)->nullable();
            $table->string('answer_type');
            $table->string('correct_answer', 500);
            $table->boolean('number_is_decimal')->default(false);
            $table->unsignedInteger('duration')->default(30);
            $table->unsignedInteger('display_order');
            $table->string('status')->default('pending');
            $table->dateTime('launched_at', 3)->nullable();
            $table->dateTime('closed_at', 3)->nullable();
            $table->dateTime('revealed_at', 3)->nullable();
            $table->unsignedBigInteger('assigned_player_id')->nullable();
            $table->timestamps();

            $table->index(['session_round_id', 'display_order']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
