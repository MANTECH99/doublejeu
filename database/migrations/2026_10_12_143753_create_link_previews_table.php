<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cache des aperçus de liens, indexé par l'empreinte de l'URL : une même
        // URL n'est scrapée qu'une fois (aperçu du composeur puis bulle envoyée).
        Schema::create('link_previews', function (Blueprint $table) {
            $table->id();
            $table->string('url_hash', 64)->unique();
            $table->text('url');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('site')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_previews');
    }
};
