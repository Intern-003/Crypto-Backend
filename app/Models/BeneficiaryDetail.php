<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BeneficiaryDetail extends Model
{
    use HasFactory;

    protected $table = 'beneficiary_details';

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_no',
        'ifsc_code',
        'upi_number',
        'beneficiary_name',
        'beneficiary_mobile_no',
        'beneficiary_email_id',
        'beneficiary_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
