<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AIRDROP DE BIENVENUE (2026-10-06) : un nouvel utilisateur peut réclamer
 * UNE SEULE FOIS 50 TST natifs Pingala (faucet on-chain). Le flag
 * has_received_airdrop verrouille le claim (pas de double-réclamation),
 * et le tx hash est conservé pour l'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_received_airdrop')->default(false)->after('has_received_signup_bonus');
            $table->string('airdrop_tx_hash')->nullable()->after('has_received_airdrop');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['has_received_airdrop', 'airdrop_tx_hash']);
        });
    }
};
