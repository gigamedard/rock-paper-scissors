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
        Schema::table('referrals', function (Blueprint $table) {
            if (!Schema::hasColumn('referrals', 'referral_code')) {
                $table->string('referral_code')->nullable()->after('referred_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
             if (Schema::hasColumn('referrals', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
        });
    }
};
