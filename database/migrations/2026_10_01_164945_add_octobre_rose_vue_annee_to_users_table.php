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
            // Année du dernier module Octobre rose vu par l'utilisateur.
            // Null = jamais vu. On stocke l'année et non une date pour que la
            // sensibilisation soit reproposée chaque année.
            $table->unsignedSmallInteger('octobre_rose_vue_annee')->nullable()->after('anniv_info_vue_annee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('octobre_rose_vue_annee');
        });
    }
};
