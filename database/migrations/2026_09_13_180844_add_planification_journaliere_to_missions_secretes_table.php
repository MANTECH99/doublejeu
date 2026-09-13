<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions_secretes', function (Blueprint $table) {
            $table->date('date_mission')->nullable()->after('statut');
            $table->enum('statut', ['en_attente', 'en_cours', 'accomplie', 'demasquee', 'echouee', 'refusee'])->default('en_attente')->change();
            $table->boolean('vue_par_cible')->default(false)->after('devine');
            $table->boolean('vue_par_partenaire')->default(false)->after('vue_par_cible');
            $table->unique(['couple_id', 'joueur_cible_id', 'date_mission']);
        });
    }

    public function down(): void
    {
        Schema::table('missions_secretes', function (Blueprint $table) {
            $table->dropUnique(['couple_id', 'joueur_cible_id', 'date_mission']);
            $table->dropColumn(['date_mission', 'vue_par_cible', 'vue_par_partenaire']);
        });
    }
};
