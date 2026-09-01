<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('joueur1_id')->constrained('users');
            $table->foreignId('joueur2_id')->constrained('users');
            $table->enum('statut', ['en_cours', 'terminee'])->default('en_cours');
            $table->timestamps();
        });

        Schema::create('quiz_session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('quiz_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions_quiz')->cascadeOnDelete();
            $table->foreignId('cible_id')->constrained('users');
            $table->unsignedInteger('ordre')->default(0);
            $table->enum('resultat', ['match', 'manque'])->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_reponses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_question_id')->constrained('quiz_session_questions')->cascadeOnDelete();
            $table->foreignId('joueur_id')->constrained('users');
            $table->string('reponse', 255);
            $table->timestamps();

            $table->unique(['session_question_id', 'joueur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_reponses');
        Schema::dropIfExists('quiz_session_questions');
        Schema::dropIfExists('quiz_sessions');
    }
};
