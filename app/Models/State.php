<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $fillable = [
        'value', 'name'//{0:'cancel',1:'pending',2:'confirm', 3:'finish' }
    ];
  
    public function turn(){
      return $this->belongsto(Turn::class);
    }
}
