<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offramp_orders')) {
            Schema::create('offramp_orders', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 16)->default('telegram');
                $table->string('chat_id', 64)->nullable();
                $table->string('payout_network', 16)->default('polygon');
                $table->string('payout_address', 42);
                $table->decimal('avaxe_amount', 18, 8);
                $table->decimal('usdt_amount', 18, 8);
                $table->decimal('rate', 18, 8)->default(0);
                $table->decimal('fee_percent', 8, 4)->default(0);
                $table->string('status', 24)->default('pending_deposit')->index();
                $table->string('deposit_address', 42);
                $table->unsignedBigInteger('deposit_index')->nullable()->unique();
                $table->decimal('deposit_amount', 18, 8)->nullable();
                $table->string('tx_hash', 100)->nullable()->unique();
                $table->unsignedInteger('confirmations')->default(0);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('deposit_detected_at')->nullable();
                $table->timestamp('deposit_confirmed_at')->nullable();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('admin_note')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('payout_tx_hash', 100)->nullable()->unique();
                $table->unsignedInteger('payout_attempts')->default(0);
                $table->timestamp('payout_started_at')->nullable();
                $table->text('payout_last_error')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('sweep_status', 24)->nullable()->index();
                $table->string('sweep_tx_hash', 100)->nullable()->unique();
                $table->decimal('sweep_amount', 18, 8)->nullable();
                $table->unsignedInteger('sweep_attempts')->default(0);
                $table->timestamp('sweep_started_at')->nullable();
                $table->text('sweep_last_error')->nullable();
                $table->timestamp('swept_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('offramp_deposit_addresses')) {
            Schema::create('offramp_deposit_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('derivation_index')->unique();
                $table->string('address', 42)->unique();
                $table->foreignId('order_id')->nullable()->unique()->constrained('offramp_orders')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();
                $table->index(['order_id', 'assigned_at']);
            });
        }

        if (! Schema::hasTable('offramp_events')) {
            Schema::create('offramp_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offramp_order_id')->nullable()->constrained('offramp_orders')->nullOnDelete();
                $table->string('type', 40);
                $table->string('actor_type', 16)->nullable();
                $table->string('actor_id', 64)->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['offramp_order_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offramp_events');
        Schema::dropIfExists('offramp_deposit_addresses');
        Schema::dropIfExists('offramp_orders');
    }
};
