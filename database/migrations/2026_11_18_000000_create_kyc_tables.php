<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'kyc_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('kyc_status', 20)->default('unverified')->index();
                $table->timestamp('kyc_submitted_at')->nullable();
                $table->timestamp('kyc_reviewed_at')->nullable();
            });
        }

        if (! Schema::hasTable('kyc_profiles')) {
            Schema::create('kyc_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('full_name');
                $table->date('date_of_birth');
                $table->string('country', 64);
                $table->string('address');
                $table->string('city');
                $table->string('postal_code', 32)->nullable();
                $table->string('id_type', 32);
                $table->text('id_number');
                $table->text('source_of_funds')->nullable();
                $table->string('occupation')->nullable();
                $table->string('wallet_address', 64);
                $table->string('status', 20)->default('pending')->index();
                $table->string('risk_level', 20)->nullable();
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('consent_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'submitted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_profiles');
        if (Schema::hasColumn('users', 'kyc_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['kyc_status', 'kyc_submitted_at', 'kyc_reviewed_at']);
            });
        }
    }
};
