<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cover_image_url', 500)->nullable();
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('max_players');
            $table->string('status')->default('draft');
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->dateTime('preselection_opens_at')->nullable();
            $table->dateTime('preselection_closes_at')->nullable();
            $table->unsignedBigInteger('current_round_id')->nullable();
            $table->unsignedBigInteger('current_question_id')->nullable();
            $table->unsignedInteger('jackpot')->default(0);
            $table->unsignedInteger('players_remaining')->default(0);
            $table->unsignedInteger('reconnection_delay')->default(10);
            $table->string('projection_code', 10)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
