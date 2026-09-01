<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couples', function (Blueprint $table) {
            $table->id();
            $table->string('code_unique', 16)->unique();
            $table->foreignId('user1_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user2_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('streak')->default(0);
            $table->unsignedInteger('score_total')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couples');
    }
};
