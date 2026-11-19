<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wave_orders', 'payment_url')) {
            Schema::table('wave_orders', function (Blueprint $table) {
                $table->string('payment_url', 500)->nullable()->after('deposit_address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wave_orders', 'payment_url')) {
            Schema::table('wave_orders', function (Blueprint $table) {
                $table->dropColumn('payment_url');
            });
        }
    }
};
