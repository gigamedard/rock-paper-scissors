<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wave_portal_events')) {
            Schema::create('wave_portal_events', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_id', 100)->unique();
                $table->string('device_id', 100)->nullable();
                $table->string('direction', 8);
                $table->decimal('amount', 18, 2);
                $table->string('currency', 8)->default('XOF');
                $table->timestamp('occurred_at')->nullable();
                $table->string('status', 32)->default('received')->index();
                $table->foreignId('matched_order_id')->nullable()->constrained('wave_orders')->nullOnDelete();
                $table->string('error', 255)->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
                $table->index(['direction', 'amount', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wave_portal_events');
    }
};
