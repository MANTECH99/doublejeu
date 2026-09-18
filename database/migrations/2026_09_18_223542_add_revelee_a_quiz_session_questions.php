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
            $table->foreignId('revelee_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revelee_le')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_session_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revelee_par_id');
            $table->dropColumn('revelee_le');
        });
    }
};
