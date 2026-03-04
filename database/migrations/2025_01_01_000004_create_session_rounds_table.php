<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->unsignedTinyInteger('round_number');
            $table->string('round_type');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('display_order');
            $table->text('rules_description')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'round_number']);
            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_rounds');
    }
};
