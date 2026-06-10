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
        Schema::create('payout_onboarded_banks', function (Blueprint $table) {
            $table->id();
            $table->string('onboard_payout_bank')->nullable();
            $table->boolean('onboarded_payout_bank_status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_onboarded_banks');
    }
};
