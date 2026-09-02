<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gif_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained()->cascadeOnDelete();
            $table->string('gif_url');
            $table->string('gif_alt')->nullable();
            $table->timestamps();

            $table->unique(['couple_id', 'gif_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gif_favorites');
    }
};
