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
        Schema::table('messages', function (Blueprint $table) {
            // Hauteurs des barres de la bande son du vocal (écrites par le
            // navigateur de l'expéditeur, ex. "45,22,78,60,…").
            $table->string('audio_bars', 1024)->nullable()->after('audio_duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('audio_bars');
        });
    }
};
