<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3 — Fonds de recouvrement + file d'attente FIFO.
 *
 * Chaque joueur qui perd 2 sessions est mis en file (status=waiting) avec le
 * montant à lui rembourser. Le fonds est alimenté par les frais (ledger credit).
 * Un batch périodique paie les premiers de la file dès que le fonds atteint
 * le seuil (10 % de la dette totale) — et jamais plus que le solde disponible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('refund_queue')) {
            Schema::create('refund_queue', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->index();
                $table->decimal('amount_owed', 18, 8);
                $table->enum('status', ['waiting', 'paid'])->default('waiting')->index();
                $table->decimal('refund_pct', 8, 4)->nullable();
                $table->decimal('target_multiplier', 8, 4)->nullable();
                $table->decimal('fee_rate', 8, 6)->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('paid_at')->nullable();
            });
        }

        if (! Schema::hasTable('refund_ledger')) {
            Schema::create('refund_ledger', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['credit', 'debit'])->index();
                $table->decimal('amount', 18, 8);
                $table->string('reason')->nullable();
                $table->string('reference')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('refund_fund_state')) {
            Schema::create('refund_fund_state', function (Blueprint $table) {
                $table->id();
                $table->decimal('balance', 18, 8)->default(0);
                $table->timestamp('updated_at')->nullable();
            });
            DB::table('refund_fund_state')->insert([
                'id' => 1,
                'balance' => 0,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_queue');
        Schema::dropIfExists('refund_ledger');
        Schema::dropIfExists('refund_fund_state');
    }
};
