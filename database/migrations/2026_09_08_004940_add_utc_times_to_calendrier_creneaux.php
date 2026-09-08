<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('calendrier_creneaux', function (Blueprint $table) {
            $table->dateTime('debut_utc')->nullable()->after('heure_debut');
            $table->dateTime('fin_utc')->nullable()->after('heure_fin');
        });

        // Backfill : l'heure saisie (date_jour + heure_debut) était la valeur
        // brute, sans fuseau. On l'interprète comme le fuseau applicatif (UTC à
        // ce jour) pour disposer d'un instant absolu dès la mise en production.
        DB::table('calendrier_creneaux')
            ->whereNull('debut_utc')
            ->whereNotNull('date_jour')
            ->whereNotNull('heure_debut')
            ->get()
            ->each(function ($creneau) {
                DB::table('calendrier_creneaux')
                    ->where('id', $creneau->id)
                    ->update([
                        'debut_utc' => $creneau->date_jour.' '.$creneau->heure_debut,
                        'fin_utc' => $creneau->heure_fin
                            ? $creneau->date_jour.' '.$creneau->heure_fin
                            : null,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendrier_creneaux', function (Blueprint $table) {
            $table->dropColumn(['debut_utc', 'fin_utc']);
        });
    }
};
