<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartes_action', function (Blueprint $table) {
            $table->id();
            $table->text('texte');
            $table->enum('niveau', ['doux', 'chaud', 'brulant'])->default('doux');
            $table->string('categorie', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartes_action');
    }
};
