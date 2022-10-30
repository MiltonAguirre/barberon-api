<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'open', 'close', 'barber_id'
    ];
    //Relationshps
    public function barbershop(){
      return $this->belongsto(Barbershop::class);
    }
    //Tools
    public function getWorktime()
    {
        # code...
    }
}
