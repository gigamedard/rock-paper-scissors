<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3 — dépôt pendant une session active (top-up).
 *
 * L'indexeur envoie le total crédité on-chain via /internal/update-balance.
 * Si le joueur est en pool, on ne touche pas à sa balance courante : on mémorise
 * le nouveau total ici. Il sera appliqué (recalcul C/R/B avec la cible choisie)
 * à la fin du pool / avant le matchmaking suivant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'pending_bankroll_total')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('pending_bankroll_total', 18, 8)
                ->nullable()
                ->after('bankroll_target_amount');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'pending_bankroll_total')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pending_bankroll_total');
            });
        }
    }
};
