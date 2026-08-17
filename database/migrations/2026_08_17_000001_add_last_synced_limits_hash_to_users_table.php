<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hash des dernières limites synchronisées on-chain (coalescence).
            // Permet de sauter l'appel setUserLimits si les limites effectives
            // n'ont pas changé depuis la dernière synchronisation (perf §A1).
            $table->string('last_synced_limits_hash', 64)->nullable()->after('payout_signature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_synced_limits_hash');
        });
    }
};
