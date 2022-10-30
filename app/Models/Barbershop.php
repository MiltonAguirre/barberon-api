<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barbershop extends Model
{
    protected $fillable = [
        'name','phone', 'days'
    ];
  
    public function user(){
      return $this->belongsto(User::class);
    }
    public function location(){
      return $this->belongsto(Location::class);
    }
    public function products(){
      return $this->hasMany(Product::class);
    }
    public function turns(){
      return $this->hasMany(Turn::class)->orderBy('start', 'DESC');
    }
    public function schedules(){
      return $this->hasMany(Schedule::class);
    }
    function getData(){
        return [
            'id' =>  $this->id,
            'name' =>  $this->name,
            'phone' =>  $this->phone,
            'days' => $this->days,
            'city' =>  $this->location ? $this->location->city : $this->city,
            'address' =>  $this->location ? $this->location->address : $this->address,
            'state' =>  $this->location ? $this->location->state : $this->state,
            'country' =>  $this->location ? $this->location->country : $this->country,
            'zip' =>  $this->location ? $this->location->zip : $this->zip,
        ];
    }
    function getSchedules(){
      $rounds = [];
      foreach ($this->schedules as $schedule) {
          $rounds[] = [
            'open'=>$schedule->open,
            'close'=>$schedule->close
          ];
      }
      return $rounds;
    }
}
