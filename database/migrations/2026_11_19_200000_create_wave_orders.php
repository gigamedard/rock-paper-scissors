<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wave_orders')) {
            Schema::create('wave_orders', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 16)->default('telegram');
                $table->string('chat_id', 64)->nullable();
                $table->string('direction', 16); // buy_usdt (Wave -> USDT) | sell_usdt (USDT -> Wave)
                $table->string('operator', 32)->default('wave_ci');
                $table->string('country', 8)->default('CI');
                $table->string('currency', 8)->default('XOF');
                $table->decimal('fiat_amount', 18, 2);
                $table->decimal('usdt_amount', 18, 8);
                $table->decimal('usdt_gross', 18, 8)->nullable();
                $table->decimal('rate', 18, 8);
                $table->decimal('fee_percent', 8, 4)->default(0);
                $table->string('status', 24)->default('pending_payment')->index();
                $table->string('payment_phone', 32)->nullable();
                $table->string('payout_phone', 32)->nullable();
                $table->string('payout_network', 16)->default('polygon');
                $table->string('deposit_address', 42)->nullable();
                $table->string('payment_ref', 120)->nullable();
                $table->string('usdt_deposit_tx_hash', 100)->nullable()->unique();
                $table->string('usdt_payout_tx_hash', 100)->nullable()->unique();
                $table->string('fiat_tx_ref', 120)->nullable();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('admin_note')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['direction', 'status', 'created_at']);
            });
        }

        if (! Schema::hasTable('wave_events')) {
            Schema::create('wave_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wave_order_id')->nullable()->constrained('wave_orders')->nullOnDelete();
                $table->string('type', 40);
                $table->string('actor_type', 16)->nullable();
                $table->string('actor_id', 64)->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['wave_order_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wave_events');
        Schema::dropIfExists('wave_orders');
    }
};
