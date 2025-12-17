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
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->boolean('status')->default(1)->nullable();
            $table->enum('payin_commision_type', ['flat', 'percent'])->default('flat')->nullable();
            $table->string('payin_commision_amount')->nullable();
            $table->enum('payout_commision_type_below', ['flat', 'percent'])->default('flat')->nullable();
            $table->string('payout_commision_amount_below')->nullable();
            $table->enum('payout_commision_type_above', ['flat', 'percent'])->default('percent')->nullable();
            $table->string('payout_commision_amount_above')->nullable();            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schemes');
    }
};
