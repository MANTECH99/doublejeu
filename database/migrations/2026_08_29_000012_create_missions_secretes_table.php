<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions_secretes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('joueur_cible_id')->constrained('users')->cascadeOnDelete();
            $table->text('texte');
            $table->enum('difficulte', ['facile', 'moyen', 'difficile'])->default('moyen');
            $table->enum('statut', ['en_attente', 'en_cours', 'accomplie', 'demasquee', 'echouee'])->default('en_attente');
            $table->timestamp('revele_at')->nullable();
            $table->timestamp('date_debut')->nullable();
            $table->timestamp('date_fin')->nullable();
            $table->timestamp('accomplie_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions_secretes');
    }
};
