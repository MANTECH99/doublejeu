<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Aperçu de lien (façon WhatsApp) : la première URL du corps est
            // résolue côté serveur (Open Graph) et recopiée ici pour un rendu
            // sans requête externe. L'image est hébergée localement.
            $table->string('link_url')->nullable()->after('body');
            $table->string('link_title')->nullable()->after('link_url');
            $table->text('link_description')->nullable()->after('link_title');
            $table->string('link_site')->nullable()->after('link_description');
            $table->string('link_image_path')->nullable()->after('link_site');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['link_url', 'link_title', 'link_description', 'link_site', 'link_image_path']);
        });
    }
};
