<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('mobile')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('charge', 15, 2)->nullable();
            $table->decimal('profit', 15, 2)->nullable();
            $table->decimal('gst', 15, 2)->nullable();
            $table->decimal('tds', 15, 2)->nullable();
            $table->string('apitxnid')->nullable();
            $table->string('txnid')->nullable();
            $table->string('payid')->nullable();
            $table->string('refno')->nullable();
            $table->text('description')->nullable();
            $table->text('remark')->nullable();
            $table->string('option1')->nullable();
            $table->string('option2')->nullable();
            $table->string('option3')->nullable();
            $table->string('option4')->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'reversed', 'refunded', 'complete', 'initiated'])->nullable();
            $table->enum('payment_platform', ['api', 'portal', 'app'])->nullable();
            $table->decimal('payout_amount', 15, 2)->nullable();
            $table->decimal('payin_amount', 15, 2)->nullable();
            $table->enum('transtion_type', ['credit', 'debit', 'none'])->nullable();
            $table->enum('product', ['fund_loadwallet','UPI','payout','upicollect','fund_transfer'])->nullable();
            $table->string('mytxnid')->nullable();
            $table->string('aepstype')->nullable();
            $table->string('payee_vpa')->nullable();
            $table->string('payer_vpa')->nullable();
            $table->string('payer_mobile')->nullable();
            $table->string('payer_acc_no')->nullable();
            $table->string('payer_ifsc')->nullable();
            $table->decimal('commission_inc_gst', 15, 2)->nullable();
            $table->decimal('bank_other_charges', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
