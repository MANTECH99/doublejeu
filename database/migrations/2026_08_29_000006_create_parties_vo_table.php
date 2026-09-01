<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties_vo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->enum('niveau', ['doux', 'chaud', 'brulant']);
            $table->enum('status', ['en_cours', 'terminee', 'abandonnee'])->default('en_cours');
            $table->foreignId('joueur_actif_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('score_joueur1')->default(0);
            $table->unsignedInteger('score_joueur2')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties_vo');
    }
};
