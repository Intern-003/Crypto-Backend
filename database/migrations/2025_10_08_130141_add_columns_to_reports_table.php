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
            $table->string('payin_rolling_amount')->nullable()->after('bank_other_charges');
            $table->string('payer_name')->nullable()->after('payin_rolling_amount');
            $table->string('payer_email')->nullable()->after('payer_name');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['payin_rolling_amount', 'payer_name', 'payer_email']);
        });
    }
};
