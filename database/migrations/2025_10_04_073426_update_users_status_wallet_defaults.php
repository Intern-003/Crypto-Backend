<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Change string to boolean
            $table->boolean('account_status')->default(true)->change();
            $table->boolean('payin_status')->default(true)->change();
            $table->boolean('payout_status')->default(true)->change();
            $table->boolean('kyc')->default(false)->change();
            // Set default values for string columns
            $table->string('payin_wallet')->default('00.00')->change();
            $table->string('payout_wallet')->default('00.00')->change();
            $table->string('total_charges')->default('00.00')->change();
            $table->string('rolling_amount')->default('00.00')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert back to original string
            $table->string('payin_status')->nullable()->change();
            $table->string('payout_status')->nullable()->change();
            $table->string('kyc')->nullable()->change();

            // Remove default for string columns
            $table->string('payin_wallet')->nullable()->change();
            $table->string('payout_wallet')->nullable()->change();
            $table->string('total_charges')->nullable()->change();
            $table->string('rolling_amount')->nullable()->change();
        });
    }
};
