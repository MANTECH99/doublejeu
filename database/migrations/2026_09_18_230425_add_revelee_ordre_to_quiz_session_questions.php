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
        Schema::table('quiz_session_questions', function (Blueprint $table) {
            $table->unsignedInteger('revelee_ordre')->nullable()->after('revelee_le');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_session_questions', function (Blueprint $table) {
            $table->dropColumn('revelee_ordre');
        });
    }
};
