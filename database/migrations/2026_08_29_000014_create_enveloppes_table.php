<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enveloppes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('joueur_id')->constrained('users')->cascadeOnDelete();
            $table->enum('couleur', ['rouge', 'bleue', 'verte']);
            $table->foreignId('defi_id')->constrained('defis_enveloppes')->cascadeOnDelete();
            $table->enum('statut', ['disponible', 'utilisee', 'realisee', 'refusee'])->default('disponible');
            $table->foreignId('partie_joueur_qui_realise')->nullable();
            $table->boolean('accepte')->nullable();
            $table->timestamps();

            $table->index(['couple_id', 'joueur_id', 'couleur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enveloppes');
    }
};
