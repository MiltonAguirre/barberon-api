<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'address','city','state','country','zip'
    ];
    public function user(){
        return $this->hasOne(User::class);
    }
    public function barbershop(){
        return $this->hasOne(Barbershop::class);
    }
    public function getFullLocation(){
        return $this->address." ". $this->city . ", ". $this->state .", ". $this->country;
    }
}
