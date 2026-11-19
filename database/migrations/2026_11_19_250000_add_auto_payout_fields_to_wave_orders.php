<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wave_orders', 'payout_attempts')) {
            Schema::table('wave_orders', function (Blueprint $table) {
                $table->unsignedInteger('payout_attempts')->default(0)->after('usdt_payout_tx_hash');
                $table->timestamp('payout_started_at')->nullable()->after('payout_attempts');
                $table->text('payout_last_error')->nullable()->after('payout_started_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wave_orders', 'payout_attempts')) {
            Schema::table('wave_orders', function (Blueprint $table) {
                $table->dropColumn(['payout_attempts', 'payout_started_at', 'payout_last_error']);
            });
        }
    }
};
