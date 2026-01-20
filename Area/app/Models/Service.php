<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'auth_type',
        'description',
        'config_schema'
    ];

    protected $casts = [
        'config_schema' => 'array'
    ];

    public function actions()
    {
        return $this->hasMany(Action::class);
    }

    public function reactions()
    {
        return $this->hasMany(Reaction::class);
    }

    public function userServices()
    {
        return $this->hasMany(UserService::class);
    }
}