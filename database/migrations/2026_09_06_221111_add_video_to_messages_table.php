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
            $table->string('video_path')->nullable()->after('photo_h');
            $table->unsignedInteger('video_w')->nullable()->after('video_path');
            $table->unsignedInteger('video_h')->nullable()->after('video_w');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['video_w', 'video_h', 'video_path']);
        });
    }
};
