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
        Schema::table('reports', function (Blueprint $table) {
            $table->decimal('amount', 30, 20)->nullable()->change();
            $table->decimal('charge', 30, 20)->nullable()->change();
            $table->decimal('profit', 30, 20)->nullable()->change();
            $table->decimal('gst', 30, 20)->nullable()->change();
            $table->decimal('tds', 30, 20)->nullable()->change();
            $table->decimal('payout_amount', 30, 20)->nullable()->change();
            $table->decimal('payout_opening_balance', 30, 20)->nullable()->change();
            $table->decimal('payout_closing_balance', 30, 20)->nullable()->change();
            $table->decimal('payin_amount', 30, 20)->nullable()->change();
            $table->decimal('commission_inc_gst', 30, 20)->nullable()->change();
            $table->decimal('bank_other_charges', 30, 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Revert columns to previous state (adjust as needed)
            $table->decimal('amount', 15, 2)->nullable()->change();
            $table->decimal('charge', 15, 2)->nullable()->change();
            $table->decimal('profit', 15, 2)->nullable()->change();
            $table->decimal('gst', 15, 2)->nullable()->change();
            $table->decimal('tds', 15, 2)->nullable()->change();
            $table->decimal('payout_amount', 15, 2)->nullable()->change();
            $table->decimal('payout_opening_balance', 15, 2)->nullable()->change();
            $table->decimal('payout_closing_balance', 15, 2)->nullable()->change();
            $table->decimal('payin_amount', 15, 2)->nullable()->change();
            $table->decimal('commission_inc_gst', 15, 2)->nullable()->change();
            $table->decimal('bank_other_charges', 15, 2)->nullable()->change();
        });
    }
};
