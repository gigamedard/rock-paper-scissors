<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blockchain_trade_id')->unique();
            $table->string('seller_wallet_address');
            $table->decimal('snt_amount', 18, 2);
            $table->decimal('avax_amount', 18, 8);
            $table->enum('status', ['open', 'fulfilled', 'cancelled'])->default('open');
            $table->timestamp('expires_at');
            $table->string('buyer_wallet_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
