<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meteo_couples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->date('jour');
            $table->string('humeur_user1')->nullable();
            $table->string('commentaire_user1')->nullable();
            $table->string('humeur_user2')->nullable();
            $table->string('commentaire_user2')->nullable();
            $table->timestamps();

            $table->unique(['couple_id', 'jour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meteo_couples');
    }
};
