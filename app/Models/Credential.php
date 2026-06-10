<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credential extends Model
{
    use HasFactory;
    
    protected $table = 'credentials';
    protected $fillable = ['name', 'description', 'mid', 'key', 'option1', 'option2', 'option3', 'option4'];
 
    public function users()
    {
        return $this->hasMany(User::class, 'credentials_id');
    }
   
}
