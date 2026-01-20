<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    /** @use HasFactory<\Database\Factories\AreaFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'action_id',
        'reaction_id',
        'action_params',
        'reaction_params',
        'is_active',
        'last_executed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'action_params' => 'array',
        'reaction_params' => 'array',
        'last_executed_at' => 'datetime',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this -> belongsTo(User::class);
    }

    public function action()
    {
        return $this -> belongsTo(Action::class);
    }

    public function reaction()
    {
        return $this -> belongsTo(Reaction::class);
    }

    public function hooks()
    {
        return $this -> hasMany(Hook::class);
    }

    public function triggers()
    {
        return $this -> hasMany(TriggerHistory::class);
    }
}
