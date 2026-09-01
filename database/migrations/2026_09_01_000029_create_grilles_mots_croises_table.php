<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grilles_mots_croises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->date('semaine');
            $table->string('statut')->default('en_cours');
            $table->json('mots');
            $table->json('grille')->nullable();
            $table->json('reponses_user1')->nullable();
            $table->json('reponses_user2')->nullable();
            $table->json('attribues_user1')->nullable();
            $table->json('attribues_user2')->nullable();
            $table->json('proposition_user1')->nullable();
            $table->json('proposition_user2')->nullable();
            $table->timestamps();

            $table->unique(['couple_id', 'semaine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grilles_mots_croises');
    }
};
