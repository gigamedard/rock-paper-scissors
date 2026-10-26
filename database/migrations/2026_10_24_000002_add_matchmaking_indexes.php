<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perf : index composites pour le matchmaking (batch processor).
     *
     * - users : requête InternalPoolService
     *     WHERE status = 'available' AND autoplay_active = 1 AND bet_amount = X
     *   → index (status, autoplay_active, bet_amount).
     *
     * - pools : requêtes BatchProcessingService / PoolFetcherService
     *     WHERE base_bet = X AND pool_size = 5 AND status = 'from_server_waitting'
     *   → index (base_bet, pool_size, status).
     *
     * Sans ces index, les deux requêtes faisaient un full scan sur des tables
     * qui grossissent (pools surtout), dégradant MySQL à mesure du volume.
     */
    public function up(): void
    {
        // IDEMPOTENT : ces index peuvent déjà exister en prod (création manuelle /
        // run partiel) alors que la migration n'est pas enregistrée → éviter
        // « Duplicate key name ». On n'ajoute que les manquants.
        $db = \Illuminate\Support\Facades\DB::getDatabaseName();
        $existing = \Illuminate\Support\Facades\DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db)
            ->whereIn('TABLE_NAME', ['users', 'pools'])
            ->pluck('INDEX_NAME')
            ->unique()
            ->all();

        if (!in_array('idx_users_matchmaking', $existing, true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['status', 'autoplay_active', 'bet_amount'], 'idx_users_matchmaking');
            });
        }

        if (!in_array('idx_pools_matchmaking', $existing, true)) {
            Schema::table('pools', function (Blueprint $table) {
                $table->index(['base_bet', 'pool_size', 'status'], 'idx_pools_matchmaking');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_matchmaking');
        });

        Schema::table('pools', function (Blueprint $table) {
            $table->dropIndex('idx_pools_matchmaking');
        });
    }
};
