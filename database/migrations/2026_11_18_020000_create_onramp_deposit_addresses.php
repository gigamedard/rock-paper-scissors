<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('onramp_deposit_addresses')) {
            Schema::create('onramp_deposit_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('derivation_index')->unique();
                $table->string('address', 42)->unique();
                $table->foreignId('order_id')->nullable()->unique()->constrained('onramp_orders')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();
                $table->index(['order_id', 'assigned_at']);
            });
        }

        if (! Schema::hasColumn('onramp_orders', 'deposit_index')) {
            Schema::table('onramp_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('deposit_index')->nullable()->unique()->after('deposit_address');
                $table->decimal('deposit_amount', 18, 8)->nullable()->after('deposit_index');
                $table->timestamp('deposit_detected_at')->nullable()->after('deposit_amount');
                $table->timestamp('deposit_confirmed_at')->nullable()->after('deposit_detected_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('onramp_orders', 'deposit_index')) {
            Schema::table('onramp_orders', function (Blueprint $table) {
                $table->dropUnique(['deposit_index']);
                $table->dropColumn(['deposit_index', 'deposit_amount', 'deposit_detected_at', 'deposit_confirmed_at']);
            });
        }
        Schema::dropIfExists('onramp_deposit_addresses');
    }
};
