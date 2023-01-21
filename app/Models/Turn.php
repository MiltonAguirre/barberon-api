<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DateInterval;
use DateTime;

class Turn extends Model
{
    protected $fillable = [
        'start', 'product_id' 
    ];
    //Relationshps
    public function user(){
      return $this->belongsto(User::class);
    }
    public function product(){
      return $this->belongsto(Product::class);
    }
    public function barbershop(){
        return $this->belongsto(Barbershop::class);
    }
    public function states()
    {
        return $this->hasMany(State::class);
    }
    //Methods
    public function getEnd(){
      $ending = new DateTime($this->start);
      $ending->add(new DateInterval('PT'. (int)$this->product->hours . 'H' . $this->product->minutes . 'M'));
      return $ending->format('Y-m-d H:i');
    }
    public function lastState()
    {
      return $this->states()->latest()->first();
    }

}
