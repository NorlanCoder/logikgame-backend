<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('status')->default('registered');
            $table->dateTime('confirmation_email_sent_at')->nullable();
            $table->dateTime('selection_email_sent_at')->nullable();
            $table->dateTime('rejection_email_sent_at')->nullable();
            $table->dateTime('registered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['session_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
