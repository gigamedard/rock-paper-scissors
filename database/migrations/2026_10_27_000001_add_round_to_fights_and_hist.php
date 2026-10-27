<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le numéro de round et le montant réellement transféré au combat.
 * Les règles diffèrent selon le round (R1 = mise de base, R2/R3 = bénéfice,
 * R4+ = mise de base même si c'est tout ce qui reste), il faut donc
 * persister le round pour l'audit/historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fights', 'round')) {
            Schema::table('fights', function (Blueprint $table) {
                $table->unsignedTinyInteger('round')->default(1)->after('pool_id');
            });
        }

        if (! Schema::hasColumn('fights', 'transferred_amount')) {
            Schema::table('fights', function (Blueprint $table) {
                $table->decimal('transferred_amount', 18, 8)->default(0)->after('base_bet_amount');
            });
        }

        if (Schema::hasTable('f_hists') && ! Schema::hasColumn('f_hists', 'round')) {
            Schema::table('f_hists', function (Blueprint $table) {
                $table->unsignedTinyInteger('round')->nullable()->after('fight_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fights', 'transferred_amount')) {
            Schema::table('fights', function (Blueprint $table) {
                $table->dropColumn('transferred_amount');
            });
        }

        if (Schema::hasColumn('fights', 'round')) {
            Schema::table('fights', function (Blueprint $table) {
                $table->dropColumn('round');
            });
        }

        if (Schema::hasTable('f_hists') && Schema::hasColumn('f_hists', 'round')) {
            Schema::table('f_hists', function (Blueprint $table) {
                $table->dropColumn('round');
            });
        }
    }
};
