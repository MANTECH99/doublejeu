<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meteo_couples', function (Blueprint $table) {
            $table->string('humeur_user1_2')->nullable();
            $table->string('commentaire_user1_2')->nullable();
            $table->string('humeur_user2_2')->nullable();
            $table->string('commentaire_user2_2')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meteo_couples', function (Blueprint $table) {
            $table->dropColumn(['humeur_user1_2', 'commentaire_user1_2', 'humeur_user2_2', 'commentaire_user2_2']);
        });
    }
};
