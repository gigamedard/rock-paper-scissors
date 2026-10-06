<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX SCHÉMA (2026-10-06, prod) : users.bet_amount = decimal(8,2) tronquait
 * le base bet 0.0004 → 0.00 (le pipeline de pools interne filtre/matche sur
 * bet_amount — les bots restaient au tiers 0.00, aucune pool créée).
 * Aligné sur balance/battle_balance : decimal(18,8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('bet_amount', 18, 8)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('bet_amount', 8, 2)->default(0.00001)->change();
        });
    }
};