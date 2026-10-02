<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal des missions déjà servies à un utilisateur. C'est ce qui rend
        // la non-répétition vérifiable : on ne sert que ce qui n'est pas dans
        // cette table. Le suivi est par utilisateur (et non par couple) pour
        // que les deux partenaires parcourent chacun les 100 missions, comme
        // si chacun avait son propre catalogue.
        Schema::create('mission_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->timestamp('served_at');

            // Une mission ne peut être servie qu'une fois par utilisateur.
            // C'est la garantie forte : même en cas de double exécution
            // concurrente de la commande, la seconde insertion échoue au lieu
            // de servir un doublon.
            $table->unique(['user_id', 'mission_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_tracks');
    }
};
