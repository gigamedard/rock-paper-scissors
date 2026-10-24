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
        Schema::table('f_hists', function (Blueprint $table) {
            $table->index(['user1_id', 'created_at'], 'idx_f_hists_user1');
            $table->index(['user2_id', 'created_at'], 'idx_f_hists_user2');
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
