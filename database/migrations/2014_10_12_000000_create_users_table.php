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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('scheme_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('password')->nullable();
            $table->string('business_mcc')->nullable();
            $table->enum('company_type', ['proprietary', 'partnership', 'private', 'public', 'llp', 'society', 'trust', 'govt', 'huf', 'boi', 'aop', 'ajp'])->nullable();
            $table->string('company_pan_no')->nullable();
            $table->string('company_pan_no_doc')->nullable();
            $table->string('company_gst_no')->nullable();
            $table->string('company_gst_no_doc')->nullable();
            $table->string('cin_llpin')->nullable();
            $table->date('date_of_incorporation')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('cancel_cheque_doc')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('state')->nullable();
            $table->string('pin_code')->nullable();
            $table->json('director_info')->nullable();
            $table->enum('payin_at_onboard', ['pay_u', 'bulk_pe'])->default('pay_u')->nullable();
            $table->enum('payout_at_onboard', ['pay_u', 'bulk_pe'])->default('bulk_pe')->nullable();         
            $table->string('website_url')->nullable();
            $table->boolean('account_status')->default(0)->nullable();
            $table->enum('role_type', ['admin', 'user'])->default('user')->nullable();
            $table->string('payin_callback')->nullable();
            $table->string('payout_callback')->nullable();
            $table->string('payin_status')->nullable();
            $table->string('payout_status')->nullable();
            $table->string('mid')->nullable();
            $table->string('key')->nullable();
            $table->string('option_1')->nullable();
            $table->string('option_2')->nullable();
            $table->string('payin_wallet')->nullable();
            $table->string('payout_wallet')->nullable();
            $table->string('total_charges')->nullable();
            $table->string('rolling_amount')->nullable();
            $table->string('remark')->nullable();
            $table->string('qr_code')->nullable();
            $table->string('description')->nullable();
            $table->string('kyc')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
