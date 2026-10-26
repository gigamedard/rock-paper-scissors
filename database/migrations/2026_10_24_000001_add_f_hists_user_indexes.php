<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perf : index composites sur f_hists (historique des combats).
     *
     * La requête d'évaluation de session fait :
     *   SELECT * FROM f_hists WHERE user1_id = X OR user2_id = X ORDER BY created_at ASC LIMIT 1
     *
     * Sans index sur user1_id/user2_id, elle faisait un full scan sur des
     * centaines de milliers de lignes à chaque évaluation → MySQL saturé.
     * Les index composites (user_id, created_at) passent la requête en
     * index_merge (plus de full scan).
     */
    public function up(): void
    {
        // IDEMPOTENT : sur la base prod, ces index peuvent déjà exister
        // (création manuelle / run partiel) alors que la migration n'est pas
        // enregistrée → « Duplicate key name ». On n'ajoute que les manquants.
        $existing = \Illuminate\Support\Facades\DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', \Illuminate\Support\Facades\DB::getDatabaseName())
            ->where('TABLE_NAME', 'f_hists')
            ->pluck('INDEX_NAME')
            ->unique()
            ->all();

        $missing = array_values(array_diff(['idx_f_hists_user1', 'idx_f_hists_user2'], $existing));
        if (empty($missing)) {
            return;
        }

        Schema::table('f_hists', function (Blueprint $table) use ($missing) {
            if (in_array('idx_f_hists_user1', $missing, true)) {
                $table->index(['user1_id', 'created_at'], 'idx_f_hists_user1');
            }
            if (in_array('idx_f_hists_user2', $missing, true)) {
                $table->index(['user2_id', 'created_at'], 'idx_f_hists_user2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('f_hists', function (Blueprint $table) {
            $table->dropIndex('idx_f_hists_user1');
            $table->dropIndex('idx_f_hists_user2');
        });
    }
};
