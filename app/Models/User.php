<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    public function role(){
        return $this->belongsto(Role::class);
    }
    public function dataUser(){
        return $this->belongsto(DataUser::class);
    }
    public function location(){
        return $this->belongsto(Location::class);
    }
    public function barbershop(){
        return $this->hasOne(Barbershop::class);
    }
    public function turns(){
        return $this->hasMany(Turn::class)->orderBy('start', 'DESC');
    }
    public function isBarber(){
        return $this->role->id == 1;
    }
    public function isClient(){
        return $this->role->id == 2;
    }
    function getData(){
        return [
            'id' =>  $this->id,
            'email' =>  $this->email,
            'role_id' => $this->role->id,
            'first_name' =>  $this->dataUser->first_name,
            'last_name' =>  $this->dataUser->last_name,
            'phone' =>  $this->dataUser->phone,
            'profile_img' =>  $this->dataUser->profile_img,
            'address' =>  $this->location->address,
            'city' =>  $this->location->city,
            'state' =>  $this->location->state,
            'zip' =>  $this->location->zip,
            'country' =>  $this->location->country,
        ];
    }
    function getFullName(){
      return $this->dataUser->first_name ." ".$this->dataUser->last_name;
    }
}