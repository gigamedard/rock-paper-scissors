<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IDEMPOTENT : l'index peut déjà exister en prod (dérive/run partiel) →
        // on ne l'ajoute que s'il manque (évite « Duplicate key name »).
        $exists = \Illuminate\Support\Facades\DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', \Illuminate\Support\Facades\DB::getDatabaseName())
            ->where('TABLE_NAME', 'pools')
            ->where('INDEX_NAME', 'idx_pools_status_created_at')
            ->exists();

        if ($exists) {
            return;
        }

        Schema::table('pools', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_pools_status_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->dropIndex('idx_pools_status_created_at');
        });
    }
};