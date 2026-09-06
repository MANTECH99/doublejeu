<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bucket_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained('couples')->cascadeOnDelete();
            $table->string('titre');
            $table->enum('categorie', ['voyages', 'activites', 'gastronomie', 'projets']);
            $table->string('lieu')->nullable();
            $table->boolean('realise')->default(false);
            $table->timestamp('realise_at')->nullable();
            $table->json('photos')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['couple_id', 'realise']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bucket_list_items');
    }
};
