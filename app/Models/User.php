<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
     
    protected $table = "users";
    
    protected $fillable = [
        'credentials_id',
        'scheme_id',
        'name',
        'email',
        'mobile_no',
        'password',
        'business_mcc',
        'company_type',
        'company_pan_no',
        'company_pan_no_doc',
        'company_gst_no',
        'company_gst_no_doc',
        'cin_llpin',
        'date_of_incorporation',
        'account_holder_name',
        'bank_account_no',
        'ifsc_code',
        'cancel_cheque_doc',
        'address',
        'city',
        'district',
        'state',
        'pin_code',
        'director_info',
        'payin_at_onboard',
        'payout_at_onboard',
        'website_url',
        'account_status',
        'role_type',
        'payin_callback',
        'payout_callback',
        'payin_status',
        'payout_status',
        'mid',
        'key',
        'option_1',
        'option_2',
        'payin_wallet',
        'payout_wallet',
        'total_charges',
        'rolling_amount',
        'remark',
        'qr_code',
        'description',
        'kyc',
        'email_verified_at',
        'user_addhar_doc',
        'user_pan_doc'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'key', // sensitive
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'date_of_incorporation' => 'date',
        'director_info'       => 'array',
        'account_status'      => 'boolean',
        'payin_status'      => 'boolean',
        'payout_status'      => 'boolean',
    ];
    
    public function schemes()
    {
        return $this->hasMany(Scheme::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
    
    public function authTokens()
    {
        return $this->hasMany(AuthToken::class);
    }
    
    public function credential()
    {
        return $this->belongsTo(Credential::class, 'credentials_id');
    }

}
