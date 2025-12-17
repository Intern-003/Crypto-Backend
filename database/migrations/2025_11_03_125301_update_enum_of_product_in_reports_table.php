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
        DB::statement("
            ALTER TABLE `reports` 
            CHANGE `product` `product` 
            ENUM('fund_loadwallet','UPI','payout','upicollect','fund_transfer','topup_payout','payin_settlement','take_back_from_wallet') 
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci 
            NULL DEFAULT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE `reports` 
            CHANGE `product` `product` 
            ENUM('fund_loadwallet','UPI','payout','upicollect','fund_transfer','topup_payout','payin_settlement') 
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci 
            NULL DEFAULT NULL
        ");
    }
};
