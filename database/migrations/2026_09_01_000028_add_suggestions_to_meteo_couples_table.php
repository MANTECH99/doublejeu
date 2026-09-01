<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meteo_couples', function (Blueprint $table) {
            $table->text('suggestion_user1')->nullable();
            $table->text('suggestion_user2')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meteo_couples', function (Blueprint $table) {
            $table->dropColumn(['suggestion_user1', 'suggestion_user2']);
        });
    }
};
