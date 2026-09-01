<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_session_questions', function (Blueprint $table) {
            $table->string('bonne_reponse', 255)->nullable()->after('resultat');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_session_questions', function (Blueprint $table) {
            $table->dropColumn('bonne_reponse');
        });
    }
};
