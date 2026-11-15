<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On-ramp USDT → AVAXe (MVP manuel allowlist).
 *
 * Aucun fonds n'est déplacé automatiquement : l'admin vérifie le dépôt USDT,
 * approuve la commande, envoie les AVAXe depuis la trésorerie dédiée, puis
 * enregistre le hash de crédit. Toute la piste d'audit est conservée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'onramp_allowed')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('onramp_allowed')->default(false)->after('is_admin');
            });
        }

        if (! Schema::hasTable('onramp_link_codes')) {
            Schema::create('onramp_link_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('code', 16)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('onramp_links')) {
            Schema::create('onramp_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 16)->default('telegram');
                $table->string('chat_id', 64);
                $table->string('username')->nullable();
                $table->timestamp('terms_accepted_at')->nullable();
                $table->string('terms_version', 32)->nullable();
                $table->timestamps();
                $table->unique(['channel', 'chat_id']);
                $table->unique(['user_id', 'channel']);
            });
        }

        if (! Schema::hasTable('onramp_orders')) {
            Schema::create('onramp_orders', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 16)->default('telegram');
                $table->string('chat_id', 64)->nullable();
                $table->string('network', 16);
                $table->string('package_code', 40);
                $table->decimal('usdt_amount', 18, 8);
                $table->decimal('avaxe_amount', 18, 8);
                $table->string('deposit_address');
                $table->string('tx_hash', 100)->nullable()->unique();
                $table->unsignedInteger('confirmations')->default(0);
                $table->string('status', 24)->default('pending_deposit')->index();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('admin_note')->nullable();
                $table->string('credit_tx_hash', 100)->nullable()->unique();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('credited_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('onramp_events')) {
            Schema::create('onramp_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('onramp_order_id')->nullable()->constrained('onramp_orders')->nullOnDelete();
                $table->string('type', 32);
                $table->string('actor_type', 16)->nullable();
                $table->string('actor_id', 64)->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['onramp_order_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onramp_events');
        Schema::dropIfExists('onramp_orders');
        Schema::dropIfExists('onramp_links');
        Schema::dropIfExists('onramp_link_codes');
        if (Schema::hasColumn('users', 'onramp_allowed')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('onramp_allowed');
            });
        }
    }
};
