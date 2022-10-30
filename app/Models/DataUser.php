<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DataUser extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'first_name','last_name','phone','profile_img'
    ];
  
      public function User(){
        return $this->hasOne(User::class);
      }
  
      function getFullName(){
        return $this->first_name . " " . $this->last_name;
      }
      function getPhone(){
        return $this->phone;
      }
}
