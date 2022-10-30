<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageProduct extends Model
{
    protected $fillable = [
        'path',
    ];
    //Relationshps
    public function product(){
      return $this->belongsto(Product::class);
    }
}
