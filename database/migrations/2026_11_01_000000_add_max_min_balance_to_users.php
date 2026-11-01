<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enregistre le plus haut et le plus bas niveau de balance atteint par chaque bot
 * (pour l'analyse a posteriori des cas de ruine).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'max_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('max_balance', 18, 8)->nullable()->after('balance');
                $table->decimal('min_balance', 18, 8)->nullable()->after('max_balance');
            });
        }

        // Initialise avec la balance courante (l'historique antérieur n'est pas reconstructible).
        DB::table('users')->whereNull('max_balance')->update([
            'max_balance' => DB::raw('balance'),
        ]);
        DB::table('users')->whereNull('min_balance')->update([
            'min_balance' => DB::raw('balance'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'max_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['max_balance', 'min_balance']);
            });
        }
    }
};
