<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scheme extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
     
    protected $table = "schemes";

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'status',
        'payin_commision_type',
        'payin_commision_amount',
        'payout_commision_type_below',
        'payout_commision_amount_below',
        'payout_commision_type_above',
        'payout_commision_amount_above',
        'rolling_payin_amount',
        'rolling_payin_type',
        "rolling_fixed_amount",
        "rolling_fixed_type",
        "gst_amount",
        "gst_type"
    ];

    /**
     * Cast attributes
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payin_commision_amount'         => 'decimal:2',
        'payout_commision_amount_below'  => 'decimal:2',
        'payout_commision_amount_above'  => 'decimal:2',
        'status'                         => 'boolean',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
