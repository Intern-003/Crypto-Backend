<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Microlog extends Model
{
    use HasFactory;
    
    protected $table = "micro_logs";
    
    protected $fillable = ["product_name", "product_response"];
}
