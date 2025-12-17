<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->decimal('payout_opening_balance', 15, 2)
                  ->nullable()
                  ->change();

            $table->decimal('payout_closing_balance', 15, 2)
                  ->nullable()
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('payout_opening_balance')
                  ->nullable()
                  ->change();

            $table->string('payout_closing_balance')
                  ->nullable()
                  ->change();
        });
    }
};
