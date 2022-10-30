<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name','price','hours','minutes','description'
    ];
    //Relationshps
    public function barbershop(){
      return $this->belongsto(Barbershop::class);
    }
    public function images(){
      return $this->hasMany(ImageProduct::class);
    }
}
