<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3 — cible de bankroll par joueur.
 *
 * bankroll_target : multiplicateur final choisi (1.5, 2, 2.5…)
 * bankroll_target_amount : montant cible T = B × (target + frais)
 * sessions_played : nombre de sessions jouées depuis le dépôt
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'bankroll_target')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('bankroll_target', 8, 4)->nullable()->after('recovery_used');
            $table->decimal('bankroll_target_amount', 18, 8)->nullable()->after('bankroll_target');
            $table->unsignedInteger('sessions_played')->default(0)->after('bankroll_target_amount');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'bankroll_target')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['bankroll_target', 'bankroll_target_amount', 'sessions_played']);
            });
        }
    }
};
