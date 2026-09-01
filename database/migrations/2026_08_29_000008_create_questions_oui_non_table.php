<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions_oui_non', function (Blueprint $table) {
            $table->id();
            $table->text('texte');
            $table->enum('categorie', ['vie_quotidienne', 'intimite', 'fantasmes', 'aventure'])->default('vie_quotidienne');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions_oui_non');
    }
};
