<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutOnboardedBank extends Model
{
    use HasFactory;
    
    protected $table = "payout_onboarded_banks";

    protected $fillable = ['onboard_payout_bank', 'onboarded_payout_bank_status'];

}
