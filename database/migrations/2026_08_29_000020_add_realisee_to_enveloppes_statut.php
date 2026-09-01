<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enveloppes MODIFY COLUMN statut ENUM('disponible', 'utilisee', 'realisee', 'refusee') NOT NULL DEFAULT 'disponible'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enveloppes MODIFY COLUMN statut ENUM('disponible', 'utilisee', 'refusee') NOT NULL DEFAULT 'disponible'");
        }
    }
};
