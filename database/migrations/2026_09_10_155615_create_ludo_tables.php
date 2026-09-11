<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ludo_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->foreignId('joueur1_id')->constrained('users');
            $table->foreignId('joueur2_id')->constrained('users');
            $table->enum('statut', ['en_cours', 'terminee'])->default('en_cours');
            $table->foreignId('tour_id')->nullable()->constrained('users');
            $table->unsignedTinyInteger('dernier_de')->nullable();
            $table->unsignedTinyInteger('six_compte')->default(0);
            $table->foreignId('vainqueur_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['couple_id', 'statut']);
        });

        Schema::create('ludo_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partie_id')->constrained('ludo_parties')->cascadeOnDelete();
            $table->foreignId('joueur_id')->constrained('users');
            $table->enum('couleur', ['rouge', 'bleue']);
            $table->unsignedTinyInteger('numero')->default(0);
            $table->integer('position')->default(-1);
            $table->timestamps();

            $table->unique(['partie_id', 'couleur', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ludo_tokens');
        Schema::dropIfExists('ludo_parties');
    }
};
