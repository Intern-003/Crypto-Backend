<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlideTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id', 'entity_id', 'session_id',
        'created_at_utc', 'expires_at_utc', 'expired',
        'payment_status', 'payment_chain_id', 'payment_currency',
        'payment_currency_symbol', 'payment_currency_tier',
        'payment_amount', 'payment_amount_usd',
        'payer_account', 'payer_wallet_address', 'payer_email',
        'enable_refund_emails', 'payment_action',
        'payment_tx_hash', 'payment_tx_url',
        'unsigned_tx_chainid', 'unsigned_tx_to', 'unsigned_tx_value',
        'sponsored_tx_chainid', 'sponsored_tx_status',
        'sponsored_tx_hash', 'sponsored_tx_url', 'sponsored_tx_raw',
        'sponsored_tx_amount', 'sponsored_tx_currency',
        'sponsored_tx_currency_symbol', 'sponsored_tx_amount_usd',
        'gas_refuel_amount', 'gas_refuel_amount_usd',
        'gas_refuel_tx_status', 'gas_refuel_tx_hash', 'gas_refuel_tx_url',
        'gas_fee_usd', 'service_fee_usd', 'total_fee_usd',
        'eta_seconds', 'metadata', 'allow_arbitrary_deposit',
        'actual_payment_chain_id', 'actual_payment_currency',
        'actual_payment_currency_symbol', 'actual_payment_currency_tier',
        'actual_payment_amount', 'actual_payment_amount_usd'
    ];

    protected $casts = [
        'expired' => 'boolean',
        'enable_refund_emails' => 'boolean',
        'allow_arbitrary_deposit' => 'boolean',
        'metadata' => 'array',
        'created_at_utc' => 'datetime',
        'expires_at_utc' => 'datetime',
    ];
}
