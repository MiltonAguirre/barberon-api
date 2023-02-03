<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenDevice extends Model
{
    protected $fillable = [
        'user_id', 'is_active', 'token'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
