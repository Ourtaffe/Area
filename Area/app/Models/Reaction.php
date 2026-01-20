<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    protected $fillable = [
        'service_id',
        'name',
        'identifier',
        'description',
        'parameters_schema'
    ];

    protected $casts = [
        'parameters_schema' => 'array'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function areas()
    {
        return $this->hasMany(Area::class, 'reaction_id');
    }
}