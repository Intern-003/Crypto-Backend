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
            $table->renameColumn('transtion_type', 'transaction_type');
            $table->string('payout_opening_balance')->nullable()->after('payout_amount');
            $table->string('payout_closing_balance')->nullable()->after('payout_opening_balance');
            $table->enum('payout_mode', ['IMPS', 'RTGS', 'NEFT', 'UPI', 'FT'])
                  ->nullable()
                  ->after('payout_closing_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->renameColumn('transtion_type', 'transaction_type');
            $table->dropColumn(['payout_opening_balance', 'payout_closing_balance', 'payout_mode']);
        });
    }
};
