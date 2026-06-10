<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketHelpDesk extends Model
{
    use HasFactory;

    protected $table = 'ticket_help_desks';

    protected $fillable = [
        'user_id',
        'ticket_id',
        'transaction_id',
        'status',
        'priority',
        'subject',
        'description',
        'attachment',
        'admin_reply',
        'assigned_to',
    ];

    protected $casts = [
        'admin_reply' => 'array',
    ];

    // Auto-generate ticket_id if not provided
    protected static function booted()
    {
        static::creating(function ($ticket) {
            if (empty($ticket->ticket_id)) {
                $ticket->ticket_id = 'TCKT' . now()->format('YmdHis') . rand(1000, 9999);
            }
        });
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
