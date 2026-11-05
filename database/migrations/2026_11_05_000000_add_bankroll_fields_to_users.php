<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3 — modèle bankroll unique + récupération de session.
 *
 * Le bankroll est calculé à la première session et déposé une seule fois :
 *   B = C + C×X + frais (C = budget session, X = facteur de récupération).
 *
 * Champs :
 *   - bankroll_total / bankroll_remaining : montant déposé / restant
 *   - bankroll_fee                        : frais prélevés une seule fois
 *   - bankroll_recovery_reserve           : réserve de rattrapage (C × X)
 *   - recovery_factor                     : X appliqué
 *   - session_attempt                     : 0 = session 1, 1 = rattrapage
 *   - recovery_used                       : la récupération a-t-elle été consommée
 *   - session_capital                     : capital de la session en cours
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'bankroll_total')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('bankroll_total', 18, 8)->nullable()->after('min_balance');
            $table->decimal('bankroll_remaining', 18, 8)->nullable()->after('bankroll_total');
            $table->decimal('bankroll_fee', 18, 8)->nullable()->after('bankroll_remaining');
            $table->decimal('bankroll_recovery_reserve', 18, 8)->nullable()->after('bankroll_fee');
            $table->decimal('recovery_factor', 12, 6)->nullable()->after('bankroll_recovery_reserve');
            $table->decimal('session_capital', 18, 8)->nullable()->after('recovery_factor');
            $table->unsignedTinyInteger('session_attempt')->default(0)->after('session_capital');
            $table->boolean('recovery_used')->default(false)->after('session_attempt');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'bankroll_total')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn([
                    'bankroll_total',
                    'bankroll_remaining',
                    'bankroll_fee',
                    'bankroll_recovery_reserve',
                    'recovery_factor',
                    'session_capital',
                    'session_attempt',
                    'recovery_used',
                ]);
            });
        }
    }
};
