<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendrier_creneaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date_jour');
            $table->string('titre');
            $table->time('heure_debut');
            $table->time('heure_fin')->nullable();
            $table->string('couleur')->nullable();
            $table->timestamps();

            $table->index(['couple_id', 'date_jour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendrier_creneaux');
    }
};
