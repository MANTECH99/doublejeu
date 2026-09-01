<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grilles_mots_croises', function (Blueprint $table) {
            $table->foreignId('createur_id')->nullable()->after('couple_id')->constrained('users')->nullOnDelete();

            // Doit exister avant de retirer l'index (couple_id, semaine) qui sert d'index support à la FK couple_id.
            $table->unique(['couple_id', 'createur_id']);
        });

        Schema::table('grilles_mots_croises', function (Blueprint $table) {
            $table->dropUnique('grilles_mots_croises_couple_id_semaine_unique');
        });
    }

    public function down(): void
    {
        Schema::table('grilles_mots_croises', function (Blueprint $table) {
            $table->dropUnique('grilles_mots_croises_couple_id_createur_id_unique');

            $table->dropForeign(['createur_id']);
            $table->dropColumn('createur_id');

            $table->unique(['couple_id', 'semaine']);
        });
    }
};
