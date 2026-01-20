<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthProvider extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this -> belongsTo(User::class);
    }
}
