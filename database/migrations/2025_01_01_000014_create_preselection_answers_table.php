<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preselection_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('preselection_question_id')->constrained('preselection_questions')->cascadeOnDelete();
            $table->string('answer_value', 500)->nullable();
            $table->foreignId('selected_choice_id')->nullable()->constrained('preselection_question_choices')->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->dateTime('submitted_at', 3)->nullable();
            $table->timestamps();

            $table->unique(['registration_id', 'preselection_question_id'], 'pa_reg_prequestion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preselection_answers');
    }
};
