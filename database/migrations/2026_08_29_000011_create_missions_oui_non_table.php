<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions_oui_non', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->foreignId('partie_id')->constrained('parties_oui_non')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions_oui_non')->cascadeOnDelete();
            $table->enum('statut', ['a_realiser', 'realisee', 'validee'])->default('a_realiser');
            $table->timestamp('realisee_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions_oui_non');
    }
};
