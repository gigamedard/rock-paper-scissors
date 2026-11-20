<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('swap_orders')) {
            Schema::create('swap_orders', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->string('direction', 24); // usdt_to_avaxe | avaxe_to_usdt
                $table->string('network', 16);   // polygon | bsc
                $table->decimal('amount_in', 18, 8);
                $table->decimal('amount_out', 18, 8);
                $table->decimal('rate', 18, 8);
                $table->string('deposit_address', 42);
                $table->string('payout_address', 42);
                $table->string('status', 32)->default('pending_deposit')->index();
                $table->string('deposit_tx_hash', 100)->nullable()->unique();
                $table->unsignedInteger('confirmations')->default(0);
                $table->string('payout_tx_hash', 100)->nullable()->unique();
                $table->text('error')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['direction', 'status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('swap_orders');
    }
};
