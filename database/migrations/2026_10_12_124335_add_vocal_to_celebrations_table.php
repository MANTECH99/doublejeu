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
        Schema::table('celebrations', function (Blueprint $table) {
            $table->string('vocal_path')->nullable()->after('audio_path');
            $table->unsignedSmallInteger('vocal_duration')->nullable()->after('vocal_path');
            $table->string('vocal_bars')->nullable()->after('vocal_duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('celebrations', function (Blueprint $table) {
            $table->dropColumn(['vocal_path', 'vocal_duration', 'vocal_bars']);
        });
    }
};
