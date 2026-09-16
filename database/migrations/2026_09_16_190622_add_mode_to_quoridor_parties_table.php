<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quoridor_parties', function (Blueprint $table) {
            $table->enum('mode', ['classique', 'course'])->default('classique')->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('quoridor_parties', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
