<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthToken extends Model
{
    use HasFactory;
    
    protected $table = "auth_tokens";
    
    protected $fillable = ['user_id', 'token', 'ip'];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
