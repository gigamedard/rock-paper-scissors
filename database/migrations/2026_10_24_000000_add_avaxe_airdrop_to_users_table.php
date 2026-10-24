<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chantier feat/avaxe-bridge (P2) : faucet AVAXe de test.
     * Ajoute un flag one-shot + le tx hash de l'airdrop AVAXe (ERC-20),
     * distinct de l'airdrop TST natif (has_received_airdrop/airdrop_tx_hash).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_received_avaxe_airdrop')->default(false)->after('airdrop_tx_hash');
            $table->string('avaxe_airdrop_tx_hash')->nullable()->after('has_received_avaxe_airdrop');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['has_received_avaxe_airdrop', 'avaxe_airdrop_tx_hash']);
        });
    }
};
