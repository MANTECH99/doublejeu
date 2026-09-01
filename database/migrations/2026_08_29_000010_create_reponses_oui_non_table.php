<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reponses_oui_non', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partie_id')->constrained('parties_oui_non')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions_oui_non')->cascadeOnDelete();
            $table->foreignId('joueur_id')->constrained('users')->cascadeOnDelete();
            $table->enum('reponse', ['oui', 'non'])->nullable();
            $table->timestamps();

            $table->unique(['partie_id', 'question_id', 'joueur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponses_oui_non');
    }
};
