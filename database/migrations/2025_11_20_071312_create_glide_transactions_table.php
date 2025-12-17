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
        Schema::create('glide_transactions', function (Blueprint $table) {
            $table->id();

            // Webhook + Session Identifiers
            $table->uuid('webhook_id')->nullable();
            $table->uuid('entity_id')->nullable();
            $table->uuid('session_id')->nullable();

            // Session Data
            $table->timestamp('created_at_utc')->nullable();
            $table->timestamp('expires_at_utc')->nullable();
            $table->boolean('expired')->default(false);

            // Payment Info
            $table->string('payment_status', 50)->nullable();
            $table->string('payment_chain_id', 100)->nullable();
            $table->string('payment_currency', 100)->nullable();
            $table->string('payment_currency_symbol', 50)->nullable();
            $table->string('payment_currency_tier', 20)->nullable();

            $table->decimal('payment_amount', 36, 18)->nullable();
            $table->decimal('payment_amount_usd', 20, 6)->nullable();

             // Payer Info
            $table->string('payer_account', 255)->nullable();
            $table->string('payer_wallet_address', 255)->nullable();
            $table->string('payer_email', 255)->nullable();
            $table->boolean('enable_refund_emails')->default(false);

            // Payment Action + Transactions
            $table->string('payment_action', 150)->nullable();
            $table->string('payment_tx_hash', 255)->nullable();
            $table->text('payment_tx_url')->nullable();

             // Unsigned Transaction
            $table->string('unsigned_tx_chainid', 100)->nullable();
            $table->string('unsigned_tx_to', 255)->nullable();
            $table->string('unsigned_tx_value', 255)->nullable();

            // Sponsored Transaction
            $table->string('sponsored_tx_chainid', 100)->nullable();
            $table->string('sponsored_tx_status', 50)->nullable();
            $table->string('sponsored_tx_hash', 255)->nullable();
            $table->text('sponsored_tx_url')->nullable();
            $table->longText('sponsored_tx_raw')->nullable();

            $table->decimal('sponsored_tx_amount', 20, 6)->nullable();
            $table->string('sponsored_tx_currency', 150)->nullable();
            $table->string('sponsored_tx_currency_symbol', 50)->nullable();
            $table->decimal('sponsored_tx_amount_usd', 20, 6)->nullable();

            // Gas + Fees
            $table->decimal('gas_refuel_amount', 20, 6)->nullable();
            $table->decimal('gas_refuel_amount_usd', 20, 6)->nullable();
            $table->string('gas_refuel_tx_status', 50)->nullable();
            $table->string('gas_refuel_tx_hash', 255)->nullable();
            $table->text('gas_refuel_tx_url')->nullable();

            $table->decimal('gas_fee_usd', 20, 6)->nullable();
            $table->decimal('service_fee_usd', 20, 6)->nullable();
            $table->decimal('total_fee_usd', 20, 6)->nullable();

            // Additional Fields
            $table->integer('eta_seconds')->nullable();
            $table->longText('metadata')->nullable();
            $table->boolean('allow_arbitrary_deposit')->default(false);

            // Actual Payment
            $table->string('actual_payment_chain_id', 100)->nullable();
            $table->string('actual_payment_currency', 100)->nullable();
            $table->string('actual_payment_currency_symbol', 50)->nullable();
            $table->string('actual_payment_currency_tier', 20)->nullable();

            $table->decimal('actual_payment_amount', 36, 18)->nullable();
            $table->decimal('actual_payment_amount_usd', 36, 18)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glide_transactions');
    }
};
