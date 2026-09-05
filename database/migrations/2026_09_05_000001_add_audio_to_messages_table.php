<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('audio_path')->nullable()->after('photo_path');
            $table->unsignedSmallInteger('audio_duration')->nullable()->after('audio_path');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['audio_duration', 'audio_path']);
        });
    }
};
