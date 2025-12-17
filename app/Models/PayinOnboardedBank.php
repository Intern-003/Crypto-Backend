<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayinOnboardedBank extends Model
{
    use HasFactory;
    
    protected $table = "payin_onboarded_banks";
    
    protected $fillable = ['onboard_payin_bank', 'onboarded_payin_bank_status'];
}
