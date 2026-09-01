<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recompenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('joueur_gagnant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('joueur_perdant_id')->constrained('users')->cascadeOnDelete();
            $table->text('texte')->nullable();
            $table->enum('statut', ['due', 'offerte', 'refusee'])->default('due');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recompenses');
    }
};
