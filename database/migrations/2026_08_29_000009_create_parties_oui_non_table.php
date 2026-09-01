<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties_oui_non', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('joueur1_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('joueur2_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['en_attente', 'en_cours', 'terminee'])->default('en_attente');
            $table->unsignedInteger('score_joueur1')->default(0);
            $table->unsignedInteger('score_joueur2')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties_oui_non');
    }
};
