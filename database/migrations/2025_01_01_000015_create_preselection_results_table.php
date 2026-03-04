<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preselection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained('registrations')->cascadeOnDelete();
            $table->unsignedInteger('correct_answers_count')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedBigInteger('total_response_time_ms')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preselection_results');
    }
};
