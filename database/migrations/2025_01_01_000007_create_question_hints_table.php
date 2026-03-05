<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_hints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->unique()->constrained('questions')->cascadeOnDelete();
            $table->string('hint_type');
            $table->unsignedInteger('time_penalty_seconds')->default(0);
            $table->json('removed_choice_ids')->nullable();
            $table->json('revealed_letters')->nullable();
            $table->string('range_hint_text')->nullable();
            $table->decimal('range_min', 15, 4)->nullable();
            $table->decimal('range_max', 15, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_hints');
    }
};
