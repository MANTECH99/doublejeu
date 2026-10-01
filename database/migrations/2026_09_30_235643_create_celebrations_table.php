<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('celebrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('destinataire_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->text('message')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('activite')->nullable();
            $table->string('promesse')->nullable();
            $table->timestamps();

            // Une seule préparation par auteur, destinataire et anniversaire : la
            // page de préparation met à jour l'existante au lieu d'en empiler.
            $table->unique(['auteur_id', 'destinataire_id', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('celebrations');
    }
};
