<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours_vo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partie_id')->constrained('parties_vo')->cascadeOnDelete();
            $table->foreignId('joueur_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['verite', 'action', 'gage']);
            $table->unsignedBigInteger('carte_id')->nullable()->index();
            $table->text('reponse')->nullable();
            $table->string('piece_jointe')->nullable();
            $table->boolean('accepte')->default(true);
            $table->smallInteger('points_gagnes')->default(0);
            $table->enum('statut', ['en_attente', 'realise', 'valide', 'refuse'])->default('en_attente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours_vo');
    }
};
