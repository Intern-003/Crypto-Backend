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
        Schema::create('payin_onboarded_banks', function (Blueprint $table) {
            $table->id();
            $table->string('onboard_payin_bank')->nullable();
            $table->boolean('onboarded_payin_bank_status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payin_onboarded_banks');
    }
};
