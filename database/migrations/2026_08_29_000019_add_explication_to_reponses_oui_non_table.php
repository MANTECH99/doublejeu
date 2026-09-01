<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reponses_oui_non', function (Blueprint $table) {
            $table->text('explication')->nullable()->after('reponse');
        });
    }

    public function down(): void
    {
        Schema::table('reponses_oui_non', function (Blueprint $table) {
            $table->dropColumn('explication');
        });
    }
};
