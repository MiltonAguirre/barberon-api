<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    const NAMES = ['canceled', 'pending', 'accepted', 'finished'];
    protected $fillable = ['comment', 'value', 'turn_id'];
  
    public function turn(){
      return $this->belongsto(Turn::class);
    }
    public function name()
    {
      return $this::NAMES[$this->value];
    }
    
}
