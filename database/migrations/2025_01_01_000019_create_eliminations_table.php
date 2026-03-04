<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eliminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_player_id')->constrained('session_players')->cascadeOnDelete();
            $table->foreignId('session_round_id')->constrained('session_rounds')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->string('reason');
            $table->unsignedInteger('capital_transferred')->default(1000);
            $table->dateTime('eliminated_at');
            $table->boolean('is_manual')->default(false);
            $table->text('admin_note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('session_round_id');
            $table->index(['session_round_id', 'eliminated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eliminations');
    }
};
