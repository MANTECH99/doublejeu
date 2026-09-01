<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions_journalieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions_du_jour')->cascadeOnDelete();
            $table->date('jour');
            $table->timestamps();

            $table->unique(['couple_id', 'jour']);
        });

        Schema::create('reponses_questions_journalieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_journaliere_id')->constrained('questions_journalieres')->cascadeOnDelete();
            $table->foreignId('joueur_id')->constrained('users');
            $table->text('reponse');
            $table->timestamps();

            $table->unique(['question_journaliere_id', 'joueur_id'], 'reponses_qj_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponses_questions_journalieres');
        Schema::dropIfExists('questions_journalieres');
    }
};
