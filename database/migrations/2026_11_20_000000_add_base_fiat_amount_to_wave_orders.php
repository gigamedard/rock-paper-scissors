<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wave_orders', 'base_fiat_amount')) {
            Schema::table('wave_orders', function (Blueprint $table) {
                $table->decimal('base_fiat_amount', 18, 2)->nullable()->after('fiat_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wave_orders', 'base_fiat_amount')) {
            Schema::table('wave_orders', function (Blueprint $table) {
                $table->dropColumn('base_fiat_amount');
            });
        }
    }
};
