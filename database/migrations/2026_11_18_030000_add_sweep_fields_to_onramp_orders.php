<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('onramp_orders', 'sweep_status')) {
            Schema::table('onramp_orders', function (Blueprint $table) {
                $table->string('sweep_status', 24)->nullable()->index()->after('credit_last_error');
                $table->string('sweep_tx_hash', 100)->nullable()->unique()->after('sweep_status');
                $table->decimal('sweep_amount', 18, 8)->nullable()->after('sweep_tx_hash');
                $table->unsignedInteger('sweep_attempts')->default(0)->after('sweep_amount');
                $table->timestamp('sweep_started_at')->nullable()->after('sweep_attempts');
                $table->text('sweep_last_error')->nullable()->after('sweep_started_at');
                $table->timestamp('swept_at')->nullable()->after('sweep_last_error');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('onramp_orders', 'sweep_status')) {
            Schema::table('onramp_orders', function (Blueprint $table) {
                $table->dropUnique(['sweep_tx_hash']);
                $table->dropColumn([
                    'sweep_status', 'sweep_tx_hash', 'sweep_amount',
                    'sweep_attempts', 'sweep_started_at', 'sweep_last_error', 'swept_at',
                ]);
            });
        }
    }
};
