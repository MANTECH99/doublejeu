<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quoridor_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('joueur1_id')->constrained('users');
            $table->foreignId('joueur2_id')->constrained('users');
            $table->enum('statut', ['en_cours', 'terminee'])->default('en_cours');
            $table->foreignId('tour_id')->nullable()->constrained('users');
            $table->json('pions');
            $table->json('murs')->nullable();
            $table->foreignId('vainqueur_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['couple_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quoridor_parties');
    }
};
