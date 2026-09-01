<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions_secretes', function (Blueprint $table) {
            $table->string('devine')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('missions_secretes', function (Blueprint $table) {
            $table->dropColumn('devine');
        });
    }
};
