<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlideSession extends Model
{
    use HasFactory;

    protected $table = "glide_sessions";
    
    protected $fillable = ["stages","glide_request", "glide_response"];
}
