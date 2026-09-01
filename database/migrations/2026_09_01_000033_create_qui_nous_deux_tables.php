<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions_qui_de_nous', function (Blueprint $table) {
            $table->id();
            $table->text('texte');
            $table->enum('categorie', ['personnalite', 'vie_quotidienne', 'relation', 'habitudes'])->default('personnalite');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('parties_qui_de_nous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('joueur1_id')->constrained('users');
            $table->foreignId('joueur2_id')->constrained('users');
            $table->enum('statut', ['en_cours', 'terminee'])->default('en_cours');
            $table->unsignedInteger('score_joueur1')->default(0);
            $table->unsignedInteger('score_joueur2')->default(0);
            $table->timestamps();
        });

        Schema::create('parties_qui_de_nous_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partie_id')->constrained('parties_qui_de_nous')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions_qui_de_nous')->cascadeOnDelete();
            $table->unsignedInteger('ordre')->default(0);
            $table->enum('resultat', ['accord', 'divergence'])->nullable();
            $table->boolean('debat_resolu')->default(false);
            $table->timestamps();

            $table->unique(['partie_id', 'question_id']);
        });

        Schema::create('reponses_qui_de_nous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partie_question_id')->constrained('parties_qui_de_nous_questions')->cascadeOnDelete();
            $table->foreignId('joueur_id')->constrained('users');
            $table->enum('designation', ['moi', 'partenaire'])->nullable();
            $table->timestamps();

            $table->unique(['partie_question_id', 'joueur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponses_qui_de_nous');
        Schema::dropIfExists('parties_qui_de_nous_questions');
        Schema::dropIfExists('parties_qui_de_nous');
        Schema::dropIfExists('questions_qui_de_nous');
    }
};
