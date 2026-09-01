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
        Schema::table('users', function (Blueprint $table) {
            $table->date('devin_mission_jour')->nullable()->after('date_naissance');
            $table->string('devin_mission_reponse', 10)->nullable()->after('devin_mission_jour');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['devin_mission_jour', 'devin_mission_reponse']);
        });
    }
};
