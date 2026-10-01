<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Anniversaire cible pour lequel l'utilisateur a déjà vu le
            // tutoriel d'utilisateur. Null = jamais vu. On stocke l'année et
            // non une date pour que le tutoriel réapparaisse chaque année.
            $table->unsignedSmallInteger('anniv_info_vue_annee')->nullable()->after('devin_verdict_vu_jour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anniv_info_vue_annee');
        });
    }
};
