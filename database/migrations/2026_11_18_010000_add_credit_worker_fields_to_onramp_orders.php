<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('onramp_orders', 'credit_attempts')) {
            Schema::table('onramp_orders', function (Blueprint $table) {
                $table->unsignedInteger('credit_attempts')->default(0)->after('confirmations');
                $table->timestamp('credit_started_at')->nullable()->after('credit_attempts');
                $table->text('credit_last_error')->nullable()->after('credit_started_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('onramp_orders', 'credit_attempts')) {
            Schema::table('onramp_orders', function (Blueprint $table) {
                $table->dropColumn(['credit_attempts', 'credit_started_at', 'credit_last_error']);
            });
        }
    }
};
