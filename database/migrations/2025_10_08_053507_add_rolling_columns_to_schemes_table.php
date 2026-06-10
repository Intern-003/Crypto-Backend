<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table("schemes", function (Blueprint $table) {
            $table
                ->string("rolling_payin_amount")
                ->nullable()
                ->after("payout_commision_amount_above");

            $table
                ->string("rolling_fixed_amount")
                ->nullable()
                ->after("rolling_payin_amount");

            // New enum columns for types
            $table
                ->enum("rolling_payin_type", ["flat", "percent"])
                ->default("percent")
                ->nullable()
                ->after("rolling_payin_amount");
            $table
                ->enum("rolling_fixed_type", ["flat", "percent"])
                ->default("flat")
                ->nullable()
                ->after("rolling_fixed_amount");

            // GST related columns
            $table
                ->string("gst_amount")
                ->nullable()
                ->after("rolling_fixed_type");
            $table
                ->enum("gst_type", ["percent"])
                ->default("percent")
                ->nullable()
                ->after("gst_amount");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("schemes", function (Blueprint $table) {
            $table->dropColumn([
                "rolling_payin_amount",
                "rolling_fixed_amount",
                "rolling_payin_type",
                "rolling_fixed_type",
            ]);
        });
    }
};
