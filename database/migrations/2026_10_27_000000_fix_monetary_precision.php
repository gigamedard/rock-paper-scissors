<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX PRÉCISION (2026-10-27) : plusieurs colonnes monétaires étaient en
 * decimal(x,2) / decimal(x,4), ce qui tronquait les mises sub-centimes
 * (ex. base bet 0.0004 → 0.00). Le contrat on-chain (AVAXe, 18 décimales)
 * accepte déjà ces montants : seul le schéma DB les tronquait.
 *
 * On aligne tout sur decimal(18,8) comme users.balance / pools.base_bet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fights')) {
            Schema::table('fights', function (Blueprint $table) {
                $table->decimal('base_bet_amount', 18, 8)->default(0)->change();
                $table->decimal('max_bet_amount', 18, 8)->default(0)->change();
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'initial_base_bet')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('initial_base_bet', 18, 8)->nullable()->change();
            });
        }

        if (Schema::hasTable('user_settings')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->decimal('base_bet_amount', 18, 8)->default(0)->change();
                $table->decimal('max_bet_amount', 18, 8)->nullable()->change();
            });
        }

        if (Schema::hasTable('queue_table')) {
            Schema::table('queue_table', function (Blueprint $table) {
                $table->decimal('base_bet', 18, 8)->default(0.00000001)->change();
            });
        }

        if (Schema::hasTable('challenges')) {
            Schema::table('challenges', function (Blueprint $table) {
                $table->decimal('base_bet_amount', 18, 8)->default(0)->change();
                $table->decimal('max_bet_amount', 18, 8)->default(0)->change();
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->decimal('amount', 18, 8)->change();
            });
        }
    }

    public function down(): void
    {
        // Best-effort revert (peut tronquer les valeurs sub-centimes).
        if (Schema::hasTable('fights')) {
            Schema::table('fights', function (Blueprint $table) {
                $table->decimal('base_bet_amount', 10, 2)->default(0)->change();
                $table->decimal('max_bet_amount', 10, 2)->default(0)->change();
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'initial_base_bet')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('initial_base_bet', 16, 4)->nullable()->change();
            });
        }

        if (Schema::hasTable('user_settings')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->decimal('base_bet_amount', 15, 2)->default(0)->change();
                $table->decimal('max_bet_amount', 15, 2)->nullable()->change();
            });
        }

        if (Schema::hasTable('queue_table')) {
            Schema::table('queue_table', function (Blueprint $table) {
                $table->decimal('base_bet', 8, 2)->default(0.01)->change();
            });
        }

        if (Schema::hasTable('challenges')) {
            Schema::table('challenges', function (Blueprint $table) {
                $table->decimal('base_bet_amount', 10, 2)->default(0)->change();
                $table->decimal('max_bet_amount', 10, 2)->default(0)->change();
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->decimal('amount', 8, 2)->change();
            });
        }
    }
};
